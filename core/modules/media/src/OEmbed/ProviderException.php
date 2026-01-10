<?php

namespace Drupal\media\OEmbed;

/**
 * Exception thrown if an oEmbed provider causes an error.
 *
 * @internal
 *   This is an internal part of the oEmbed system and should only be used by
 *   oEmbed-related code in Drupal core.
 */
class ProviderException extends \Exception {

  /**
   * Information about the oEmbed provider which caused the exception.
   *
   * @var \Drupal\media\OEmbed\Provider
   *
   * @see \Drupal\media\OEmbed\ProviderRepositoryInterface::get()
   */
  protected $provider;

  public function __construct($message, ?Provider $provider = NULL, ?\Throwable $previous = NULL) {
    $this->provider = $provider;
    $message = str_replace('@name', $provider ? $provider->getName() : '<unknown>', $message);
    parent::__construct($message, 0, $previous);
  }

}
