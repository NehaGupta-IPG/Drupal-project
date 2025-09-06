<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Render\RenderableInterface;
use Symfony\Component\Mime\Email;

/**
 * Interface for email plugins.
 */
interface EmailPluginInterface extends PluginInspectionInterface {

  /**
   * Builds the email and returns the body as a render array.
   *
   * @param \Symfony\Component\Mime\Email $email
   *   The email instance.
   * @param \Drupal\Core\Mailer\EmailParams $params
   *   The content params.
   */
  public function build(Email $email, EmailParams $params): array|RenderableInterface|null;

}
