# Git File System for Static Export

Provides a Git-based remote file system to store data exported by Static Export.

## INTRODUCTION ##
This module allows storing all data from Static Export in a remote Git repository. To achieve that, it offers:
  1) A "static-git" stream wrapper that saves data to a local directory, which is, in fact, a cloned repository.
  2) An EventSubscriber that listen to Static Export events and executes git commands on that repository. It commits
     and pushes changes from FileCollectionGroups at once, making only one commit per FileCollectionGroup, regardless
     of the number of files they contain.

## REQUIREMENTS ##
* Static Export module
* Git locally installed and executable
* A cloned repository with proper write permissions for the user running your web-server (usually, www-data)
* Push permission, on that repository, for the user running your web-server (usually, www-data)

The last one can be achieved by different ways, for example:
* Create a directory at /var/www/.ssh, owned by www-data and add "700" permissions
* Add required keys to the above directory:

```bash
# ls -al /var/www/.ssh/
total 20
drwx------ 2 www-data www-data 4096 feb 20 01:48 .
drwxr-xr-x 7 root     root     4096 feb 20 01:43 ..
-rw------- 1 www-data www-data 3243 feb 20 01:48 id_rsa
-rw------- 1 www-data www-data  743 feb 20 01:48 id_rsa.pub
-rw-r--r-- 1 www-data www-data 1326 feb 20 01:50 known_hosts
```

To ensure `www-data` user is able to push to the remote repository, you can use `sudo -HE -u www-data git push`

## INSTALLATION ##
Run `composer require drupal/static_export_stream_wrapper_git`.

## CONFIGURATION ##
Configuration available at /admin/config/static/export/stream-wrappers/static-git
