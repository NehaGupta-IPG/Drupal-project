<?php

namespace Drupal\Core\Test\Http;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class TestClientFactory extends ClientFactory {

  /**
   * The test HTTP handler.
   *
   * @var callable
   */
  protected $httpHandler;

  /**
   * Sets the test HTTP handler.
   *
   * Test classes should call this method to set the callable that handles HTTP
   * requests.
   *
   * @param callable $http_handler
   *   The handler callable, for example, a method on the test class.
   */
  public function setHttpHandler(callable $http_handler): void {
    $this->httpHandler = $http_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function fromOptions(array $config = []) {
    // Return a Guzzle client with a mocked handler. We need to give a method on
    // this class as the handler, rather than $this->httpHandler, because this
    // service is setup before a test class has a chance to call
    // static::setHandler().
    $mocked_client = new Client([
      'handler' => [$this, 'httpHandler'],
    ]);

    return $mocked_client;
  }

  /**
   * Client handler callback.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   The Guzzle request.
   */
  public function httpHandler(Request $request) {
    assert(isset($this->httpHandler), 'TestClientFactory::setHttpHandler must be called before Guzzle HTTP requests are made.');
    return ($this->httpHandler)($request);
  }

}
