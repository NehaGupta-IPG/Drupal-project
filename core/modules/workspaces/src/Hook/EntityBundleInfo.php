<?php

declare(strict_types=1);
namespace Drupal\workspaces\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\workspaces\Provider\WorkspaceProviderCollector;

/**
 * Provide entity bundle info hooks.
 */
final class EntityBundleInfo {

  public function __construct(
    private readonly WorkspaceProviderCollector $collector
  ) {}

  /**
   * Implements hook_entity_bundle_info().
   */
  #[Hook('entity_bundle_info')]
  public function entityBundleInfo(): array {
    $bundles = [];
    foreach ($this->collector->getProviders() as $id => $provider) {
      $bundles[$id] = [
        'label' => $id,
      ];
    }
    return $bundles;
  }

}
