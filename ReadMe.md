# Webroot Plugin for Lychee gallery manager

This plugin prevent the lychee folder (with all the php scripts, the other plugins and *your photos*) to be publicly visible.
Your photo are serve to the user with a php script that check the permission of the user and, if he's allowed, send the photo with the xsendfile directive (or with php, as you want).

# Installation

go to your lychee folder, then:

	cd plugins
	git clone https://github.com/Bramas/lychee-webroot.git webroot

Now the same lychee website is accessible by adding `/plugins/webroot` to the url (but the photos are not accessible)
Make this path the root of your website: replace /path/to/lychee by /path/to/lychee/plugins/webroot in the configuration file of the web server of your choice. For nginx, replace the line 

	root /path/to/lychee;

by

	root /path/to/lychee/plugins/webroot;

to make the photo accessible, rewrite the url in the following way. With nginx, add the current line undex `location / { ... }` in the conf file for this website:

	rewrite ^/uploads/([^\/]+)/(.*)$ /getPhoto.php?type=$1&url=$2;

Now you should see your website as before.
Try to see a private photo with a direct link while logged out to verify that the photo is not accessible.
