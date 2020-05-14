#!/bin/bash

# remove efs symlink - codedeploy will fail with leftover files
rm -f /var/www/drupal/sites/default/files
