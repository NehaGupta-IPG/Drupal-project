<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests deprecations of form elements.
 */
#[Group('Form')]
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
        'deprecation_version' => 'drupal:11.3.0',
        'removed_version' => 'drupal:12.0.0',
        'change_record' => 'https://www.drupal.org/node/3521264',
      ],
      '#tree' => TRUE,
    ];
    $form['pear_container']['pear'] = [
      '#type' => 'hidden',
      '#value' => 1,
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
    $form_state->getValue('pear_container');
    $form_state->getValue(['pear_container', 'pear']);
  }

  /**
   * Tests triggering deprecations on submission of deprecated_elements_form.
   */
  #[IgnoreDeprecations]
  public function testForm(): void {
    $form_state = new FormState();
    \Drupal::formBuilder()->submitForm($this, $form_state);

    $this->expectDeprecation('The form element with key [pear_container] in class Drupal\Tests\system\Kernel\Form\DeprecatedFormElementsTest is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/node/3521264');
    $this->expectDeprecation('The form element with key [pear_container, pear] in class Drupal\Tests\system\Kernel\Form\DeprecatedFormElementsTest is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/node/3521264');
  }

}
