<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\Enum\ModeColorScheme;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Style\Enum\ModeColorScheme.
 */
#[CoversClass(ModeColorScheme::class)]
#[Group('style')]
class ModeColorSchemeTest extends UnitTestCase {

  /**
   * Test Enum.
   *
   * @param \Drupal\Core\Theme\Style\Enum\ModeColorScheme $mode
   *   The mode.
   * @param string $expectedValue
   *   The expected mode value.
   * @param string $expectedName
   *   The expected mode name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $expectedLabel
   *   The expected mode label.
   */
  #[DataProvider('providerEnum')]
  public function testEnum(ModeColorScheme $mode, string $expectedValue, string $expectedName, TranslatableMarkup $expectedLabel): void {
    $this->assertEquals($expectedValue, $mode->value);
    $this->assertEquals($expectedName, $mode->name);
    $this->assertEquals($expectedLabel, $mode->label());
  }

  /**
   * Data provider for ::testEnum().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerEnum(): iterable {
    yield 'any' => [
      ModeColorScheme::Any,
      'any',
      'Any',
      new TranslatableMarkup('Any'),
    ];
    yield 'light' => [
      ModeColorScheme::Light,
      'light',
      'Light',
      new TranslatableMarkup('Light'),
    ];
    yield 'dark' => [
      ModeColorScheme::Dark,
      'dark',
      'Dark',
      new TranslatableMarkup('Dark'),
    ];
  }

}
