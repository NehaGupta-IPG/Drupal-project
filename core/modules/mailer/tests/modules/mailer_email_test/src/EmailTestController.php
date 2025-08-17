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

  public function minimal(Email $email, string $id, string $module, string $key, string $langcode): array {
    $email->subject(sprintf('Minimal Test Email (%s)', $id));

    return [
      '#type' => 'processed_text',
      '#text' => sprintf('%s(id: %s, module: %s, key: %s, langcode: $s)', __METHOD__, $id, $module, $key, $langcode),
      '#langcode' => $langcode,
      '#format' => NULL,
    ];
  }

  public function scalars(Email $email, string $text, int $number, bool $status, string $langcode): array {
    $email->subject('Scalar Params Test Email');

    return [
      '#type' => 'processed_text',
      '#text' => sprintf('%s(text: %s, number: %s, status: %s)', __METHOD__, $text, $number, $status),
      '#langcode' => $langcode,
      '#format' => NULL,
    ];
  }

  public function entity(Email $email, EntityInterface $entity, string $langcode): array {
    $email->subject('Typical Test Email');

    return [
      '#type' => 'processed_text',
      '#text' => sprintf('entity(id: %s, bundle: %s, type: %s)', $entity->id(), $entity->bundle(), $entity->getEntityTypeId()),
      '#langcode' => $langcode,
      '#format' => NULL,
    ];
  }

  public function generic(Email $email, EmailControllerParams $params, string $langcode): array {
    $email->subject(sprintf('Generic Test Email (%s)', $params->definition->id()));

    $argnames = array_keys($params->getArguments());
    sort($argnames);

    return [
      '#type' => 'processed_text',
      '#text' => json_encode($argnames, JSON_THROW_ON_ERROR),
      '#langcode' => $langcode,
      '#format' => NULL,
    ];
  }

}
