<?php

declare(strict_types=1);

namespace Drupal\user_form_multiple_test\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for user_form_multiple_test.
 */
class UserFormMultipleTestHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for the registration form.
   */
  #[Hook('form_user_register_form_alter')]
  public function formUserRegisterFormAlter(&$form, FormStateInterface $form_state): void {
    // Change the value of 'roles' to null
    unset($form['account']['roles']);
  }

}
