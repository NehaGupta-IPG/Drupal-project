<?php

declare(strict_types=1);

namespace Drupal\error_test\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * A response that throws an exception when sendContent() is called.
 */
class SendContentExceptionResponse extends Response {

  /**
   * {@inheritdoc}
   */
  public function sendContent(): static {
    throw new \Exception('Exception in sendContent().');
  }

}
