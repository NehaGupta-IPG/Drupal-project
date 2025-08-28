<?php

declare(strict_types=1);

namespace Drupal\Core\MailTheme;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Determines the default mail theme negotiator.
 */
class DefaultNegotiator implements MailThemeNegotiatorInterface {

  public function __construct(protected ConfigFactoryInterface $configFactory) {
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
    return $this->configFactory->get('system.theme')->get('default');
  }

}
