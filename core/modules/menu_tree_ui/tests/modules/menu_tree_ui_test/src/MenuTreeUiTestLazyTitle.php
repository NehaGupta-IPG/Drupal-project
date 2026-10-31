<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui_test;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Builds an operation title through a lazy builder.
 */
final class MenuTreeUiTestLazyTitle implements TrustedCallbackInterface {

  /**
   * Builds the title: an element a test can look for.
   */
  public function build(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['data-menu-tree-ui-test-lazy' => ''],
      '#value' => 'Lazy',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

}
