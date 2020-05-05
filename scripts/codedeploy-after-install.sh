#!/bin/bash

# permissions
# https://www.drupal.org/forum/support/post-installation/2016-09-22/file-and-directory-permissions-lets-finally-get-this
find /var/www/drupal -type d -exec chmod 755 {} +
find /var/www/drupal -type f -exec chmod 644 {} +
chmod 444 /var/www/drupal/.htaccess
chmod 555 /var/www/drupal/sites/default
chmod 400 /var/www/drupal/sites/default/settings.php
find /var/www/drupal/sites/default/files -type d -exec chmod 755 {} +
find /var/www/drupal/sites/default/files -type f -exec chmod 664 {} +

# TODO: does each application server *always* need to rebuild the cache? CodePipeline custom action?
export HOME=/var/www/drupal
/var/www/drupal/vendor/bin/drush cache:rebuild
