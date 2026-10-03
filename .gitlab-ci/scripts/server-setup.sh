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
if [ "$CLI_OPCACHE_FILE_CACHE" = "1" ]; then
  # Share compiled scripts between the run-tests.sh/PHPUnit CLI processes.
  # php-cli.ini only: the web server SAPIs read php.ini and are unaffected.
  mkdir -p /tmp/opcache-cli && chown www-data:www-data /tmp/opcache-cli
  printf '\nopcache.file_cache=/tmp/opcache-cli\n' >> "$(php -r 'echo PHP_CONFIG_FILE_PATH;')/php-cli.ini"
fi
$CI_PROJECT_DIR/.gitlab-ci/scripts/ci-diag.sh start
