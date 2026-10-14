<?php

declare(strict_types=1);

namespace Drupal\Core\Http;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\TransportSharing;
use GuzzleHttp\Utils;

/**
 * Creates a HandlerStack with non-default handler options.
 */
class HandlerStackFactory {

  /**
   * Creates a handler stack that can be used by clients.
   *
   * The returned handler will wrap the provided handler or use the most
   * appropriate default handler for your system. The returned HandlerStack
   * has support for authentication, cookies, redirects, HTTP error
   * exceptions, and preparing a body before sending.
   *
   * The returned handler stack can be passed to a client in the "handler"
   * option.
   *
   * @param (callable(\Psr\Http\Message\RequestInterface, array<array-key, mixed>): \GuzzleHttp\Promise\PromiseInterface<\Psr\Http\Message\ResponseInterface, mixed>)|null $handler
   *   HTTP handler function to use with the stack. If no handler is provided,
   *   the best handler for your system will be utilized.
   *
   * @return \GuzzleHttp\HandlerStack<callable(\Psr\Http\Message\RequestInterface, array<array-key, mixed>): \GuzzleHttp\Promise\PromiseInterface<\Psr\Http\Message\ResponseInterface, mixed>>
   *   The handler stack.
   */
  public static function create(?callable $handler = NULL): HandlerStack {
    // Opportunistically enable persistent sharing of connections, DNS and TLS
    // sessions across multiple executions.
    return HandlerStack::create($handler ?: Utils::chooseHandler(['transport_sharing' => TransportSharing::PERSISTENT_PREFER]));
  }

}
