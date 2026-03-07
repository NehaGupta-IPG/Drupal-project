<?php

namespace Drupal\mailer\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Mailer\EmailManager;
use Drupal\Core\Mailer\EmailPluginInterface;

class MailerThemeHooks {

  public function __construct(
    protected EmailManager $emailManager,
  ) {

  }

  #[Hook('theme')]
  public function theme() : array {
    return $this->emailManager->getThemeImplementations();
  }

  public function preprocessEmail(array &$variables): void {
    /** @var EmailPluginInterface $email */
    $email = $variables['elements']['#email'] ?? NULL;
    if ($email !== NULL) {
      $variables['params'] = $email->getParams() ?? [];
      $variables['langcode'] = $email->getLangcode();
      $variables['email'] = $email;
    }
  }

}
