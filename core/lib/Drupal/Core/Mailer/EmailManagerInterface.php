<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Symfony\Component\Mime\Email;

/**
 * Defines the interface for a plugin manager to collect information on emails.
 */
interface EmailManagerInterface {

  /**
   * Returns the email definition.
   *
   * @param string $id
   *   The email plugin id.
   * @param array|\Closure|null $defaults
   *   The call site defaults to be merged with the plugin definition parameter
   *   defaults. A closure is converted into ['_controller' => $defaults] before
   *   merged with plugin defaults.
   */
  public function getEmailDefinition(string $id, array|\Closure|null $defaults = NULL): EmailDefinition;

  /**
   * Returns a new email instance.
   *
   * @param string $id
   *   The email plugin id.
   */
  public function createEmail(string $id): Email;

}
