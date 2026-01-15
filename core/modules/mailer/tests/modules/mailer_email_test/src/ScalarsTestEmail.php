<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test;

use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\Mailer\Attribute\EmailSubject;
use Drupal\Core\Mailer\EmailBase;
use Symfony\Component\Mime\Email;

/**
 * Scalars test email plugin.
 */
class ScalarsTestEmail extends EmailBase {

  public static function createParams(string $text, int $number, bool $status): array {
    return [
      'text' => $text,
      'number' => $number,
      'status' => $status,
    ];
  }

  #[EmailSubject]
  public function getSubject(): string {
    return 'Scalar Params Test Email';
  }

  #[EmailBody]
  public function getBody(Email $email, string $text, int $number, bool $status): void {
    $email->text(sprintf('%s(text: %s, number: %s, status: %s)', __METHOD__, $text, $number, $status ? 'true' : 'false'));
  }

}
