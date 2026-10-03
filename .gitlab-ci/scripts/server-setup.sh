#!/bin/bash

ln -s $CI_PROJECT_DIR /var/www/html/subdirectory
if command -v unitd >/dev/null; then
  sudo unit-start $CI_PROJECT_DIR/.gitlab-ci/unit.json
else
  sudo service apache2 start
fi
mkdir -p ./sites/simpletest ./sites/default/files ./build/logs/junit /var/www/.composer
chown -R www-data:www-data ./sites ./build/logs/junit ./vendor /var/www/
sudo -u www-data git config --global --add safe.directory $CI_PROJECT_DIR
$CI_PROJECT_DIR/.gitlab-ci/scripts/ci-diag.sh start
