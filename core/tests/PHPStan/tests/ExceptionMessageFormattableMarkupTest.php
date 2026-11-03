<?php

declare(strict_types=1);

namespace Drupal\PHPStan\Tests;

use Drupal\PHPStan\Rules\ExceptionMessageFormattableMarkup;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ExceptionMessageFormattableMarkup rule.
 *
 * @extends RuleTestCase<ExceptionMessageFormattableMarkup>
 */
#[Group('PHPStan')]
class ExceptionMessageFormattableMarkupTest extends RuleTestCase {

  /**
   * {@inheritdoc}
   */
  protected function getRule(): Rule {
    return new ExceptionMessageFormattableMarkup(
      self::getContainer()->getByType(ReflectionProvider::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function testRule(): void {
    // cspell:ignore analyse
    $this->analyse(
      [__DIR__ . '/../fixtures/exception-message-formattable-markup.php'],
      [
        [
          'Exception messages must not use FormattableMarkup.',
          17,
        ],
        [
          'Exception messages must not use FormattableMarkup.',
          18,
        ],
      ]
    );
  }

}
