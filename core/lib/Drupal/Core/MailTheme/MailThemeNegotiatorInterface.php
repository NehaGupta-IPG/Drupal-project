<?php

declare(strict_types=1);

namespace Drupal\Core\MailTheme;

/**
 * Defines an interface for classes which determine the theme for an email.
 *
 * To influence the selection of the mail theme, create a new service tagged
 * with 'mail_theme_negotiator'.
 */
interface MailThemeNegotiatorInterface {

  /**
   * Whether this theme negotiator should be used to set the mail theme.
   *
   * @param \Drupal\Core\MailTheme\MailTemplateId $templateId
   *   The mail template ID.
   *
   * @return bool
   *   TRUE if this negotiator should be used or FALSE to let other negotiators
   *   decide.
   */
  public function applies(MailTemplateId $templateId): bool;

  /**
   * Determine the active theme for the email.
   *
   * @param \Drupal\Core\MailTheme\MailTemplateId $templateId
   *   The mail template ID.
   *
   * @return string|null
   *   The name of the theme, or NULL if other negotiators, like the configured
   *   default one, should be used instead.
   */
  public function determineMailTheme(MailTemplateId $templateId): ?string;

}
