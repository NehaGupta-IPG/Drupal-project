<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer\Plugin\Email;

use Drupal\Core\Mailer\Attribute\Email;
use Drupal\Core\Mailer\EmailPluginBase;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Default class used for emails plugins.
 */
#[Email(
  id: 'email.default',
  label: new TranslatableMarkup('Default'),
  description: new TranslatableMarkup('Default email'),
)]
class EmailPluginDefault extends EmailPluginBase {

  /**
   * {@inheritdoc}
   */
  public function htmlBody(): RenderableInterface|array|null {
    if ($template_id = $this->getHtmlBodyTemplate()) {
      return [
        '#theme' => $template_id,
        '#email' => $this,
      ];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function textBody(): RenderableInterface|array|null {
    if ($template_id = $this->getTextBodyTemplate()) {
      return [
        '#theme' => $template_id,
        '#email' => $this,
      ];
    }
    if (array_key_exists('text', $this->parameters) && $this->parameters['text']) {
      return ['#markup' => $this->parameters['text']];
    }
    return NULL;
  }

}
