<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;

/**
 * Hook implementations for menu_tree_ui_test.
 */
final class MenuTreeUiTestHooks {

  /**
   * The State key that adds an operation with a lazily built title when set.
   */
  public const LAZY_OPERATION_KEY = 'menu_tree_ui_test.lazy_operation';

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * Implements hook_menu_tree_ui_row_operations_alter().
   */
  #[Hook('menu_tree_ui_row_operations_alter')]
  public function rowOperationsAlter(array &$operations, MenuLinkTreeElement $element): void {
    if (!$this->state->get(self::LAZY_OPERATION_KEY, FALSE)) {
      return;
    }
    $operations['lazy'] = [
      'title' => [
        '#lazy_builder' => ['menu_tree_ui_test.lazy_title:build', []],
        '#create_placeholder' => TRUE,
      ],
      'url' => Url::fromRoute('<front>'),
      'weight' => 10,
    ];
  }

}
