<?php

declare(strict_types=1);

namespace Drupal\Core\MailTheme;

/**
 * A value object that identifies a mail template.
 */
final class MailTemplateId {

  /**
   * Creates a mail template ID value object.
   *
   * @param string $provider
   *   The mail template provider (i.e. module).
   * @param string $key
   *   The mail template key.
   */
  public function __construct(
    public readonly string $provider,
    public readonly string $key,
  ) {
  }

}
