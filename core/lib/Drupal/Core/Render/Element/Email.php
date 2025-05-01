<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;

/**
 * Provides a form input element for entering an email address.
 *
 * Properties:
 * - #default_value: An RFC-compliant email address.
 * - #size: The size of the input element in characters.
 * - #pattern: A string for the native HTML5 pattern attribute.
 * - #multiple: (optional) If TRUE, the element accepts multiple email addresses
 *   separated by commas. Defaults to FALSE.
 * - #maxlength: (optional) The maximum length of the element in characters.
 *   Defaults to 254 for one email according to RFC 3696 and Erratum 1690.
 *
 * Example usage:
 * @code
 * $form['email'] = [
 *   '#type' => 'email',
 *   '#title' => $this->t('Email'),
 * ];
 * @endcode
 *
 * Element might use a native HTML5 pattern attribute:
 * @code
 *  $form['email'] = [
 *    '#type' => 'email',
 *    '#title' => $this->t('Email'),
 *    '#pattern' => '*@example.com',
 *  ];
 * @endcode
 *
 * Element might use a native HTML5 multiple attribute:
 * @code
 *  $form['emails'] = [
 *    '#type' => 'email',
 *    '#title' => $this->t('Emails'),
 *    '#multiple' => TRUE,
 *    '#maxlength' => 1024,
 *  ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 */
#[FormElement('email')]
class Email extends FormElementBase {

  /**
   * Defines the max length for an email address.
   *
   * The maximum length of an email address is 254 characters. RFC 3696
   * specifies a total length of 320 characters, but mentions that
   * addresses longer than 256 characters are not normally useful. Erratum
   * 1690 was then released which corrected this value to 254 characters.
   *
   * @see http://tools.ietf.org/html/rfc3696#section-3
   * @see http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
   */
  const EMAIL_MAX_LENGTH = 254;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => self::EMAIL_MAX_LENGTH,
      '#multiple' => FALSE,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [static::class, 'processAutocomplete'],
        [static::class, 'processAjaxForm'],
        [static::class, 'processPattern'],
      ],
      '#element_validate' => [
        [static::class, 'validateEmail'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderEmail'],
      ],
      '#theme' => 'input__email',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Form element validation handler for #type 'email'.
   *
   * Note that #maxlength and #required is validated by _form_validate()
   * already.
   */
  public static function validateEmail(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = trim($element['#value']);

    // Skip validation if the value is empty.
    if ($value === '') {
      return;
    }

    // Create an array of email addresses so each one can be validated
    // individually. Email addresses can be only comma-separated.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/multiple#email_input
    $multiple = $element['#multiple'];
    $emails = $multiple
      ? array_map('trim', explode(',', $value))
      : [$value];

    // Display an error when an email address is empty.
    if (in_array('', $emails, TRUE)) {
      $form_state->setError($element, t('All email addresses must be non-empty.'));
      return;
    }

    // Validate each email address.
    /** @var \Drupal\Component\Utility\EmailValidator $validator */
    $validator = \Drupal::service('email.validator');
    $invalid_emails = [];
    foreach ($emails as $email) {
      if (empty($email)) {
        continue;
      }
      if (!$validator->isValid($email)) {
        $invalid_emails[] = $email;
      }
    }

    // Set trimmed and validated email address(es).
    $form_state->setValueForElement($element, implode(',', $emails));

    if ($invalid_emails) {
      $multiple_error_suffix = $multiple ? ', and separate the addresses with a comma.' : '.';
      $form_state->setError($element, new PluralTranslatableMarkup(
        count($invalid_emails),
        'The email address %mails is not valid. Use the format user@example.com' . $multiple_error_suffix,
        'The email addresses %mails are not valid. Use the format user@example.com' . $multiple_error_suffix,
        ['%mails' => implode(', ', $invalid_emails)],
      ));
    }
  }

  /**
   * Prepares a #type 'email' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function preRenderEmail($element) {
    $element['#attributes']['type'] = 'email';
    Element::setAttributes($element, [
      'id',
      'name',
      'value',
      'size',
      'maxlength',
      'placeholder',
      'multiple',
    ]);
    static::setAttributes($element, ['form-email']);
    return $element;
  }

}
