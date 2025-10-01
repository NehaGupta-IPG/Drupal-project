<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style\Enum;

use Drupal\Core\Theme\Style\Enum\Target;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Style\Enum\Target.
 */
#[CoversClass(Target::class)]
#[Group('style')]
class TargetTest extends UnitTestCase {

  /**
   * Test Enum.
   *
   * @param \Drupal\Core\Theme\Style\Enum\Target $target
   *   The target.
   * @param string $expectedValue
   *   The expected target value.
   * @param string $expectedName
   *   The expected target name.
   */
  #[DataProvider('providerEnum')]
  public function testEnum(Target $target, string $expectedValue, string $expectedName): void {
    $this->assertEquals($expectedValue, $target->value);
    $this->assertEquals($expectedName, $target->name);
  }

  /**
   * Data provider for ::testEnum().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerEnum(): iterable {
    yield 'any' => [
      Target::Any,
      'any',
      'Any',
    ];
    yield 'html' => [
      Target::Html,
      'html',
      'Html',
    ];
    yield 'body' => [
      Target::Body,
      'body',
      'Body',
    ];
    yield 'meta' => [
      Target::Meta,
      'meta',
      'Meta',
    ];
  }

}
