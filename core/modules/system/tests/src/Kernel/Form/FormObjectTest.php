<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\form_test\FormTestObject;
use Drupal\KernelTests\ConfigFormTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests building a form from an object.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormObjectTest extends ConfigFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->form = new FormTestObject($this->container->get('config.factory'), $this->container->get('config.typed'));
    $this->values = [
      'bananas' => [
        '#value' => $this->randomString(10),
        '#config_name' => 'form_test.object',
        '#config_key' => 'bananas',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[IgnoreDeprecations]
  public function testConfigForm(): void {
    parent::testConfigForm();
    $this->expectDeprecation('The form element with key [pear_container] in class Drupal\form_test\FormTestObject is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/project/drupal/issues/3519300');
    $this->expectDeprecation('The form element with key [pear_container, pear] in class Drupal\form_test\FormTestObject is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See https://www.drupal.org/project/drupal/issues/3519300');
  }

}
