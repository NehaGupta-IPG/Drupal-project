<?php

declare(strict_types=1);

namespace Drupal\PHPStan\Tests;

use Drupal\PHPStan\Rules\SetUpMethodVisibility;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SetUpMethodVisibility rule.
 *
 * @extends RuleTestCase<SetUpMethodVisibility>
 */
#[Group('PHPStan')]
class SetUpMethodVisibilityTest extends RuleTestCase {

  /**
   * {@inheritdoc}
   */
  protected function getRule(): Rule {
    return new SetUpMethodVisibility(
      self::getContainer()->getByType(ReflectionProvider::class),
    );
  }

  /**
   * Tests that test setUp() methods are protected.
   */
  public function testRule(): void {
    // cspell:ignore analyse
    $this->analyse(
      [__DIR__ . '/../fixtures/set-up-method-visibility.php'],
      [
        [
          'Test setUp() methods must be protected.',
          13,
        ],
      ],
    );
  }

}
