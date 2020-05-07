#!/bin/bash

# permissions
# https://www.drupal.org/forum/support/post-installation/2016-09-22/file-and-directory-permissions-lets-finally-get-this
mkdir -p /var/www/drupal/sites/default/files
find /var/www/drupal -type d -exec chmod 755 {} +
find /var/www/drupal -type f -exec chmod 644 {} +
chmod 444 /var/www/drupal/.htaccess
chmod 555 /var/www/drupal/sites/default
chmod 400 /var/www/drupal/sites/default/settings.php
chmod 755 /var/www/drupal/vendor/drush/drush/drush
find /var/www/drupal/sites/default/files -type d -exec chmod 755 {} +
find /var/www/drupal/sites/default/files -type f -exec chmod 664 {} +
chown -R www-data /var/www/drupal

source /etc/profile.d/oe-patterns-drupal.sh
env > /tmp/env.txt

# TODO: does each application server *always* need to rebuild the cache? CodePipeline custom action?
export HOME=/var/www/drupal
/var/www/drupal/vendor/bin/drush cache:rebuild
