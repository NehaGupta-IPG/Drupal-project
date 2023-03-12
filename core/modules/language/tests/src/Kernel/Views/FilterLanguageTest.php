<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel\Views;

use Drupal\Core\Language\LanguageInterface;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the filter language handler.
 *
 * @see \Drupal\language\Plugin\views\filter\Language
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class FilterLanguageTest extends LanguageTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Tests the language filter.
   */
  public function testFilter(): void {
    $view = Views::getView('test_view');
    foreach (['en' => 'John', 'xx-lolspeak' => 'George'] as $langcode => $name) {
      $view->setDisplay();
      $view->displayHandlers->get('default')->overrideOption('filters', [
        'langcode' => [
          'id' => 'langcode',
          'table' => 'views_test_data',
          'field' => 'langcode',
          'value' => [$langcode => $langcode],
        ],
      ]);
      $this->executeView($view);

      $expected = [
        ['name' => $name],
      ];
      $this->assertIdenticalResultset($view, $expected, ['views_test_data_name' => 'name']);

      $expected = [
        '***LANGUAGE_site_default***',
        '***LANGUAGE_language_interface***',
        'en',
        'xx-lolspeak',
        'und',
        'zxx',
      ];
      $this->assertSame($expected, array_keys($view->filter['langcode']->getValueOptions()));

      $view->destroy();
    }
  }

  /**
   * Tests the language filter value options.
   */
  public function testValueOptions() {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'langcode' => [
        'id' => 'langcode',
        'table' => 'views_test_data',
        'field' => 'langcode',
        'value' => [],
      ],
    ]);
    $this->executeView($view);
    $this->assertSame([
      '***LANGUAGE_' . LanguageInterface::LANGCODE_SITE_DEFAULT . '***',
      '***LANGUAGE_' . LanguageInterface::TYPE_INTERFACE . '***',
      'en',
      'xx-lolspeak',
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ], array_keys($view->filter['langcode']->getValueOptions()));
    $view->destroy();

    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'langcode' => [
        'id' => 'langcode',
        'table' => 'views_test_data',
        'field' => 'langcode',
        'value' => [],
        'exposed' => TRUE,
      ],
    ]);
    $this->executeView($view);
    $this->assertSame(['en', 'xx-lolspeak'], array_keys($view->filter['langcode']->getValueOptions()));
  }

}
