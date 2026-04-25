<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Plugin\Email;

use Drupal\Core\Mailer\Attribute\Email;
use Drupal\Core\Mailer\EmailPluginBase;
use Drupal\Core\Render\RenderableInterface;

/**
 * Minimal test email plugin.
 */
#[Email(
  id: 'mailer_email_test.minimal',
)]
class MinimalTestEmail extends EmailPluginBase {

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
  public function textBody(): RenderableInterface|array|null {
    $id = $this->getPluginId();
    $module = $this->getModule();
    $key = $this->getKey();
    $langcode = $this->getLangcode();
    return [
      '#type' => 'markup',
      '#markup' => sprintf('%s(id: %s, module: %s, key: %s, langcode: %s)', __METHOD__, $id, $module, $key, $langcode),
    ];
  }

}
