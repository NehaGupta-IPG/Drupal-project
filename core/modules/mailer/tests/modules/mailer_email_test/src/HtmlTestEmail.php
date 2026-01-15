<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test;

use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\Mailer\Attribute\EmailSubject;
use Drupal\Core\Mailer\EmailBase;

/**
 * Html test email plugin.
 */
class HtmlTestEmail extends EmailBase {

  public static function createParams(): array {
    return [];
  }

  #[EmailSubject]
  public function getSubject(string $id): string {
    return sprintf('HTML Email (%s)', $id);
  }

  #[EmailBody]
  public function getBody(): array {
    $build['colors'] = [
      '#markup' => '<p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p>',
    ];
    return $build;
  }

}
