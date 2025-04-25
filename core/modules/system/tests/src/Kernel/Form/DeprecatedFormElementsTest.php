<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\form_test\DeprecatedElementsFormTestObject;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests deprecations of form elements.
 *
 * @group Form
 */
class DeprecatedFormElementsTest extends KernelTestBase {

  /**
   * Tests triggering deprecations on submission of deprecated_elements_form.
   */
  #[IgnoreDeprecations]
  public function testForm(): void {
    $form = new DeprecatedElementsFormTestObject();
    $form_state = new FormState();
    \Drupal::formBuilder()->submitForm($form, $form_state);

    $this->expectDeprecation('The form element with key [pear_container] in class Drupal\form_test\DeprecatedElementsFormTestObject is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/project/drupal/issues/3519300');
    $this->expectDeprecation('The form element with key [pear_container, pear] in class Drupal\form_test\DeprecatedElementsFormTestObject is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/project/drupal/issues/3519300');
  }

}
