<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Render\Element\Checkbox;
use Drupal\Core\Render\Element\Container;
use Drupal\Core\Render\Element\Details;
use Drupal\Core\Render\Element\Fieldset;
use Drupal\Core\Render\Element\Textarea;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that generic grouping preserves element-specific callbacks.
 */
#[Group('Render')]
class GroupCallbacksTest extends UnitTestCase {

  /**
   * Tests that element callbacks remain callable and retain AJAX processing.
   */
  #[DataProvider('providerElementCallbacks')]
  public function testElementCallbacks(string $class, array $process, array $pre_render): void {
    $element = new $class([], 'test', []);
    $info = $element->getInfo();
    foreach (['#process' => $process, '#pre_render' => $pre_render] as $property => $methods) {
      foreach ($methods as $method) {
        $this->assertContains([$class, $method], $info[$property]);
      }
      foreach ($info[$property] ?? [] as $callback) {
        $this->assertIsCallable($callback);
      }
    }
  }

  /**
   * Provides the callbacks that grouping must not remove.
   */
  public static function providerElementCallbacks(): array {
    return [
      'datetime' => [Datetime::class, ['processDatetime', 'processAjaxForm'], []],
      'checkbox' => [Checkbox::class, ['processCheckbox', 'processAjaxForm'], ['preRenderCheckbox']],
      'container' => [Container::class, ['processContainer'], ['preRenderContainer']],
      'details' => [Details::class, ['processAjaxForm'], ['preRenderDetails']],
      'fieldset' => [Fieldset::class, ['processAjaxForm'], []],
      'textarea' => [Textarea::class, ['processAjaxForm'], []],
      'textfield' => [
        Textfield::class,
        ['processAutocomplete', 'processAjaxForm', 'processPattern'],
        ['preRenderTextfield'],
      ],
    ];
  }

}
