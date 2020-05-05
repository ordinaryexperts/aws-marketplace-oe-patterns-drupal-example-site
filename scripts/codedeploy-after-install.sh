#!/bin/bash

cd /var/www/drupal

# permissions
# https://www.drupal.org/forum/support/post-installation/2016-09-22/file-and-directory-permissions-lets-finally-get-this
find /var/www/drupal -type d -exec chmod 755 {} +
find /var/www/drupal -type f -exec chmod 644 {} +
chmod /var/www/drupal/.htaccess 444
chmod /var/www/drupal/sites/default 555
chmod /var/www/drupal/sites/default/settings.php 400
find /var/www/drupal/sites/default/files -type d -exec chmod 755 {} +
find /var/www/drupal/sites/default/files -type f -exec chmod 664 {} +

# TODO: does each application server *always* need to rebuild the cache? CodePipeline custom action?
./vendor/bin/drush cache:rebuild
