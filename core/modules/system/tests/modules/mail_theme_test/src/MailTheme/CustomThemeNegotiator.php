<?php

declare(strict_types=1);

namespace Drupal\mail_theme_test\MailTheme;

use Drupal\Core\MailTheme\MailTemplateId;
use Drupal\Core\MailTheme\MailThemeNegotiatorInterface;

/**
 * Just forces the 'test_theme' theme for 'mail_theme_test_trigger' template ID.
 */
class CustomThemeNegotiator implements MailThemeNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(MailTemplateId $templateId): bool {
    return $templateId->provider === 'mail_theme_test' && $templateId->key === 'trigger';
  }

  /**
   * {@inheritdoc}
   */
  public function determineMailTheme(MailTemplateId $templateId): ?string {
    return 'test_theme';
  }

}
