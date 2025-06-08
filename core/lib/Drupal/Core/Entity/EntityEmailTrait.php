<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

/**
 * Provides a trait which helps entities to generate email messages.
 */
trait EntityEmailTrait {

  /**
   * {@inheritdoc}
   */
  public function toEmail(string $key, ?string $langcode): Email {
    $entityType = $this->getEntityType();
    $class = $entityType->getHandlerClass('email', $key) ??
      $entityType->getHandlerClass('email', 'default');
    if (!$class) {
      throw new \InvalidArgumentException("Invalid email key ($key) specified.");
    }

    $builder = $this->entityTypeManager()->createHandlerInstance($class, $entityType);
    assert($builder instanceof EntityMessageBuilderInterface);

    $email = new Email();

    // Add message tags.
    $email->getHeaders()
      ->add(new TagHeader($entityType->getProvider() . '.' . $key))
      ->add(new MetadataHeader('Drupal-Provider', $entityType->getProvider()))
      ->add(new MetadataHeader('Drupal-Key', $key));

    // FIXME: The rendering could be moved to an event subscriber. Rendering the
    // message is the responsibility of the MessageListener in Symfony.
    // @see https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/Mailer/EventListener/MessageListener.php#L71

    // FIXME: Isolating the render context is not the only thing necessary to
    // render the message body consistently. At least theme switching and
    // possibly other context switches might be needed.
    // @see https://www.drupal.org/i/3486179
    \Drupal::service(RendererInterface::class)->executeInRenderContext(new RenderContext(), function () use ($email, $builder, $key, $langcode) {
      $builder->buildMessage($email, $this, $key, $langcode);
    });

    return $email;
  }

}
