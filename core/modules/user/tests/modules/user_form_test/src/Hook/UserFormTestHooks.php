<?php

declare(strict_types=1);

namespace Drupal\user_form_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for user_form_test.
 */
class UserFormTestHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for user_cancel_form().
   */
  #[Hook('form_user_cancel_form_alter')]
  public function formUserCancelFormAlter(&$form, &$form_state) : void {
    $form['user_cancel_confirm']['#default_value'] = FALSE;
    $form['access']['#value'] = \Drupal::currentUser()->hasPermission('cancel other accounts');
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(&$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'user' && \Drupal::keyvalue('user_form_test')->get('user_form_test_constraint_roles_edit')) {
      $fields['roles']->addConstraint('FieldWidgetConstraint');
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(&$entity_types) : void {
    if (\Drupal::state()->get('user_form_test_route_alter', FALSE)) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
      $user = $entity_types['user'];
      $user->setLinkTemplate('canonical', '/route_alter/{user}');
      $user->setLinkTemplate('edit-form', '/route_alter/{user}/edit');
      $user->setLinkTemplate('cancel-form', '/route_alter/{user}/cancel');
    }
  }

}
