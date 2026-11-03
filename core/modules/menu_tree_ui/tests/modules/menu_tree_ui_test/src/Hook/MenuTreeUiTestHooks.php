<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui_test\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Session\AccountInterface;
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

  /**
   * The State key that forbids menu updates and attaches the foreign library.
   *
   * A page refused by this flag carries an asset a gate must keep out.
   */
  public const FORBID_UPDATE_KEY = 'menu_tree_ui_test.forbid_update';

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

  /**
   * Implements hook_menu_access().
   */
  #[Hook('menu_access')]
  public function menuAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation === 'update' && $this->state->get(self::FORBID_UPDATE_KEY, FALSE)) {
      return AccessResult::forbidden()->setCacheMaxAge(0);
    }
    return AccessResult::neutral();
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->state->get(self::FORBID_UPDATE_KEY, FALSE)) {
      $attachments['#attached']['library'][] = 'menu_tree_ui_test/foreign';
      $attachments['#cache']['max-age'] = 0;
    }
  }

}
