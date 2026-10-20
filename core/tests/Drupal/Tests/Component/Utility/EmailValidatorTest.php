<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

// cspell:ignore UASG

use Drupal\Component\Utility\EmailValidator;
use Drupal\Tests\EmailTestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\TestTools\PhpUnitCompatibility\ForwardCompatibilityTrait;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EmailValidator utility class.
 */
#[CoversClass(EmailValidator::class)]
#[Group('Utility')]
class EmailValidatorTest extends TestCase {

  use EmailTestDataTrait;
  use ForwardCompatibilityTrait;

  /**
   * Tests is valid.
   */
  public function testIsValid(): void {
    // Note that \Drupal\Component\Utility\EmailValidator wraps
    // \Egulias\EmailValidator\EmailValidator so we don't do anything more than
    // test that the wrapping works since the dependency has its own test
    // coverage.
    $validator = new EmailValidator();
    $this->assertTrue($validator->isValid('example@example.com'));
    $this->assertFalse($validator->isValid('example@example.com@'));
    $this->assertFalse($validator->isValid('example@example .com'));
    $this->assertFalse($validator->isValid('example @example.com'));
  }

  /**
   * Tests the SMTP mailbox syntax contract.
   */
  #[DataProvider('internationalizedMailboxProvider')]
  #[DataProvider('uasgMailboxProvider')]
  public function testMailboxSyntax(string $mailbox, bool $valid): void {
    $validator = new EmailValidator();
    $this->assertSame($valid, $validator->isValid($mailbox));
    if (!$valid) {
      $this->assertNotNull($validator->getError());
    }
  }

  /**
   * Tests is valid exception.
   */
  public function testIsValidException(): void {
    $validator = new EmailValidator();
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessageIs('Calling \Drupal\Component\Utility\EmailValidator::isValid() with the second argument is not supported. See https://www.drupal.org/node/2997196');
    $validator->isValid('example@example.com', (new NoRFCWarningsValidation()));
  }

}
