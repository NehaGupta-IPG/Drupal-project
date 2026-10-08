<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Plugin\Email;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Mailer\Attribute\Email;
use Drupal\Core\Mailer\EmailPluginDefault;
use Drupal\Core\Render\RenderableInterface;

/**
 * Minimal test email plugin.
 */
#[Email(
  id: 'mailer_email_test.minimal',
)]
class MinimalTestEmail extends EmailPluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubject(): \Stringable|string|null {
    return sprintf('Minimal Test Email (%s)', $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function htmlBody(): RenderableInterface|array|null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function textBody(): \Stringable {
    $id = $this->getPluginId();
    $langcode = $this->getLangcode();
    return new FormattableMarkup('@method(id: @id, langcode: @langcode)', [
      '@method' => __METHOD__,
      '@id' => $id,
      '@langcode' => $langcode,
    ]);
  }

}
