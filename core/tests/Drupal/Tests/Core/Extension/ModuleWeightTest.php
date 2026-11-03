<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Extension;

use Drupal\Core\Extension\ModuleWeight;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Extension\ModuleWeight.
 */
#[CoversClass(ModuleWeight::class)]
#[Group('Extension')]
class ModuleWeightTest extends UnitTestCase {

  /**
   * Tests sorting by weight and name.
   *
   * @param array $data
   *   The module data to sort.
   * @param array $expected
   *   The expected sorted module data.
   */
  #[DataProvider('providerSort')]
  public function testSort(array $data, array $expected): void {
    // Call the static method without instantiating the class.
    $this->assertSame($expected, ModuleWeight::sort($data));
  }

  /**
   * Data provider for testSort().
   *
   * @return array[]
   *   The test cases for testSort().
   */
  public static function providerSort(): array {
    return [
      'weight then name' => [
        ['node' => 0, 'block' => -5, 'user' => 0],
        ['block' => -5, 'node' => 0, 'user' => 0],
      ],
      'negative weight before name order' => [
        ['zebra' => -1, 'apple' => 0],
        ['zebra' => -1, 'apple' => 0],
      ],
      'extreme weights' => [
        ['high' => PHP_INT_MAX, 'low' => -PHP_INT_MAX, 'mid' => 0],
        ['low' => -PHP_INT_MAX, 'mid' => 0, 'high' => PHP_INT_MAX],
      ],
      'empty array' => [
        [],
        [],
      ],
      'single element' => [
        ['single' => 3],
        ['single' => 3],
      ],
    ];
  }

}
