<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\Enum\Type;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Style\Enum\Type.
 */
#[CoversClass(Type::class)]
#[Group('style')]
class TypeTest extends UnitTestCase {

  /**
   * Test Enum.
   *
   * @param \Drupal\Core\Theme\Style\Enum\Type $type
   *   The type.
   * @param string $expectedValue
   *   The expected type value.
   * @param string $expectedName
   *   The expected type name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $expectedLabel
   *   The expected type label.
   */
  #[DataProvider('providerEnum')]
  public function testEnum(Type $type, string $expectedValue, string $expectedName, TranslatableMarkup $expectedLabel): void {
    $this->assertEquals($expectedValue, $type->value);
    $this->assertEquals($expectedName, $type->name);
    $this->assertEquals($expectedLabel, $type->label());
  }

  /**
   * Data provider for ::testEnum().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerEnum(): iterable {
    yield 'theme' => [
      Type::Theme,
      'theme',
      'Theme',
      new TranslatableMarkup('Theme'),
    ];
    yield 'utility' => [
      Type::Utility,
      'utility',
      'Utility',
      new TranslatableMarkup('Utility'),
    ];
  }

}
