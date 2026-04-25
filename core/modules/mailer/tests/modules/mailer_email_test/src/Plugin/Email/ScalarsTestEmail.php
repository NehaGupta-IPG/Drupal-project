<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Plugin\Email;

use Drupal\Core\Mailer\Attribute\Email;
use Drupal\Core\Mailer\EmailPluginBase;
use Drupal\Core\Render\RenderableInterface;

/**
 * Scalars test email plugin.
 */
#[Email(
  id: 'mailer_email_test.scalars',
  default_subject: 'Scalar Params Test Email',
)]
class ScalarsTestEmail extends EmailPluginBase {

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
    ['text' => $text, 'number' => $number, 'status' => $status] = $this->getParameters();
    return [
      '#type' => 'markup',
      '#markup' => sprintf('%s(text: %s, number: %s, status: %s)', __METHOD__, $text, $number, $status ? 'true' : 'false'),
    ];
  }

}
