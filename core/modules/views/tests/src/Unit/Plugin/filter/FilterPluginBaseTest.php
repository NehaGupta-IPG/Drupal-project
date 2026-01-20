<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\filter;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\filter\FilterPluginBase.
 */
#[CoversClass(FilterPluginBase::class)]
#[CoversMethod(FilterPluginBase::class, 'getCacheContexts')]
#[Group('views')]
class FilterPluginBaseTest extends UnitTestCase {

  /**
   * Tests accept exposed input.
   */
  #[DataProvider('acceptExposedInputProvider')]
  public function testAcceptExposedInput(bool $expected_result, array $options, array $input): void {
    $definition = [
      'title' => 'Accept exposed input Test',
      'group' => 'Test',
    ];
    $filter = new FilterPluginBaseStub([], 'stub', $definition);
    $filter->options = $options;
    $this->assertSame($expected_result, $filter->acceptExposedInput($input));
  }

  /**
   * The data provider for testAcceptExposedInput.
   *
   * @return array
   *   The data set.
   */
  public static function acceptExposedInputProvider() {
    return [
      'not-exposed' => [TRUE, ['exposed' => FALSE], []],
      'exposed-no-input' => [TRUE, ['exposed' => TRUE], []],
      'exposed-zero-input' => [FALSE, [
        'exposed' => TRUE,
        'is_grouped' => FALSE,
        'expose' => [
          'use_operator' => TRUE,
          'operator_id' => '=',
          'identifier' => 'identifier',
        ],
      ], ['identifier' => 0],
      ],
      'exposed-empty-array-input' => [FALSE, [
        'exposed' => TRUE,
        'is_grouped' => FALSE,
        'expose' => [
          'use_operator' => TRUE,
          'operator_id' => '=',
          'identifier' => 'identifier',
        ],
      ], ['identifier' => []],
      ],
    ];
  }

  #[DataProvider('getCacheContextsProvider')]
  public function testGetCacheContexts(array $expected_result, array $options): void {
    $definition = [
      'title' => 'Get cache contexts Test',
      'group' => 'Test',
    ];
    $filter = new FilterPluginBaseStub([], 'stub', $definition);
    $filter->options = $options;
    $this->assertSame($expected_result, $filter->getCacheContexts());
  }

  /**
   * The data provider for testGetCacheContexts.
   *
   * @return array
   *   The data set.
   */
  public static function getCacheContextsProvider(): array {
    return [
      'not-exposed' => [[], ['exposed' => FALSE]],
      'exposed-no-input' => [['url.query_args'], ['exposed' => TRUE]],
      'exposed-zero-input' => [
        ['url.query_args:identifier', 'url.query_args:operator_id'],
        [
          'exposed' => TRUE,
          'is_grouped' => FALSE,
          'expose' => [
            'use_operator' => TRUE,
            'operator_id' => 'operator_id',
            'identifier' => 'identifier',
          ],
        ],
      ],
      'exposed-empty-array-input' => [
        ['url.query_args:identifier', 'url.query_args:operator_id'],
        [
          'exposed' => TRUE,
          'is_grouped' => FALSE,
          'expose' => [
            'use_operator' => TRUE,
            'operator_id' => 'operator_id',
            'identifier' => 'identifier',
          ],
        ],
      ],
    ];
  }

}

/**
 * Empty class to support testing filter plugins.
 */
class FilterPluginBaseStub extends FilterPluginBase {}
