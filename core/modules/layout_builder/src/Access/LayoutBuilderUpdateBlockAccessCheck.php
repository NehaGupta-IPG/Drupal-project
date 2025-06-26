<?php

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access check for the Layout Builder defaults.
 *
 * @ingroup layout_builder_access
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutBuilderUpdateBlockAccessCheck extends LayoutBuilderBlockAccessBase {

  /**
   * Constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layoutTempstoreRepository
   *   The Layout Builder tempstore repository service.
   */
  public function __construct(
    protected readonly LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
  ) {}

  /**
   * Checks routing access to the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string $delta
   *   The block delta.
   * @param string $uuid
   *   The block UUID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage, string $delta, string $uuid, AccountInterface $account, Route $route): AccessResultInterface {
    $access = $this->baseAccess($section_storage, $account, $route->getRequirement('_layout_builder_update_block_access'));
    if ($access->isAllowed()) {
      // Load the current state of sections if during edition.
      $section_storage = $this->layoutTempstoreRepository->get($section_storage);
      $section = $section_storage->getSection($delta);
      $component = $section->getComponent($uuid);
      $plugin = $component->getPlugin();
      if ($plugin instanceof InlineBlock) {
        $access = $access->andIf($plugin->blockOperationAccess($account, 'edit'));
      }
    }

    return $access;
  }

}
