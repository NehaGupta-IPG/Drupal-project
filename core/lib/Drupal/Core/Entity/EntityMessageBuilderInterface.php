<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Symfony\Component\Mime\Message;

/**
 * Defines an interface for entity message builders.
 *
 * @ingroup entity_api
 */
interface EntityMessageBuilderInterface {

  /**
   * Populates a MIME message from the provided entity.
   *
   * @param \Symfony\Component\Mime\Message $message
   *   The target MIME message.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   * @param string $key
   *   The message mode that should be used to render the entity.
   * @param string $langcode
   *   (optional) For which language the entity should be rendered, defaults to
   *   the current content language.
   *
   * @throws \InvalidArgumentException
   *   Can be thrown when the set of parameters is inconsistent.
   */
  public function buildMessage(Message $message, EntityInterface $entity, string $key, ?string $langcode = NULL): void;

}
