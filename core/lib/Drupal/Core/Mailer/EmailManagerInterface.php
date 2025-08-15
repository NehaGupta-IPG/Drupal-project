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
   */
  public function getEmailDefinition(string $id): EmailDefinition;

  /**
   * Returns the email builder.
   */
  public function getBuilder(): EmailBuilderInterface;

  /**
   * Returns a new email instance.
   *
   * @param string $id
   *   The email plugin id.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   */
  public function createEmail(string $id, array $configuration = []): Email;

}
