<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\Enum\Status;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Style\Enum\Status.
 */
#[CoversClass(Status::class)]
#[Group('style')]
class StatusTest extends UnitTestCase {

  /**
   * Test Enum.
   *
   * @param \Drupal\Core\Theme\Style\Enum\Status $status
   *   The status.
   * @param string $expectedValue
   *   The expected status value.
   * @param string $expectedName
   *   The expected status name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $expectedLabel
   *   The expected status label.
   * @param bool $expectedTriggerWarning
   *   Whether the status should trigger a warning.
   */
  #[DataProvider('providerEnum')]
  public function testEnum(Status $status, string $expectedValue, string $expectedName, TranslatableMarkup $expectedLabel, bool $expectedTriggerWarning): void {
    $this->assertEquals($expectedValue, $status->value);
    $this->assertEquals($expectedName, $status->name);
    $this->assertEquals($expectedLabel, $status->label());
    $this->assertEquals($expectedTriggerWarning, $status->triggerWarning());
  }

  /**
   * Data provider for ::testEnum().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerEnum(): iterable {
    yield 'experimental' => [
      Status::Experimental,
      'experimental',
      'Experimental',
      new TranslatableMarkup('Experimental'),
      FALSE,
    ];
    yield 'stable' => [
      Status::Stable,
      'stable',
      'Stable',
      new TranslatableMarkup('Stable'),
      FALSE,
    ];
    yield 'deprecated' => [
      Status::Deprecated,
      'deprecated',
      'Deprecated',
      new TranslatableMarkup('Deprecated'),
      TRUE,
    ];
    yield 'obsolete' => [
      Status::Obsolete,
      'obsolete',
      'Obsolete',
      new TranslatableMarkup('Obsolete'),
      TRUE,
    ];
  }

}
