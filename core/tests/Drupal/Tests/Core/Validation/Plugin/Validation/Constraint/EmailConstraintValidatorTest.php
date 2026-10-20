<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Validation\Plugin\Validation\Constraint;

// cspell:ignore UASG

use Drupal\Core\Validation\Plugin\Validation\Constraint\EmailConstraint;
use Drupal\Tests\EmailTestDataTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Tests that field constraints accept the shared SMTP mailbox syntax.
 */
#[CoversClass(EmailConstraint::class)]
#[Group('Validation')]
class EmailConstraintValidatorTest extends TestCase {

  use EmailTestDataTrait;

  /**
   * Tests field validation against the same contract as the email service.
   */
  #[DataProvider('internationalizedMailboxProvider')]
  #[DataProvider('uasgMailboxProvider')]
  public function testMailboxSyntax(string $mailbox, bool $valid): void {
    $violations = Validation::createValidator()->validate($mailbox, new EmailConstraint());
    // Optional empty values are validated separately by required constraints.
    $this->assertCount($valid || $mailbox === '' ? 0 : 1, $violations);
    if (count($violations)) {
      $this->assertSame(EmailConstraint::INVALID_FORMAT_ERROR, $violations[0]->getCode());
    }
  }

  /**
   * Tests the same domain input through field constraint validation.
   */
  #[DataProvider('normalizedDomainMailboxProvider')]
  public function testDomainSeparatorValidation(string $mailbox, string $normalized): void {
    $validator = Validation::createValidator();
    $this->assertCount(0, $validator->validate($normalized, new EmailConstraint()));
    $this->assertCount(0, $validator->validate($mailbox, new EmailConstraint()));
  }

}
