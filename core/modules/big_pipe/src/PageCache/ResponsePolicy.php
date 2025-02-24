<?php

namespace Drupal\big_pipe\PageCache;

use Drupal\big_pipe\Render\BigPipeResponse;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents caching of BigPipe responses.
 */
class ResponsePolicy implements ResponsePolicyInterface {
  /**
   * {@inheritdoc}
   */
  public function check(Response $response, Request $request) {
    if ($response instanceof BigPipeResponse) {
      return static::DENY;
    }
  }
}
