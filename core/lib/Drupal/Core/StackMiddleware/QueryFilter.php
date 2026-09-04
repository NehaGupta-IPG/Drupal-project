<?php

namespace Drupal\Core\StackMiddleware;

use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Filters out irrelevant query parameters.
 *
 * Requests often contain tracking parameters such as utm_* and others.
 *
 * To use this, set query_parameter_filters in your settings.php, each
 * item may contain regular expression placeholders.
 *
 * @code
 * $settings['query_parameter_filters'] = [
 *   'utm.*',
 * ];
 * @endcode
 */
readonly class QueryFilter implements HttpKernelInterface {

  /**
   * Constructs a new ContentLength instance.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   The wrapped HTTP kernel.
   */
  public function __construct(
    protected HttpKernelInterface $httpKernel,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    $query_string = $request->server->get('QUERY_STRING');

    if ($query_string && $query_parameter_filters = Settings::get('query_parameter_filters', ['utm.*'])) {
      parse_str($query_string, $query);
      foreach ($query as $key => $value) {
        foreach ($query_parameter_filters as $query_parameter_filter) {
          if (preg_match('/' . $query_parameter_filter . '/', $key)) {
            unset($query[$key]);
            $request->query->remove($key);
          }
        }
      }
      $new_query_string = http_build_query($query, '', '&');
      $request_uri = $request->server->get('REQUEST_URI');
      if ($request_uri && str_contains($request_uri, $query_string)) {
        $request->server->set('REQUEST_URI', str_replace($query_string, $new_query_string, $request_uri));
      }
      $request->server->set('QUERY_STRING', $new_query_string);
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
