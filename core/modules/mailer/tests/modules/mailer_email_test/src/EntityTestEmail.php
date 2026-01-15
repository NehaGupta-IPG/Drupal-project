<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mailer\Attribute\EmailBody;
use Drupal\Core\Mailer\Attribute\EmailSubject;
use Drupal\Core\Mailer\EmailBase;
use Symfony\Component\Mime\Email;

/**
 * Entity test email plugin.
 */
class EntityTestEmail extends EmailBase {

  public static function createParams(EntityInterface $entity): array {
    return ['entity' => $entity];
  }

  #[EmailSubject]
  public function getSubject(): string {
    return 'Entity Test Email';
  }

  #[EmailBody]
  public function getBody(Email $email, EntityInterface $entity): void {
    $email->text(sprintf('%s(id: %s, bundle: %s, type: %s)', __METHOD__, $entity->id(), $entity->bundle(), $entity->getEntityTypeId()));
  }

}
