<?php

declare(strict_types=1);

namespace Drupal\contact;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Adds the experimental mail handler if enabled.
 */
class ContactServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->has(MailerInterface::class) && $container->hasParameter('contact.use_experimental_mailer') && $container->getParameter('contact.use_experimental_mailer')) {
      $container->getDefinition('contact.mail_handler')
        ->setClass(MailerMailHandler::class)
        ->setArguments([
          new Reference(MailerInterface::class),
          new Reference(LanguageManagerInterface::class),
          new Reference('logger.channel.contact'),
          new Reference(EntityTypeManagerInterface::class),
          new Reference(ConfigFactoryInterface::class),
        ]);
    }
  }

}
