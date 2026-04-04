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
   * Expected HTTP response statuses.
   */
  protected array $expectedHttpStatuses = [];

  /**
   * Sets an expected HTTP response status for a future request.
   *
   * This will fail the test if the actual response status does not match.
   *
   * If this is not called for a path, the response is expected to be 200.
   *
   * @param string $path
   *   The path for the expectation.
   * @param int $status
   *   The expected HTTP status.
   */
  public function setExpectedHttpStatus(string $path, int $status): void {
    $this->expectedHttpStatuses[$path] = $status;
  }

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

    $expected_status = $this->expectedHttpStatuses[$path] ?? SymfonyResponse::HTTP_OK;
    Assert::assertEquals($expected_status, $response->getStatusCode());

    // Convert the Symfony response to a Guzzle response.
    $psr17_factory = new HttpFactory();
    $psr_http_factory = new PsrHttpFactory($psr17_factory, $psr17_factory, $psr17_factory, $psr17_factory);
    $guzzle_response = $psr_http_factory->createResponse($response);

    return $guzzle_response;
  }

}
