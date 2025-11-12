WP Safe Mode v1 beta
-----------------------
WP Safe Mode Beta is self hosted administration and development tool that helps user to fix, hack and manage WordPress, out of the 
WordPress itself.
Beta version is currently free to download and use for testing and work with your WordPress site. 
 
It helps with migration, backup, fixing, plugin activations, themes, debugging, htaccess modifications and much, much more..
The WP safe mode tool is an essential for WordPress end users and developers.

Managed by: imbamarketing.com and cloud-industry.com


Features
-----------------------
 - Edit options  in wp_config - configuration file 
 - Themes Switch - switch existing themes out of the WordPress using this tool 
 - Download and install Twenty Fifteen theme from wordpress.org repository
 - Backup database - fully and archive optionally 
 - Backup database tables - selected tables will be exported in sql and/or csv format and can be archived optionally 
 - Backup full WordPress installation directory 
 - Download of backup files 
 - Edit of main .htaccess in WordPress directory
 - Edit of robots.txt in WordPress directory
 - PHP error_log read - formatted overview of PHP error_log file + search through error_log file
 - Search database - experimental
 - Quick actions - set of quick action buttons 
 - Optimize database tables 
 - Maintenance mode 
 - Delete Unapproved Comments
 - Delete spam comments 
 - Delete post revisions 
 - Change of home and siteurl option values 
 - Scan WordPress core files
 - Autobackup - settings for automatic backup of your WordPress files and database 



Requirements
-----------------------
Apache web server
PHP  5.3.x or newer
MySQL 5.3.x or newer 

Permissions to write to folder on server 
Functional WordPress website on server 
MySQL user with all privileges 

Installation
-----------------------
1 - Unpack archive copy the folder in a root directory on your web server where your WordPress is installed, e.g. http://www.yourdomain.com/wordpress-safe-mode
2 - Copy and rename settings sample file settings.sample.php into settings.php and 
3 - Set WordPress site directory for $settings['wp_dir'] if it is not in same directory and $settings['sfstore'] for where your backup files will be stored. Rest of data in settings.php don't edit
4 - Make sure the directory for backup files is writtable and wp_config.php can be edited. 
5 - Access directly to e.g. http://www.yourdomain.com/wordpress-safe-mode/  . Optionally, you can rename your WP Safe Mode main directory

Change Log
-----------------------

v0.06 beta 
added login feature - setup login credentials and use login to access tool 
added global settings feature 
automatically create settings.php if doesn't exists
all backups stored now in wp safe mode storage 
minor fixes

v0.05 beta
new sections and features added -  quick actions, .htaccess generator, robots.txt editor, php error_log read, maintenance mode, optimize tables, delete all spam/unapproved comments, search database, autobackup, delete post revisions, change of siteurl and home option values, scan WordPress core files
new design 
major code fixes 


v0.04  beta

mobile friendly layout 
minor fixes

v0.03  beta

removed intercom signup, added links for support forum and contact
added inactive links for coming soon features

v0.02  beta

Added intercom signup for support and update notifications 
Few visual UI tweaks 




Authors and Contributors
-----------------------

CloudIndustry - http://cloud-industry.com 
Nikola Kirincic, Marko Tiosavljevic, Daliborka Ciric, Luka Cvetinovic, Nikola Stojanovic


License
-----------------------

Please check license.txt or visit http://wpsafemode.com/licenses/ 


Trademark note: 

WP Safe Mode, and wpsafemode are trademarks of Cloud Industry LLC, &copy Cloud Industry LLC, all rights reserved 

Note
-----------------------
This tool is still in experimental phase. It is recommended to test first in development environment or to backup files first. 

Please do not move branding or links

Please visit http://wpsafemode.com/ to signup and leave a feedback so we can improve our tool and give you even with more features and fixed bugs. 

Best Regards, 

Cloud Industry Team 






