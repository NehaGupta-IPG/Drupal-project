<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\views\filter;

use Drupal\Core\Database\Database;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\filter\StringFilter.
 */
#[CoversClass(StringFilter::class)]
#[Group('Views')]
class StringFilterTest extends UnitTestCase {

  /**
   * Tests the acceptExposedInput method.
   *
   * @legacy-covers ::acceptExposedInput
   */
  #[DataProvider('provideAcceptExposedInput')]
  public function testAcceptExposedInput($options, $value, $expected): void {
    $plugin_definition = [
      'title' => $this->randomMachineName(),
    ];

    $connection = Database::getConnection();
    $configuration = [];
    $plugin = new StringFilter($configuration, 'string_filter', $plugin_definition, $connection);
    $translation_stub = $this->getStringTranslationStub();
    $plugin->setStringTranslation($translation_stub);

    $view = $this->prophesize(ViewExecutable::class)->reveal();
    $display = $this->prophesize(DisplayPluginBase::class)->reveal();
    $view->display_handler = $display;
    $plugin->init($view, $view->display_handler, $options);

    $this->assertSame($expected, $plugin->acceptExposedInput($value));
  }

  /**
   * Data provider for testAcceptExposedInput test.
   *
   * @return array[]
   *   The test cases.
   */
  public static function provideAcceptExposedInput(): array {
    // [$options, $value, $expected]
    return [];
  }

}
