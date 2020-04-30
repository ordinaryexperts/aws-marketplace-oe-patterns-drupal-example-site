#!/bin/bash

pushd .

cd $(dirname "$0")
cd drupal

./vendor/bin/drush cr

popd
