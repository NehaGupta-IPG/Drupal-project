<?php

namespace Drupal\Core\Extension;

/**
 * Exception class to throw when modules are missing on install.
 *
 * @see \Drupal\Core\Extension\ModuleInstaller::install()
 *
 * @ingroup extension_api
 */
class MissingDependencyException extends \Exception {}
