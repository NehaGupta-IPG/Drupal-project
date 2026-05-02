<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the the deprecation message for empty #title attribute.
 */
#[Group('form')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class FormElementMissingTitleDeprecationTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_element_missing_title_deprecation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // #title is completely missing.
    // Should cause deprecation message.
    $form['textfield_missing_title'] = [
      '#type' => 'textfield',
    ];
    // #title is the empty string ('').
    // Should cause deprecation message.
    $form['textfield_title_is_empty_string'] = [
      '#title' => '',
      '#type' => 'textfield',
    ];
    // #title is one or more spaces (' ').
    // Should cause deprecation message.
    $form['textfield_title_is_space'] = [
      '#title' => ' ',
      '#type' => 'textfield',
    ];
    // #title is FALSE.
    // Should cause deprecation message.
    $form['textfield_title_is_false'] = [
      '#title' => FALSE,
      '#type' => 'textfield',
    ];
    // #title is completely missing. #input is NULL.
    // Should not cause deprecation message.
    $form['textfield_missing_title_null_input'] = [
      '#type' => 'textfield',
      '#input' => NULL,
    ];

    // Buttons.
    foreach (['button', 'submit'] as $type) {
      // Missing #value.
      // Should cause deprecation message.
      $form["{$type}_missing_value"] = [
        '#type' => $type,
        '#title' => "{$type}_missing_value",
      ];
      // Missing #title.
      // Should not cause deprecation message.
      $form["{$type}_missing_title"] = [
        '#type' => $type,
        '#value' => "{$type}_missing_title",
      ];
    }

    // Fieldset missing title.
    // Should cause deprecation.
    $form['fieldset'] = [
      '#type' => 'fieldset',
      'child' => [
        '#type' => 'checkbox',
        '#title' => 'child',
      ],
    ];

    // Skipped types.
    // Should not cause deprecation message.
    $types_to_skip = [
      'field_ui_table',
      'table',
      'tableselect',
      'hidden',
      'token',
      'value',
      'item',
      'datelist',
      'password_confirm',
    ];

    foreach ($types_to_skip as $type) {
      $form["{$type}_missing_title"] = [
        '#type' => $type,
      ];
    }

    // #title is completely missing. aria-label attribute is set.
    // Should not cause deprecation message.
    $form['textfield_missing_title_with_aria_label'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'aria-label' => 'aria label',
      ],
    ];

    // #title is completely missing. aria-labelledby attribute is set.
    // Should not cause deprecation message.
    $form['textfield_missing_title_with_aria_labelledby'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'aria-labelledby' => 'aria labelledby',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Ensures that missing #title attribute triggers deprecation message.
   */
  public function testDeprecationMessage(): void {
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "textfield_missing_title" of #type "textfield" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "textfield_title_is_empty_string" of #type "textfield" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "textfield_title_is_space" of #type "textfield" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "textfield_title_is_false" of #type "textfield" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "button_missing_value" of #type "button" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "submit_missing_value" of #type "submit" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');
    $this->expectUserDeprecationMessage('Support for form elements with empty #title attributes is deprecated in drupal:11.4.0 and is removed in drupal:13.0.0. Form element "fieldset" of #type "fieldset" from form "form_test_element_missing_title_deprecation" has an empty #title attribute. See https://www.drupal.org/project/drupal/issues/933004');

    $this->container->get('form_builder')->getForm($this);
  }

}
