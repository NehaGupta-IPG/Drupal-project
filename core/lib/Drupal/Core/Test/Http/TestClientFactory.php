<?php

namespace Drupal\Core\Test\Http;

use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Test HTTP client factory which sends HTTP requests back into the site.
 *
 * This allows tests to set up mocked versions of external sites to return test
 * data.
 */
class TestClientFactory extends ClientFactory {

  /**
   * {@inheritdoc}
   */
  public function fromOptions(array $config = []) {
    // Return a Guzzle client with a mocked handler.
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
  public function httpHandler(GuzzleRequest $request): GuzzleResponse {
    $path = $request->getUri()->getPath();

    // Can't use the Symfony PSR-7 Bridge to convert, as the Guzzle
    // request is an outgoing request, and the HTTP kernel wants an
    // incoming request, and these have different interfaces in PSR-7.
    $symfony_request = SymfonyRequest::create($path);

    $http_kernel = \Drupal::service('http_kernel');
    $response = $http_kernel->handle($symfony_request);

    Assert::assertEquals(SymfonyResponse::HTTP_OK, $response->getStatusCode());

    // Convert the Symfony response to a Guzzle response.
    $psr17_factory = new HttpFactory();
    $psr_http_factory = new PsrHttpFactory($psr17_factory, $psr17_factory, $psr17_factory, $psr17_factory);
    $guzzle_response = $psr_http_factory->createResponse($response);

    return $guzzle_response;
  }

}
