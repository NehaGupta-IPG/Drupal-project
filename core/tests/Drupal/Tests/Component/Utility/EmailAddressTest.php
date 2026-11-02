<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\EmailAddress;
use Drupal\Tests\EmailTestDataTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests domain-only separator processing without interpreting mailbox syntax.
 */
#[CoversClass(EmailAddress::class)]
#[Group('Utility')]
class EmailAddressTest extends TestCase {

  use EmailTestDataTrait;

  #[DataProvider('normalizedDomainMailboxProvider')]
  #[DataProvider('preservedSyntaxProvider')]
  public function testNormalize(string $input, string $expected): void {
    $this->assertSame($expected, EmailAddress::normalizeDomainSeparators($input));
    $this->assertSame($expected, EmailAddress::normalizeDomainSeparators($expected));
  }

  public static function preservedSyntaxProvider(): array {
    return [
      'Quoted local with at sign' => ['"local@name。part"@example。org', '"local@name。part"@example.org'],
      'Quoted display name and list' => [
        '"Name。 <local@name>" <person@example。org>, other@example．org',
        '"Name。 <local@name>" <person@example.org>, other@example.org',
      ],
      'Escaped quote' => ['"local\\"。part"@example｡org', '"local\\"。part"@example.org'],
      'IPv4 literal' => ['person@[192.0.2.1]', 'person@[192.0.2.1]'],
      'Invalid literal' => ['person@[192。0。2。1]', 'person@[192。0。2。1]'],
      'IPv6 literal' => ['person@[IPv6:2001:db8::1]', 'person@[IPv6:2001:db8::1]'],
      'No domain' => ['local。part', 'local。part'],
      'Invalid UTF-8' => ["person\xFF@example。org", "person\xFF@example。org"],
    ];
  }

}
