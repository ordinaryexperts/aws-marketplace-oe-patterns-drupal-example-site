#!/bin/bash

cd /var/www/drupal

# TODO: does each application server *always* need to rebuild the cache? CodePipeline custom action?
./vendor/bin/drush cache:rebuild
