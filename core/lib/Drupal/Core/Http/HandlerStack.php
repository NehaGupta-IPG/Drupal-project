<?php

namespace Drupal\Core\Http;

use GuzzleHttp\HandlerStack as GuzzleHandlerStack;
use GuzzleHttp\TransportSharing;
use GuzzleHttp\Utils;

/**
 * Helper class to create a HandlerStack with non-default handler options.
 */
class HandlerStack extends GuzzleHandlerStack {

  /**
   * {@inheritdoc}
   */
  public static function create(?callable $handler = NULL): GuzzleHandlerStack {
    // Opportunistically enable persistent sharing of connections, DNS and TLS
    // sessions across multiple executions.
    return parent::create($handler ?: Utils::chooseHandler(['transport_sharing' => TransportSharing::PERSISTENT_PREFER]));
  }

}
