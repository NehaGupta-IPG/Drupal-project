<?php

declare(strict_types=1);

namespace Drupal\Core\Hook\Attribute;

/**
 * Marks a hook as deprecated.
 *
 * The effect of this attribute is independent from the specific class or method
 * on which it is placed.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class DeprecatedHook implements HookAttributeInterface {

  /**
   * Constructs a DeprecatedHook object.
   *
   * @param string $hook
   *   The hook name from which to remove the target implementation.
   * @param string $provider
   *   The provider of this hook , module name or core.
   * @param string $version_deprecated
   *   The version the hook was deprecated in.
   * @param string $version_removed
   *   The version the hook will be removed.
   * @param string $change_record
   *   The change record for this deprecation.
   */
  public function __construct(
    public readonly string $hook,
    public readonly string $provider,
    public readonly string $version_deprecated,
    public readonly string $version_removed,
    public readonly string $change_record,
  ) {}

}
