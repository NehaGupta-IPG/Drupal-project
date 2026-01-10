<?php

namespace Drupal\rest;

use Symfony\Component\HttpFoundation\Response;

/**
 * A response that does not contain cacheability metadata.
 *
 * Used when resources are modified by a request: responses to unsafe requests
 * (POST/PATCH/DELETE) can never be cached.
 *
 * @see \Drupal\rest\ResourceResponse
 */
class ModifiedResourceResponse extends Response implements ResourceResponseInterface {

  use ResourceResponseTrait;

  public function __construct($data = NULL, $status = 200, $headers = []) {
    $this->responseData = $data;
    parent::__construct('', $status, $headers);
  }

}
