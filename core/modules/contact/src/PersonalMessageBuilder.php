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
 * Defines a class to build mails from per-user contact message entities.
 *
 * @see \Drupal\contact\Entity\Message
 */
class PersonalMessageBuilder extends EntityHandlerBase implements EntityHandlerInterface, EntityMessageBuilderInterface {

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
    $recipient = $entity->getPersonalRecipient();
    assert($recipient instanceof UserInterface);

    $variables = [
      '@site-name' => $this->configFactory->get('system.site')->get('name'),
      '@subject' => $entity->getSubject(),
      '@recipient-name' => $entity->getPersonalRecipient()->getDisplayName(),
      '@recipient-edit-url' => $entity->getPersonalRecipient()->toUrl('edit-form', [
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
    $message->subject((string) $this->t('[@site-name] @subject', $variables, $options));
    $build = [
      'opening' => [
        '#type' => 'processed_text',
        '#text' => $this->t('Hello @recipient-name,', $variables, $options),
        '#langcode' => $langcode,
      ],
      'intro' => [
        '#type' => 'processed_text',
        '#text' => $this->t("@sender-name (@sender-url) has sent you a message via your contact form at @site-name.", $variables, $options),
        '#langcode' => $langcode,
      ],
    ];

    // Only include the opt-out line in the original email and not in the
    // copy to the sender. Also exclude this if the email was sent from a
    // user administrator because they can always send emails even if the
    // contacted user has disabled their contact form.
    if ($key === 'user_mail' && !$sender->hasPermission('administer users')) {
      $build['opt_out'] = [
        '#type' => 'processed_text',
        '#text' => $this->t("If you don't want to receive such messages, you can change your settings at @recipient-edit-url.", $variables, $options),
        '#langcode' => $langcode,
      ];
    }
    $build['message'] = $this->entityTypeManager->getViewBuilder('contact_message')->view($entity, 'mail', $langcode);
    $message->html((string) $this->renderer->render($build));
  }

}
