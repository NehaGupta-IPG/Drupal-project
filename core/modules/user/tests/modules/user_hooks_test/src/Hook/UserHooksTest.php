<?php

declare(strict_types=1);

namespace Drupal\user_hooks_test\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Contains hook implementations.
 */
class UserHooksTest {

  /**
   * Alters the username.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $name
   *   The username that is displayed for a user.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The object on which the operation is being performed.
   */
  #[Hook('user_format_name_alter')]
  public function userFormatNameAlter(&$name, AccountInterface $account): void {
    if (\Drupal::keyValue('user_hooks_test')->get('user_format_name_alter', FALSE)) {
      if (\Drupal::keyValue('user_hooks_test')->get('user_format_name_alter_safe', FALSE)) {
        $name = new FormattableMarkup('<em>@uid</em>', ['@uid' => $account->id()]);
      }
      else {
        $name = '<em>' . $account->id() . '</em>';
      }
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(&$entity_types) : void {
    if (\Drupal::state()->get('user_hooks_test_route_alter', FALSE)) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
      $user = $entity_types['user'];
      $user->setLinkTemplate('canonical', '/route_alter/{user}');
      $user->setLinkTemplate('edit-form', '/route_alter/{user}/edit');
      $user->setLinkTemplate('cancel-form', '/route_alter/{user}/cancel');
    }
  }

}
