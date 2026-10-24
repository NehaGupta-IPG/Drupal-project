<?php

declare(strict_types=1);

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Base class for layout builder block access checks.
 */
abstract class LayoutBuilderBlockAccessBase implements AccessInterface {

  /**
   * Checks base route access requirements.
   */
  protected function baseAccess(
    SectionStorageInterface $section_storage,
    AccountInterface $account,
    string $section_operation,
  ): AccessResultInterface {
    $access = $section_storage->access($section_operation, $account, TRUE);

    // Check for the global permission unless the section storage checks
    // permissions itself.
    if (!$section_storage->getPluginDefinition()->get('handles_permission_check')) {
      $access = $access->andIf(AccessResult::allowedIfHasPermission($account, 'configure any layout'));
    }

    if ($access instanceof RefinableCacheableDependencyInterface) {
      $access->addCacheableDependency($section_storage);
    }
    return $access;
  }

}
