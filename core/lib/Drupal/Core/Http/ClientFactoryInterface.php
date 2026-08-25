<?php

namespace Drupal\Core\Http;

use GuzzleHttp\HandlerStack;

/**
 * Helper class interface to construct a HTTP client with Drupal specific config.
 */
interface ClientFactoryInterface {

  /**
   * Constructs a new ClientFactory instance.
   *
   * @param \GuzzleHttp\HandlerStack $stack
   *   The handler stack.
   */
  public function __construct(HandlerStack $stack);

  /**
   * Constructs a new client object from some configuration.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client.
   */
  public function fromOptions(array $config = []);

}
