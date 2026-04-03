<?php

namespace Drupal\Core\Test\Http;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class TestClientFactory extends ClientFactory {

  public $httpHandler;

  public function fromOptions(array $config = []) {
    // Return a Guzzle client with a mocked handler.
    $mocked_client = new Client([
      'handler' => [$this, 'httpHandler'],
    ]);

    return $mocked_client;
  }

  public function httpHandler(Request $request) {
    return ($this->httpHandler)($request);
  }



}
