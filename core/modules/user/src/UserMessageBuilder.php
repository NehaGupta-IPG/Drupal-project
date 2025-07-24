<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMessageBuilderInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * Defines a class to build mails from user entities.
 *
 * @see \Drupal\user\Entity\User
 */
class UserMessageBuilder extends EntityHandlerBase implements EntityHandlerInterface, EntityMessageBuilderInterface {

  /**
   * The token service.
   */
  protected Token $tokenService;

  /**
   * The language manager service.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static();
    $instance->tokenService = $container->get(Token::class);
    $instance->languageManager = $container->get(LanguageManagerInterface::class);
    $instance->configFactory = $container->get(ConfigFactoryInterface::class);
    $instance->renderer = $container->get(RendererInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMessage(Message $message, EntityInterface $entity, string $key, ?string $langcode = NULL): void {
    assert($message instanceof Email);
    $variables = ['user' => $entity];
    $language = $this->languageManager->getLanguage($langcode);
    $originalLanguage = $this->languageManager->getConfigOverrideLanguage();
    $this->languageManager->setConfigOverrideLanguage($language);
    $mailConfig = $this->configFactory->get('user.mail');
    $tokenOptions = ['langcode' => $langcode, 'callback' => 'user_mail_tokens'];
    $message->subject(PlainTextOutput::renderFromHtml($this->tokenService->replace($mailConfig->get($key . '.subject'), $variables, $tokenOptions)));
    $build = [
      '#type' => 'processed_text',
      '#text' => $this->tokenService->replacePlain($mailConfig->get($key . '.body'), $variables, $tokenOptions),
      '#langcode' => $langcode,
    ];
    $message->html((string) $this->renderer->render($build));
    $this->languageManager->setConfigOverrideLanguage($originalLanguage);
  }

}
