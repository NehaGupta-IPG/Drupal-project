<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a form element for entering a password.
 *
 * Password form element will mask user input which can be unmasked by the user.
 *
 * Usage example:
 * @code
 * $form['pass'] = [
 *   '#type' => 'password_unmask',
 *   '#title' => t('Password'),
 *   '#size' => 25,
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Password
 */
#[FormElement('password_unmask')]
class PasswordUnmask extends Password {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#process'][] = [static::class, 'processUnmask'];

    return $info;
  }

  /**
   * Process callback for #type password_unmask elements.
   *
   * Adds the attributes needed for the password strength bar and the password
   * unmask button.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processUnmask(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#attached']['library'][] = 'core/drupal.password-unmask';
    $element['#attached']['library'][] = 'user/drupal.user.css';
    $element['#attributes']['class'][] = 'password-field';
    $element['#attributes']['class'][] = 'js-password-field';
    $element['#attributes']['data-drupal-password-unmask'] = TRUE;

    return $element;
  }

}
