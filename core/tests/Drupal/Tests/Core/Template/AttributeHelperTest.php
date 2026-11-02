<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Template;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeHelper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Template\AttributeHelper.
 */
#[CoversClass(AttributeHelper::class)]
#[Group('Template')]
class AttributeHelperTest extends UnitTestCase {

  /**
   * Provides tests data for testAttributeExists.
   *
   * @return array
   *   An array of test data each containing an array of attributes, the name
   *   of the attribute to check existence of, and the expected result.
   */
  public static function providerTestAttributeExists(): array {
    return [
      [['class' => ['example-class']], 'class', TRUE],
      [[], 'class', FALSE],
      [['class' => ['example-class']], 'id', FALSE],
      [['class' => ['example-class'], 'id' => 'foo'], 'id', TRUE],
      [['id' => 'foo'], 'class', FALSE],
    ];
  }

  /**
   * Tests attribute exists.
   */
  #[DataProvider('providerTestAttributeExists')]
  public function testAttributeExists(array $test_data, string $test_attribute, bool $expected): void {
    $this->assertSame($expected, AttributeHelper::attributeExists($test_attribute, $test_data));
    $attributes = new Attribute($test_data);
    $this->assertSame($expected, AttributeHelper::attributeExists($test_attribute, $attributes));
  }

  /**
   * Provides tests data for testMergeCollections.
   *
   * @return array
   *   An array of test data each containing an initial attribute collection, an
   *   Attribute object or array to be merged, and the expected result.
   */
  public static function providerTestMergeCollections(): array {
    return [
      [[], ['class' => ['class1']], ['class' => ['class1']]],
      [[], new Attribute(['class' => ['class1']]), ['class' => ['class1']]],
      [
        ['class' => ['example-class']],
        ['class' => ['class1']],
        ['class' => ['example-class', 'class1']],
      ],
      [
        ['class' => ['example-class']],
        new Attribute(['class' => ['class1']]),
        ['class' => ['example-class', 'class1']],
      ],
      [
        ['class' => ['example-class']],
        ['id' => 'foo', 'href' => 'bar'],
        ['class' => ['example-class'], 'id' => 'foo', 'href' => 'bar'],
      ],
      [
        ['class' => ['example-class']],
        new Attribute(['id' => 'foo', 'href' => 'bar']),
        ['class' => ['example-class'], 'id' => 'foo', 'href' => 'bar'],
      ],
      [
        ['class' => ['example-class']],
        ['class' => ['class1']],
        ['class' => ['example-class', 'class1']],
      ],
      [
        ['class' => ['example-class']],
        new Attribute(['class' => ['class1']]),
        ['class' => ['example-class', 'class1']],
      ],
      [
        ['class' => ['example-class']],
        ['id' => 'foo', 'href' => 'bar'],
        ['class' => ['example-class'], 'id' => 'foo', 'href' => 'bar'],
      ],
      [
        ['class' => ['example-class']],
        new Attribute(['id' => 'foo', 'href' => 'bar']),
        ['class' => ['example-class'], 'id' => 'foo', 'href' => 'bar'],
      ],
      [
        ['class' => ['example-class'], 'style' => 'color: pink;'],
        ['class' => ['class1'], 'style' => 'color: red;'],
        ['class' => ['example-class', 'class1'], 'style' => 'color: red;'],
      ],
      [
        ['class' => ['example-class'], 'style' => 'color: pink;'],
        ['class' => ['class1'], 'style' => ['color: red;']],
        [
          'class' => ['example-class', 'class1'],
          'style' => ['color: pink;', 'color: red;'],
        ],
      ],
      [
        ['class' => ['example-class'], 'style' => ['color: pink;']],
        ['class' => ['class1'], 'style' => 'color: red;'],
        [
          'class' => ['example-class', 'class1'],
          'style' => ['color: pink;', 'color: red;'],
        ],
      ],
      [
        ['class' => ['example-class'], 'style' => ['color: pink;']],
        ['class' => ['class1'], 'style' => ['color: red;']],
        [
          'class' => ['example-class', 'class1'],
          'style' => ['color: pink;', 'color: red;'],
        ],
      ],
    ];
  }

  /**
   * Tests merge collections.
   */
  #[DataProvider('providerTestMergeCollections')]
  public function testMergeCollections(array $original, Attribute|array $merge, array $expected): void {
    $this->assertEquals($expected, AttributeHelper::mergeCollections($original, $merge));
    $this->assertEquals(new Attribute($expected), AttributeHelper::mergeCollections(new Attribute($original), $merge));
  }

  /**
   * Tests merge collections argument exception.
   */
  public function testMergeCollectionsAttributeWithStringArgumentException(): void {
    $attributes = new Attribute(['class' => ['example-class']]);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageIs('Invalid collection argument');
    // @phpstan-ignore argument.type
    AttributeHelper::mergeCollections($attributes, 'not an array');
  }

  /**
   * Tests merge collections argument exception.
   */
  public function testMergeCollectionsStringWithAttributeArgumentException(): void {
    $attributes = new Attribute(['class' => ['example-class']]);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageIs('Invalid collection argument');
    // @phpstan-ignore argument.type
    AttributeHelper::mergeCollections('not an array', $attributes);
  }

  /**
   * Data provider for ::testMatchCollectionsTypes.
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerTestMatchCollectionsTypes(): iterable {
    yield 'Default: matching types' => [
      [
        'class' => ['my-class'],
        'style' => 'color: pink;',
        'target' => '_blank',
      ],
      [
        'class' => ['my-second-class'],
        'style' => 'color: red;',
        'target' => '_same',
      ],
      [
        'class' => ['my-class'],
        'style' => 'color: pink;',
        'target' => '_blank',
      ],
      [
        'class' => ['my-second-class'],
        'style' => 'color: red;',
        'target' => '_same',
      ],
    ];

    yield 'First array has string attribute, second has array attribute' => [
      [
        'style' => 'color: pink;',
      ],
      [
        'style' => ['color: red;'],
      ],
      [
        'style' => ['color: pink;'],
      ],
      [
        'style' => ['color: red;'],
      ],
    ];

    yield 'Second array has string attribute, first has array attribute' => [
      [
        'style' => ['color: pink;'],
      ],
      [
        'style' => 'color: red;',
      ],
      [
        'style' => ['color: pink;'],
      ],
      [
        'style' => ['color: red;'],
      ],
    ];
  }

  /**
   * Tests match collections types.
   *
   * @legacy-covers ::matchCollectionsTypes
   */
  #[DataProvider('providerTestMatchCollectionsTypes')]
  public function testMatchCollectionsTypes(array $array1, array $array2, array $expectedArray1, array $expectedArray2): void {
    AttributeHelper::matchCollectionsTypes($array1, $array2);
    $this->assertEquals($expectedArray1, $array1);
    $this->assertEquals($expectedArray2, $array2);
  }

}
