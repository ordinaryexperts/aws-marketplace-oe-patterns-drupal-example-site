#!/bin/bash

# remove efs symlink - codedeploy will fail with leftover files
rm /var/www/drupal/sites/default/files
