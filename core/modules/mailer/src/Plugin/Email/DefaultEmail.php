<?php

namespace Drupal\mailer\Plugin\Email;

use Drupal\Core\Mailer\Attribute\Email;
use Drupal\Core\Mailer\EmailPluginDefault;
use Drupal\Core\Mailer\EmailPluginInterface;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Simple email plugin.
 */
#[Email(
  id: 'mailer.default',
  label: new TranslatableMarkup('Default'),
  description: new TranslatableMarkup('Default email'),
)]
class DefaultEmail extends EmailPluginDefault implements EmailPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function htmlBody(): RenderableInterface|array|null {
    if ($body = $this->getParam('html')) {
      return $body;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function textBody(): RenderableInterface|array|null {
    if ($body = $this->getParam('text')) {
      return $body;
    }
    return NULL;
  }

}
