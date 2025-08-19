<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mailer\EmailControllerParams;
use Symfony\Component\Mime\Email;

/**
 * Test email controller methods.
 */
class EmailTestController {

  public function minimal(Email $email, string $id, string $module, string $key, string $langcode): void {
    $email->subject(sprintf('Minimal Test Email (%s)', $id));
    $email->text(sprintf('%s(id: %s, module: %s, key: %s, langcode: %s)', __METHOD__, $id, $module, $key, $langcode));
  }

  public function scalars(Email $email, string $text, int $number, bool $status): void {
    $email->subject('Scalar Params Test Email');
    $email->text(sprintf('%s(text: %s, number: %s, status: %s)', __METHOD__, $text, $number, $status ? 'true' : 'false'));
  }

  public function entity(Email $email, EntityInterface $entity): void {
    $email->subject('Entity Test Email');
    $email->text(sprintf('%s(id: %s, bundle: %s, type: %s)', __METHOD__, $entity->id(), $entity->bundle(), $entity->getEntityTypeId()));
  }

  public function generic(Email $email, EmailControllerParams $params): void {
    $email->subject(sprintf('Generic Test Email (%s)', $params->definition->id()));
    $argnames = array_keys($params->getArguments());
    sort($argnames);

    $email->text(json_encode($argnames, JSON_THROW_ON_ERROR));
  }

  public function html(Email $email, string $id): array {
    $email->subject(sprintf('HTML Email (%s)', $id));
    $build['colors'] = [
      '#markup' => '<p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p>',
    ];
    return $build;
  }

}
