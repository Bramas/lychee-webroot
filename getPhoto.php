<?php
namespace Lychee;

# must be one of the following value:
# * auto   (automatically choose the best option)
# * php    (use php readfile() function)
# * nginx  (use nginx xsendfile header)
# * apache (use apache xsendfile header)

$sendFileMethod = 'php';

$uploadsPath    = __DIR__ . '/../../uploads';


# ----------------- End of configuration -------------------------
# -----------------------------------------------------------------

$uploadsPath = realpath($uploadsPath);

if(empty($_GET['type']) || empty($_GET['url']))
{
	exit('Error: Unable to find your photo.');
}

$type = $_GET['type'];
$url  = $_GET['url'];


# Usual startup (taken from php/index.php file)

use Lychee\Modules\Album;
use Lychee\Modules\Config;
use Lychee\Modules\Settings;
use Lychee\Modules\Database;

require(__DIR__ . '/../../php/define.php');
require(__DIR__ . '/../../php/autoload.php');

session_start();
date_default_timezone_set('UTC');

if (Config::exists()===false) {
	exit('Error: no config file.');
}
$isAdmin = false;
if ((isset($_SESSION['login'])&&$_SESSION['login']===true)&&
	(isset($_SESSION['identifier'])&&$_SESSION['identifier']===Settings::get()['identifier'])) {
	$isAdmin = true;
}
# end of startup

$database = Database::get();

# return the photo if the current user has acces to it or false otherwise (taken from php/module/photo.php)
function getPhoto($database, $type, $photoUrl, $isAdmin)
{
	$retinaSuffix = '@2x';
	$urlParts = explode('.', $photoUrl);
	$dbUrl = $photoUrl;
	# If the filename ends in $retinaSuffix, remove it for the database query
	if (substr_compare($urlParts[0], $retinaSuffix, strlen($urlParts[0])-strlen($retinaSuffix), strlen($retinaSuffix)) === 0) {
		$dbUrl = substr($urlParts[0], 0, -strlen($retinaSuffix)) . '.' . $urlParts[1];
	}

	# Get photo
	if($type == 'thumb')
	{
		$query	= Database::prepare(
			$database, 
			"SELECT * FROM ? WHERE thumbUrl = '?' LIMIT 1", 
			array(
				LYCHEE_TABLE_PHOTOS, 
				$dbUrl
			)
		);
	}
	else {
		$query	= Database::prepare(
			$database, 
			"SELECT * FROM ? WHERE url = '?' LIMIT 1", 
			array(
				LYCHEE_TABLE_PHOTOS, 
				$dbUrl
			)
		);
	}
	$photos	= Database::execute($database, $query, __METHOD__, __LINE__);
	$photo	= $photos->fetch_object();
	if ($photo === null) {
		http_response_code(404);
		exit('Photo not found');
	}
	# Check if public
	if ($isAdmin=== true||$photo->public==='1') {
		# Photo public
		return $photo;
	} else {
		# Check if album public
		$album	= new Album($database, null, null, $photo->album);
		$agP	= $album->getPublic();

		if ($agP===true) return $photo;
	}

	# Photo private
	return false;
}

# get the photo
$photo = getPhoto($database, $type, $url, $isAdmin);


# Check the permission
if($photo === false)
{
	exit('Error: You do not have access to this photo.');
}

# Check the type of the request
if( ! in_array($type, array('big', 'import',  'medium', 'small', 'thumb')))
{
	exit('Error: type not recognized');
}

# check if the file exists
$filepath = $uploadsPath . DIRECTORY_SEPARATOR  . $type . DIRECTORY_SEPARATOR  . $url;
if( ! file_exists($filepath))
{
	Log::error($database, 'plugin::webroot', __LINE__,  'The file does not exists: '.$filepath);
	exit('Error: the file does not exists (see the log).');
}

# set the content-type
header('Content-type: '.$photo->type);

# send the file with the appropriate methode
if($sendFileMethod == 'auto')
{
	$sendFileMethod = 'php';
	if(strstr($_SERVER["SERVER_SOFTWARE"], 'nginx'))
	{
		$sendFileMethod = 'nginx';
	}
	elseif(strstr($_SERVER["SERVER_SOFTWARE"], 'Apache'))
	{
		$sendFileMethod = 'apache';
	}
}
switch($sendFileMethod)
{
	default:
	case 'php':

	readfile(__DIR__ . '/../../uploads/'.$type.'/'.$url);

	break;
	case 'nginx':

	$fileurl  = '/protected-uploads/' . $type . '/' . $url;
	header('X-Accel-Redirect: ' . $fileurl);

	break;

	case 'apache':

	header('X-Sendfile: '. $filepath);

	break;
}
