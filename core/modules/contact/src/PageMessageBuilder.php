<?php

declare(strict_types=1);

namespace Drupal\contact;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityHandlerBase;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMessageBuilderInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * Defines a class to build mails from site wide contact page message entities.
 *
 * @see \Drupal\contact\Entity\Message
 */
class PageMessageBuilder extends EntityHandlerBase implements EntityHandlerInterface, EntityMessageBuilderInterface {

  /**
   * The language manager service.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $instance = new static();
    $instance->languageManager = $container->get(LanguageManagerInterface::class);
    $instance->configFactory = $container->get(ConfigFactoryInterface::class);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->renderer = $container->get(RendererInterface::class);
    $instance->currentUser = $container->get(AccountInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildMessage(Message $message, EntityInterface $entity, string $key, ?string $langcode = NULL): void {
    assert($message instanceof Email);
    assert($entity instanceof MessageInterface);

    $language = $this->languageManager->getLanguage($langcode);
    $variables = [
      '@site-name' => $this->configFactory->get('system.site')->get('name'),
      '@subject' => $entity->getSubject(),
      '@form' => $entity->getContactForm()->label(),
      '@form-url' => $entity->getContactForm()->toUrl('canonical', [
        'absolute' => TRUE,
        'language' => $language,
      ])->toString(),
    ];

    // FIXME: The contact message entity should store the sender user object as
    // an entity relation.
    $sender = $this->currentUser;
    if ($sender->isAuthenticated()) {
      $senderEntity = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      assert($senderEntity instanceof UserInterface);
      $variables['@sender-name'] = $senderEntity->getDisplayName();
      $variables['@sender-url'] = $senderEntity->toUrl('canonical', ['absolute' => TRUE, 'language' => $language])->toString();
    }
    else {
      $variables['@sender-name'] = $this->t('@name (not verified)', ['@name' => $entity->getSenderName()]);
      $variables['@sender-url'] = $entity->getSenderMail();
    }

    $options = ['langcode' => $langcode];
    $message->subject((string) $this->t('[@form] @subject', $variables, $options));
    $build = [
      'intro' => [
        '#type' => 'processed_text',
        '#text' => $this->t("@sender-name (@sender-url) sent a message using the contact form at @form-url.", $variables, $options),
        '#langcode' => $langcode,
      ],
      'message' => $this->entityTypeManager->getViewBuilder('contact_message')->view($entity, 'mail', $langcode),
    ];
    $message->html((string) $this->renderer->render($build));
  }

}
