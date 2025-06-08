<?php

declare(strict_types=1);

namespace Drupal\contact;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Provides an experimental class for handling contact mail messages via mailer.
 */
class MailerMailHandler implements MailHandlerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new mailer mail handler object.
   *
   * @param \Symfony\Component\Mailer\MailerInterface $mailer
   *   The mailer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   *
   * @internal
   */
  public function __construct(
    protected MailerInterface $mailer,
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service:'logger.channel.contact')]
    protected LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function sendMailMessages(MessageInterface $message, AccountInterface $sender): void {
    $currentLangcode = $this->languageManager->getCurrentLanguage()->getId();
    $recipientLangcode = $this->languageManager->getDefaultLanguage()->getId();

    $siteEmail = $this->configFactory->get('system.site')->get('mail');
    if (!$message->isPersonal()) {
      $recipients = $message->getContactForm()->getRecipients();
    }
    elseif ($recipient = $message->getPersonalRecipient()) {
      // Send to the user in the user's preferred language.
      $recipients = [$recipient->getEmail()];
      $recipientLangcode = $recipient->getPreferredLangcode();
    }
    else {
      throw new MailHandlerException('Unable to determine message recipient');
    }

    // Send email to the recipient(s).
    $key_prefix = $message->isPersonal() ? 'user' : 'page';
    $email = $message->toEmail($key_prefix . '_mail', $recipientLangcode);
    $this->mailer->send($email->from($siteEmail)->to(...$recipients)->replyTo($message->getSenderMail()));

    // If requested, send a copy to the user, using the current language.
    if ($message->copySender()) {
      $email = $message->toEmail($key_prefix . '_copy', $currentLangcode);
      $this->mailer->send($email->from($siteEmail)->to($message->getSenderMail()));
    }

    // If configured, send an auto-reply, using the current language.
    if (!$message->isPersonal() && $message->getContactForm()->getReply()) {
      // User contact forms do not support an auto-reply message, so this
      // message always originates from the site.
      if (!$message->getSenderMail()) {
        $this->logger->error('Error sending auto-reply, missing sender email address in %contact_form', [
          '%contact_form' => $message->getContactForm()->label(),
        ]);
      }
      else {
        $email = $message->toEmail('page_autoreply', $currentLangcode);
        $this->mailer->send($email->from($siteEmail)->to($message->getSenderMail()));
      }
    }

    if (!$message->isPersonal()) {
      $this->logger->info('%sender-name (@sender-from) sent an email regarding %contact_form.', [
        '%sender-name' => $message->getSenderName(),
        '@sender-from' => $message->getSenderMail(),
        '%contact_form' => $message->getContactForm()->label(),
      ]);
    }
    else {
      $this->logger->info('%sender-name (@sender-from) sent %recipient-name an email.', [
        '%sender-name' => $message->getSenderName(),
        '@sender-from' => $message->getSenderMail(),
        '%recipient-name' => $message->getPersonalRecipient()->getAccountName(),
      ]);
    }
  }

}
