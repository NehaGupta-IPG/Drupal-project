<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

/**
 * A value object representing email controller parameters.
 */
class EmailControllerParams {

  /**
   * @param \Drupal\Core\Mailer\EmailDefinition $definition
   *   The email plugin definiton.
   * @param array $attributes
   *   Email parameters merged with defaults from the plugin definition.
   * @param array $environment
   *   Environment parameters for the controller invocation.
   */
  public function __construct(
    public readonly EmailDefinition $definition,
    public array $attributes,
    public array $environment,
  ) {
  }

}
