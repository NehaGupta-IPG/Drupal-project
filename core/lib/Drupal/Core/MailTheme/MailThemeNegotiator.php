<?php

declare(strict_types=1);

namespace Drupal\Core\MailTheme;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Provides a class which determines the theme for an email.
 *
 * Uses MailThemeNegotiatorInterface objects which are passed in using the
 * 'mail_theme_negotiator' tag.
 */
class MailThemeNegotiator implements MailThemeNegotiatorInterface {

  /**
   * Constructs a new MailThemeNegotiator.
   *
   * @param iterable<\Drupal\Core\MailTheme\MailThemeNegotiatorInterface> $negotiators
   *   An array of negotiators.
   */
  public function __construct(
    #[AutowireIterator(tag: 'mail_theme_negotiator')]
    protected iterable $negotiators,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(MailTemplateId $templateId): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineMailTheme(MailTemplateId $templateId): ?string {
    $theme = NULL;

    foreach ($this->negotiators as $negotiator) {
      if ($negotiator->applies($templateId)) {
        $theme = $negotiator->determineMailTheme($templateId);
        if ($theme !== NULL) {
          break;
        }
      }
    }

    return $theme;
  }

}
