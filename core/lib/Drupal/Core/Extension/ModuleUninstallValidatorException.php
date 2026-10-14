<?php

namespace Drupal\Core\Extension;

/**
 * Defines an exception thrown when uninstalling a module that did not validate.
 *
 * @ingroup extension_api
 */
class ModuleUninstallValidatorException extends \InvalidArgumentException {}
