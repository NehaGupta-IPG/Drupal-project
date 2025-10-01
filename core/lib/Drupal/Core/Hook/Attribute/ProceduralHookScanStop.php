<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

@trigger_error('The ' . __NAMESPACE__ . '\ProceduralHookScanStop is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3549644', E_USER_DEPRECATED);

/**
 * Defines a ProceduralHookScanStop attribute object.
 *
 * This allows contrib and core to mark when a file has no more
 * procedural hooks to be gathered. Any procedural hooks in the file should
 * be placed before the function with this attribute. This includes all hooks
 * that can be converted to object oriented hooks and also includes:
 * - hook_hook_info()
 * - hook_module_implements_alter()
 * - hook_requirements()
 * - hook_preprocess()
 * - hook_preprocess_HOOK()
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no
 * replacement.
 *
 * @see https://www.drupal.org/node/3549644
 */
#[\Attribute(\Attribute::TARGET_FUNCTION)]
class ProceduralHookScanStop {

}
