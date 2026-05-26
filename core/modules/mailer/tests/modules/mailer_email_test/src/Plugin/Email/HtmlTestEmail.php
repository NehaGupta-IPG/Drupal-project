<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Plugin\Email;

use Drupal\Core\Mailer\Attribute\Email;
use Drupal\Core\Mailer\EmailPluginDefault;
use Drupal\Core\Render\RenderableInterface;

/**
 * Html test email plugin.
 */
#[Email(
  id: 'mailer_email_test.html',
)]
class HtmlTestEmail extends EmailPluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubject(): \Stringable|string|null {
    return sprintf('HTML Email (%s)', $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function htmlBody(): RenderableInterface|array|null {
    $build['colors'] = [
      '#markup' => '<p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p>',
    ];
    return $build;
  }

}
