<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deprecations of form elements.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class DeprecatedFormElementsTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'deprecated_elements_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['pear_container'] = [
      '#type' => 'container',
      '#deprecated' => [
        'class' => self::class,
        'deprecation_version' => 'drupal:11.5.0',
        'removed_version' => 'drupal:12.0.0',
        'change_record' => 'https://www.drupal.org/node/3521264',
      ],
      '#tree' => TRUE,
    ];
    $form['pear_container']['pear'] = [
      '#type' => 'hidden',
      '#value' => 1,
    ];
    $form['pear_container']['another_pear'] = [
      '#type' => 'hidden',
      '#value' => 2,
      '#deprecated' => [
        'class' => self::class,
        'deprecation_version' => 'drupal:11.5.0',
        'removed_version' => 'drupal:12.0.0',
        'change_record' => 'https://www.drupal.org/node/3521264',
      ],
    ];

    $form['orange'] = [
      '#type' => 'textfield',
      '#title' => 'Orange',
      '#default_value' => 'mandarin',
      '#deprecated' => [
        'class' => self::class,
        'deprecation_version' => 'drupal:11.5.0',
        'removed_version' => 'drupal:12.0.0',
        'change_record' => 'https://www.drupal.org/node/3521264',
      ],
    ];

    $form['apple_settings'] = [
      '#type' => 'container',
      '#deprecated' => [
        'class' => self::class,
        'deprecation_version' => 'drupal:11.5.0',
        'removed_version' => 'drupal:12.0.0',
        'change_record' => 'https://www.drupal.org/node/3521264',
      ],
      '#tree' => TRUE,
    ];

    $form['apple_settings']['apple'] = [
      '#type' => 'hidden',
      '#value' => 'honey crisp',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state):void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->assertEquals(['pear' => 1, 'another_pear' => 2], $form_state->getValue('pear_container'));
    $this->assertEquals(1, $form_state->getValue(['pear_container', 'pear']));
    $this->assertEquals(2, $form_state->getValue(['pear_container', 'another_pear']));
    $this->assertEquals('mandarin', $form_state->getValue('orange'));
    $subform_state = SubformState::createForSubform($form['apple_settings'], $form, $form_state);
    $this->assertEquals('honey crisp', $subform_state->getValue('apple'));
  }

  /**
   * Tests triggering deprecations on submission of deprecated_elements_form.
   */
  #[IgnoreDeprecations]
  public function testForm(): void {
    $form_state = new FormState();
    \Drupal::formBuilder()->submitForm($this, $form_state);

    $this->expectUserDeprecationMessage('The form element with key [pear_container] in class Drupal\Tests\system\Kernel\Form\DeprecatedFormElementsTest is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/node/3521264');
    $this->expectUserDeprecationMessage('The form element with key [pear_container, pear] in class Drupal\Tests\system\Kernel\Form\DeprecatedFormElementsTest is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/node/3521264');
    $this->expectUserDeprecationMessage('The form element with key [orange] in class Drupal\Tests\system\Kernel\Form\DeprecatedFormElementsTest is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/node/3521264');
    $this->expectUserDeprecationMessage('The form element with key [apple_settings, apple] in class Drupal\Tests\system\Kernel\Form\DeprecatedFormElementsTest is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/node/3521264');
  }

}
