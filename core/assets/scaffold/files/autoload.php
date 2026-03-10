<?php

return (function() {
  $autoloader = require __DIR__ . '/autoload_composer.php';
  return \Drupal\Core\Boot\PreBootstrap::init($autoloader);
})();
