#!/bin/bash

pushd .

cd $(dirname "$0")

chgrp -R www-data sites/default/files
chmod -R 775 sites/default/files

./vendor/bin/drush cr

popd
