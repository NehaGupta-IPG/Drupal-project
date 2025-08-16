<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

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

}
