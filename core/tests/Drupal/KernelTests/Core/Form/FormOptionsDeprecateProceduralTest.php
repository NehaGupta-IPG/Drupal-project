<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Form;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests deprecation of form_select_options() and form_get_options().
 *
 * @covers \Drupal\Core\Form\FormBuilder::getCache
 *
 * @group Form
 * @group legacy
 */
class FormOptionsDeprecateProceduralTest extends KernelTestBase {

  /**
   * Tests the form cache with a logged-in user.
   */
  public function testDeprecateGetOptions(): void {

    $this->expectDeprecation('form_get_options() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement. See https://www.drupal.org/node/3412600');
    $element = [
      '#type' => 'select',
      '#options' => ['one' => 'one', 'two' => 'two', 'three' => 'three', 'four' => '<strong>four</strong>'],
    ];
    form_get_options($element, 'one');
  }

  /**
   * Tests the form cache without a logged-in user.
   */
  public function testDeprecateSelectOptions(): void {
    $this->expectDeprecation('form_select_options() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use \Drupal\Core\Form\FormOptionsHelper::formSelectOptions(). See https://www.drupal.org/node/3412600');

    $element = [
      '#type' => 'select',
      '#options' => ['one' => 'one', 'two' => 'two', 'three' => 'three', 'four' => '<strong>four</strong>'],
    ];
    form_select_options($element);
  }

}
