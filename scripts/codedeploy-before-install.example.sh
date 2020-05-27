#!/bin/bash

# remove efs symlink - codedeploy will fail with leftover files
rm -f /var/www/app/drupal/sites/default/files
