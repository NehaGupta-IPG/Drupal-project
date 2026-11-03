<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Component\Gettext\PoHeader;
use Drupal\Component\Gettext\PoItem;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the numeric field handler with a thousands separator and plurals.
 *
 * @see \Drupal\views\Plugin\views\field\NumericField
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class FieldNumericFormatPluralTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'system',
    'views',
    'views_test_config',
    'views_test_data',
    'user',
    'language',
    'locale',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $header = new PoHeader();
    [$nplurals, $formula] = $header->parsePluralForms('nplurals=2; plural=(n != 1);');
    $this->container->get('locale.plural.formula')->setPluralFormula('en', $nplurals, $formula);
  }

  /**
   * Tests plural formatting of a value rendered with a thousands separator.
   */
  #[DataProvider('providerFormatPluralSeparator')]
  public function testFormatPluralSeparator(int $value, string $expected): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('fields', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
        'separator' => ',',
        'format_plural' => TRUE,
        'format_plural_string' => '1 place' . PoItem::DELIMITER . '@count places',
      ],
    ]);
    $this->executeView($view);

    $view->result[0]->views_test_data_age = $value;
    $this->assertSame($expected, $view->field['age']->advancedRender($view->result[0]));
  }

  /**
   * Data provider for testFormatPluralSeparator().
   *
   * @return array
   *   Sets of a raw value and the expected rendered output.
   */
  public static function providerFormatPluralSeparator(): array {
    return [
      'singular' => [1, '1 place'],
      'no separator' => [999, '999 places'],
      'leading 1' => [1234, '1,234 places'],
      'leading 2' => [2345, '2,345 places'],
      'leading 9' => [9999, '9,999 places'],
      'five digits, leading 1' => [12345, '12,345 places'],
      'five digits, leading 2' => [20000, '20,000 places'],
      'six digits, leading 1' => [123456, '123,456 places'],
    ];
  }

}
