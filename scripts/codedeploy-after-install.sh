#!/bin/bash

# symlink to efs files directory
ln -s /mnt/efs/drupal/files /var/www/app/drupal/sites/default/files

# permissions
# https://www.drupal.org/forum/support/post-installation/2016-09-22/file-and-directory-permissions-lets-finally-get-this
find /var/www/app/drupal -type d -exec chmod 755 {} +
find /var/www/app/drupal -type f -exec chmod 644 {} +
chmod 444 /var/www/app/drupal/.htaccess
chmod 555 /var/www/app/drupal/sites/default
chmod 400 /var/www/app/drupal/sites/default/settings.php
chmod 755 /var/www/app/drupal/vendor/drush/drush/drush
find /var/www/app/drupal/sites/default/files -type d -exec chmod 755 {} +
find /var/www/app/drupal/sites/default/files -type f -exec chmod 664 {} +
chown -R www-data /var/www/app/drupal

# TODO: does each application server *always* need to rebuild the cache? CodePipeline custom action?
cd /var/www/app/drupal
composer require drush/drush
/var/www/app/drupal/vendor/bin/drush cache:rebuild
