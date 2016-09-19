## Wordpress VOD Repository for Moodle 3.1+

### INTRODUCTION
The Wordpress VOD Repository allows you to pick a video files from a remote Wordpress media.

### REQUIREMENTS
* Moodle 3.x
* Wordpress 4.x + several plugins, see: [More info](https://wordpress-solutions-for-education.zeef.com/nadav.kavalerchik#block_96909_video)

### INSTALLATION
- Place the "wpvod" directory within your_moodle_install/repository/
- Visit the admin notifications page and complete the installation
- Enable the repository
- Set the proper URL for your wordpress system
- Set a shared secret
- Done!

### USAGE
- Whenever you use the filepicker and an external video file is a valid choice you are able to pick video files/stream urls 
from the connected Wordpress server. The result is a link to the video item.

### Todo
* Add global setting for a wordpress URL
* Add shared secret (also enable it on the wp restful apis)