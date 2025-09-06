<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

/**
 * Defines an interface for the email builder factory.
 */
interface EmailFactoryInterface {

  /**
   * Creates and returns a new email builder for a given email id.
   *
   * @param string $id
   *   The email id.
   */
  public function create(string $id): EmailBuilderInterface;

}
