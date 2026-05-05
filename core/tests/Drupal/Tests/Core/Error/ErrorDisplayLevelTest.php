<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Core\Error\ErrorDisplayLevel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ErrorDisplayLevel enum.
 */
#[Group('Error')]
class ErrorDisplayLevelTest extends UnitTestCase {

  /**
   * Tests that enum cases have correct string values.
   */
  public function testEnumValues(): void {
    $this->assertSame('hide', ErrorDisplayLevel::Hide->value);
    $this->assertSame('some', ErrorDisplayLevel::Some->value);
    $this->assertSame('all', ErrorDisplayLevel::All->value);
    $this->assertSame('verbose', ErrorDisplayLevel::Verbose->value);
  }

  /**
   * Tests that tryFrom works for valid values.
   */
  #[DataProvider('provideValidValues')]
  public function testTryFromValid(string $value, ErrorDisplayLevel $expected): void {
    $this->assertSame($expected, ErrorDisplayLevel::tryFrom($value));
  }

  /**
   * Data provider for valid enum values.
   *
   * @return array<string, array{0: string, 1: ErrorDisplayLevel}>
   *   Test cases.
   */
  public static function provideValidValues(): array {
    return [
      'hide' => ['hide', ErrorDisplayLevel::Hide],
      'some' => ['some', ErrorDisplayLevel::Some],
      'all' => ['all', ErrorDisplayLevel::All],
      'verbose' => ['verbose', ErrorDisplayLevel::Verbose],
    ];
  }

  /**
   * Tests that tryFrom returns NULL for invalid values.
   */
  public function testTryFromInvalid(): void {
    $this->assertNull(ErrorDisplayLevel::tryFrom('invalid'));
    $this->assertNull(ErrorDisplayLevel::tryFrom(''));
  }

  /**
   * Tests isVerbose() returns TRUE only for Verbose case.
   */
  public function testIsVerbose(): void {
    $this->assertTrue(ErrorDisplayLevel::Verbose->isVerbose());
    $this->assertFalse(ErrorDisplayLevel::All->isVerbose());
    $this->assertFalse(ErrorDisplayLevel::Some->isVerbose());
    $this->assertFalse(ErrorDisplayLevel::Hide->isVerbose());
  }

  /**
   * Tests isDisplayable() for Hide level.
   */
  public function testIsDisplayableHide(): void {
    $this->assertFalse(ErrorDisplayLevel::Hide->isDisplayable());
    $this->assertFalse(ErrorDisplayLevel::Hide->isDisplayable(['%type' => 'Error']));
  }

  /**
   * Tests isDisplayable() for All level.
   */
  public function testIsDisplayableAll(): void {
    $this->assertTrue(ErrorDisplayLevel::All->isDisplayable());
    $this->assertTrue(ErrorDisplayLevel::All->isDisplayable(['%type' => 'Notice']));
  }

  /**
   * Tests isDisplayable() for Verbose level.
   */
  public function testIsDisplayableVerbose(): void {
    $this->assertTrue(ErrorDisplayLevel::Verbose->isDisplayable());
    $this->assertTrue(ErrorDisplayLevel::Verbose->isDisplayable(['%type' => 'Notice']));
  }

  /**
   * Tests isDisplayable() for Some level filters Notice and Strict warning.
   */
  public function testIsDisplayableSome(): void {
    // Without error context, Some does not display.
    $this->assertFalse(ErrorDisplayLevel::Some->isDisplayable());

    // Notices and strict warnings are filtered out.
    $this->assertFalse(ErrorDisplayLevel::Some->isDisplayable(['%type' => 'Notice']));
    $this->assertFalse(ErrorDisplayLevel::Some->isDisplayable(['%type' => 'Strict warning']));

    // Other error types are displayed.
    $this->assertTrue(ErrorDisplayLevel::Some->isDisplayable(['%type' => 'Error']));
    $this->assertTrue(ErrorDisplayLevel::Some->isDisplayable(['%type' => 'Warning']));
    $this->assertTrue(ErrorDisplayLevel::Some->isDisplayable(['%type' => 'User error']));
  }

  /**
   * Tests that all four cases exist.
   */
  public function testCasesCount(): void {
    $this->assertCount(4, ErrorDisplayLevel::cases());
  }

}
