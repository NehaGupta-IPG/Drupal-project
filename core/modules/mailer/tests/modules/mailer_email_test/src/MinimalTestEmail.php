<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test;

use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\Mailer\Attribute\EmailSubject;
use Drupal\Core\Mailer\EmailBase;
use Symfony\Component\Mime\Email;

/**
 * Minimal test email plugin.
 */
class MinimalTestEmail extends EmailBase {

  public static function createParams(): array {
    return [];
  }

  #[EmailSubject]
  public function getSubject(string $id): string {
    return sprintf('Minimal Test Email (%s)', $id);
  }

  #[EmailBody]
  public function getBody(Email $email, string $id, string $module, string $key, string $langcode): void {
    $email->text(sprintf('%s(id: %s, module: %s, key: %s, langcode: %s)', __METHOD__, $id, $module, $key, $langcode));
  }

}
