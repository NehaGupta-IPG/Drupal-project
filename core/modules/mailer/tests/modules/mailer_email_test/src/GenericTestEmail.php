<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test;

use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\Mailer\Attribute\EmailSubject;
use Drupal\Core\Mailer\EmailParams;
use Drupal\Core\Mailer\EmailBase;
use Symfony\Component\Mime\Email;

/**
 * Generic test email plugin.
 */
class GenericTestEmail extends EmailBase {

  #[EmailSubject]
  public function getSubject(string $id): string {
    return sprintf('Generic Test Email (%s)', $id);
  }

  #[EmailBody]
  public function getBody(Email $email, EmailParams $params): void {
    $keys = array_keys($params->getArguments());
    sort($keys);

    $email->text(json_encode($keys, JSON_THROW_ON_ERROR));
  }

}
