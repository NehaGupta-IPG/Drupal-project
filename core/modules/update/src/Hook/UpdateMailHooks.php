<?php

namespace Drupal\update\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Mail hook implementations for the update module.
 */
class UpdateMailHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_mail().
   *
   * Constructs the email notification message when the site is out of date.
   *
   * @see \Drupal\Core\Mail\MailManagerInterface::mail()
   * @see \Drupal\update\Hook\UpdateCronHooks::notify()
   * @see _update_message_text()
   * @see \Drupal\update\UpdateManagerInterface
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params): void {
    $langcode = $message['langcode'];
    $language = $this->languageManager->getLanguage($langcode);
    $site_name = $this->configFactory
      ->get('system.site')
      ->get('name');
    $message['subject'] .= $this->t(
      'New release(s) available for @site_name',
      ['@site_name' => $site_name],
      ['langcode' => $langcode],
    );
    foreach ($params as $msg_type => $msg_reason) {
      $message['body'][] = _update_message_text($msg_type, $msg_reason, $langcode);
    }
    $update_url = Url::fromRoute(
      'update.status',
      [],
      [
        'absolute' => TRUE,
        'language' => $language,
      ],
    )->toString();
    $message['body'][] = $this->t(
      'See the available updates page for more information:',
      [],
      ['langcode' => $langcode],
    ) . "\n" . $update_url;
    $settings_url = Url::fromRoute('update.settings', [], ['absolute' => TRUE])->toString();
    if ($this->configFactory->get('update.settings')->get('notification.threshold') == 'all') {
      $message['body'][] = $this->t(
        'Your site is currently configured to send these emails when any updates are available. To get notified only for security updates, @url.',
        ['@url' => $settings_url]
      );
    }
    else {
      $message['body'][] = $this->t(
        'Your site is currently configured to send these emails only when security updates are available. To get notified for any available updates, @url.',
        ['@url' => $settings_url]
      );
    }
  }

}
