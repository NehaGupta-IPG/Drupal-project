<?php

namespace Drupal\Core\Test\HttpClientMiddleware;

use Drupal\Core\Test\TestErrorLogger;
use Drupal\Core\Utility\Error;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Overrides the User-Agent HTTP header for outbound HTTP requests.
 */
class TestHttpClientMiddleware {

  /**
   * HTTP middleware that replaces the user agent for test requests.
   */
  public function __invoke() {
    // If the database prefix is being used to run the tests in a copied
    // database, then set the User-Agent header to the database prefix so that
    // any calls to other Drupal pages will run the test-prefixed database. The
    // user agent is used to ensure that multiple testing sessions running at
    // the same time won't interfere with each other as they would if the
    // database prefix were stored statically in a file or database variable.
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        if ($user_agent = drupal_generate_test_ua(drupal_valid_test_ua())) {
          $request = $request
            ->withHeader('User-Agent', $user_agent)
            ->withHeader('X-Drupal-Test-Request-From-Runner', TRUE);

        }
        return $handler($request, $options)
          ->then(function (ResponseInterface $response) {
            if (!drupal_valid_test_ua()) {
              return $response;
            }
            if (!empty($response->getHeader('X-Drupal-Wait-Terminate')[0])) {
              $lock = \Drupal::lock();
              if (!$lock->acquire('test_wait_terminate')) {
                $lock->wait('test_wait_terminate');
              }
              $lock->release('test_wait_terminate');
            }
            $headers = $response->getHeaders();
            foreach ($headers as $header_name => $header_values) {
              if (preg_match('/^X-Drupal-Assertion-[0-9]+$/', $header_name, $matches)) {
                trigger_error('Using headers to communicate errors in the system under test is deprecated in drupal:11.4.0 and removed from drupal:13.0.0. Use \Drupal\Core\Test\TestErrorLogger instead. See https://www.drupal.org/node/3581815', E_USER_DEPRECATED);
                foreach ($header_values as $header_value) {
                  $parameters = unserialize(urldecode($header_value));
                  if (count($parameters) === 3) {
                    if ($parameters[1] === 'User deprecated function') {
                      // Fire the same deprecation message to allow it to be
                      // collected by PHPUnit.
                      // phpcs:ignore Drupal.Semantics.FunctionTriggerError
                      @trigger_error((string) $parameters[0], E_USER_DEPRECATED);
                    }
                    else {
                      throw new \Exception($parameters[1] . ': ' . $parameters[0] . "\n" . Error::formatBacktrace([$parameters[2]]));
                    }
                  }
                  else {
                    throw new \Exception('Error thrown with the wrong amount of parameters.');
                  }
                }
              }
            }

            TestErrorLogger::reTriggerErrors();
            return $response;
          });
      };
    };
  }

}
