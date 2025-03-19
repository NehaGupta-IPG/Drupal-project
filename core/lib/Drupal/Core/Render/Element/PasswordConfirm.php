<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;

/**
 * Provides a form element for double-input of passwords.
 *
 * Formats as a pair of password fields, which do not validate unless the two
 * entered passwords match.
 *
 * Properties:
 * - #size: The size of the input element in characters.
 * - #pass2_attributes: An array of attributes to apply to the
 *   confirm_password field.
 *
 * Usage example:
 * @code
 * $form['pass'] = [
 *   '#type' => 'password_confirm',
 *   '#title' => $this->t('Password'),
 *   '#size' => 25,
 *   '#attributes' => ['class' => ['password-field']],
 *   '#pass2_attributes' => ['class' => ['password-confirm']],
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Password
 */
#[FormElement('password_confirm')]
class PasswordConfirm extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#markup' => '',
      '#process' => [
        [static::class, 'processPasswordConfirm'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      $element += ['#default_value' => []];
      return $element['#default_value'] + ['pass1' => '', 'pass2' => ''];
    }
    $value = ['pass1' => '', 'pass2' => ''];
    // Throw out all invalid array keys; we only allow pass1 and pass2.
    foreach ($value as $allowed_key => $default) {
      // These should be strings, but allow other scalars since they might be
      // valid input in programmatic form submissions. Any nested array values
      // are ignored.
      if (isset($input[$allowed_key]) && is_scalar($input[$allowed_key])) {
        $value[$allowed_key] = (string) $input[$allowed_key];
      }
    }
    return $value;
  }

  /**
   * Combine password element attribute arrays.
   *
   * Normalize the autocomplete attribute to remove invalid cases.
   *
   * @param mixed[] $default_attributes
   *   Set of default attributes for a password element.
   * @param mixed|null $passed_attributes
   *   Attributes passed in via #attributes or #pass2_attributes.
   *
   * @return mixed[]
   *   Combined attribute array.
   */
  protected static function combineAttributes(array $default_attributes = [], $passed_attributes = NULL): array {
    $combined_attributes = is_array($passed_attributes) ?
      array_merge_recursive($passed_attributes, $default_attributes) :
      $default_attributes;

    // Since autocomplete="off" can't be combined with any other hints,
    // we normalize it to a single "off" hint.
    if (!empty($combined_attributes['autocomplete'])) {
      if (
        is_array($combined_attributes['autocomplete']) &&
        in_array('off', $combined_attributes['autocomplete'])
      ) {
        $combined_attributes['autocomplete'] = ['off'];
      }
      elseif (
        is_string($combined_attributes['autocomplete']) &&
        in_array('off', explode(' ', $combined_attributes['autocomplete']))
      ) {
        $combined_attributes['autocomplete'] = ['off'];
      }
    }
    return $combined_attributes;
  }

  /**
   * Expand a password_confirm field into two text boxes.
   */
  public static function processPasswordConfirm(&$element, FormStateInterface $form_state, &$complete_form) {
    $pass1_attributes = [
      'class' => ['password-field', 'js-password-field'],
      'autocomplete' => ['new-password'],
    ];
    $pass1_combined_attributes = static::combineAttributes(
      $pass1_attributes,
      $element['#attributes'],
    );
    $element['pass1'] = [
      '#type' => 'password',
      '#title' => t('Password'),
      '#value' => empty($element['#value']) ? NULL : $element['#value']['pass1'],
      '#required' => $element['#required'],
      '#attributes' => $pass1_combined_attributes,
      '#error_no_message' => TRUE,
    ];

    $pass2_attributes = [
      'class' => ['password-confirm', 'js-password-confirm'],
      'autocomplete' => ['new-password'],
    ];
    $element['pass2'] = [
      '#type' => 'password',
      '#title' => t('Confirm password'),
      '#value' => empty($element['#value']) ? NULL : $element['#value']['pass2'],
      '#required' => $element['#required'],
      '#attributes' => $pass2_attributes,
      '#error_no_message' => TRUE,
    ];
    if (isset($element['#pass2_attributes'])) {
      $element['pass2']['#attributes'] = static::combineAttributes(
        $pass2_attributes,
        $element['#pass2_attributes'],
      );
    }
    $element['#element_validate'] = [[static::class, 'validatePasswordConfirm']];
    $element['#tree'] = TRUE;

    if (isset($element['#size'])) {
      $element['pass1']['#size'] = $element['pass2']['#size'] = $element['#size'];
    }

    return $element;
  }

  /**
   * Validates a password_confirm element.
   */
  public static function validatePasswordConfirm(&$element, FormStateInterface $form_state, &$complete_form) {
    $pass1 = trim($element['pass1']['#value']);
    $pass2 = trim($element['pass2']['#value']);
    if (strlen($pass1) > 0 || strlen($pass2) > 0) {
      if (strcmp($pass1, $pass2)) {
        $form_state->setError($element, t('The specified passwords do not match.'));
      }
    }
    elseif ($element['#required'] && $form_state->getUserInput()) {
      $form_state->setError($element, t('Password field is required.'));
    }

    // Password field must be converted from a two-element array into a single
    // string regardless of validation results.
    $form_state->setValueForElement($element['pass1'], NULL);
    $form_state->setValueForElement($element['pass2'], NULL);
    $form_state->setValueForElement($element, $pass1);

    return $element;
  }

}
