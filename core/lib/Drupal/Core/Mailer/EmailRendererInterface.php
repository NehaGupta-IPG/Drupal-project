<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Symfony\Component\Mime\Email;

/**
 * A service which renders emails according to an email definition.
 */
interface EmailRendererInterface {

  /**
   * Renders an email according to an email definition and parameters.
   *
   * @param \Drupal\Core\Mailer\EmailDefinition $definition
   *   The email definition.
   * @param array $params
   *   The email parameters.
   * @param string $langcode
   *   The language to use for rendering.
   */
  public function render(EmailDefinition $definition, array $params, string $langcode): Email;

}
