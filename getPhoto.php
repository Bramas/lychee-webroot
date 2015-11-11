<?php

# must be one of the following value:
# * auto   (automatically choose the best option)
# * php    (use php readfile() function)
# * nginx  (use nginx xsendfile header)
# * apache (use apache xsendfile header)

$sendFileMethod = 'auto';

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


# Usual startup (taken from php/api.php file)
session_start();
date_default_timezone_set('UTC');
# Load required files
require(__DIR__ . '/../../php/define.php');
require(__DIR__ . '/../../php/autoload.php');
require(__DIR__ . '/../../php/modules/misc.php');

if (file_exists(LYCHEE_CONFIG_FILE)) require(LYCHEE_CONFIG_FILE);
else {
	exit('Error: no config file.');
}
# Define the table prefix
if (!isset($dbTablePrefix)) $dbTablePrefix = '';
defineTablePrefix($dbTablePrefix);
# Connect to database
$database = Database::connect($dbHost, $dbUser, $dbPassword, $dbName);

# Load settings
$settings = new Settings($database);
$settings = $settings->get();

$isAdmin = false;
if ((isset($_SESSION['login'])&&$_SESSION['login']===true)&&
	(isset($_SESSION['identifier'])&&$_SESSION['identifier']===$settings['identifier'])) {
	$isAdmin = true;
}
# end of startup


# return the photo if the current user has acces to it or false otherwise (taken from php/module/photo.php)
function getPhoto($database, $type, $photoUrl, $isAdmin)
{

	# Get photo
	if($type == 'thumb')
	{
		$query	= Database::prepare(
			$database, 
			"SELECT public, album FROM ? WHERE thumbUrl = '?' LIMIT 1", 
			array(
				LYCHEE_TABLE_PHOTOS, 
				$photoUrl
			)
		);
	}
	else {
		$query	= Database::prepare(
			$database, 
			"SELECT public, album FROM ? WHERE url = '?' LIMIT 1", 
			array(
				LYCHEE_TABLE_PHOTOS, 
				$photoUrl
			)
		);
	}
	$photos	= $database->query($query);
	$photo	= $photos->fetch_object();
	# Check if public
	if ($isAdmin=== true||$photo->public==='1') {
		# Photo public
		return $photo;
	} else {
		# Check if album public
		$album	= new Album($database, null, null, $photo->album);
		$agP	= $album->getPublic();
		$acP	= $album->checkPassword($password);
		# Album public and password correct
		if ($agP===true&&$acP===true) return $photo;
		# Album public, but password incorrect
		if ($agP===true&&$acP===false) return $photo;
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
if( ! in_array($type, array('big', 'import',  'medium',  'thumb')))
{
	exit('Error: type not recognized');
}

# check if the file exists
$filepath = $uploadsPath . '/' . $type . '/' . $url;
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
