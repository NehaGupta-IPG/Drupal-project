<?php

declare(strict_types=1);

namespace Drupal\contact;

use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMessageBuilderInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * Defines a class to build autoreply mails from contact message entities.
 *
 * @see \Drupal\contact\Entity\Message
 */
class ReplyMessageBuilder extends EntityHandlerBase implements EntityHandlerInterface, EntityMessageBuilderInterface {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static();
    $instance->renderer = $container->get(RendererInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMessage(Message $message, EntityInterface $entity, string $key, ?string $langcode = NULL): void {
    assert($message instanceof Email);
    assert($entity instanceof MessageInterface);

    $variables = [
      '@subject' => $entity->getSubject(),
      '@form' => $entity->getContactForm()->label(),
    ];
    $message->subject((string) $this->t('[@form] @subject', $variables, ['langcode' => $langcode]));

    $build = [
      '#type' => 'processed_text',
      '#text' => $entity->getContactForm()->getReply(),
      '#langcode' => $langcode,
    ];
    $message->html((string) $this->renderer->render($build));
  }

}
