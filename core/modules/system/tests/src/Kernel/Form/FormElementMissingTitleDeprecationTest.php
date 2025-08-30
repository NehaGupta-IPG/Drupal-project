<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the the deprecation message for empty #title property.
 */
#[Group('form')]
#[Group('legacy')]
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
    $form['missing_title'] = [
      '#type' => 'textfield',
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
   * Ensures that missing #title property triggers deprecation message.
   */
  public function testDeprecationMessage(): void {
    $this->expectDeprecation('Form elements that need titles for accessibility missing title attributes is deprecated in drupal:10.3.0 and is required in drupal:11.0.0. Form element "missing_title" of #type "textfield" from form "form_test_element_missing_title_deprecation" is missing a #title attribute. See https://www.drupal.org/project/drupal/issues/933004');

    /** @var \Drupal\Core\Form\FormBuilder $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->getForm($this);
    $this->assertTrue(isset($form['missing_title']) && !isset($form['missing_title']['#title']));
    $this->render($form);
  }

}
