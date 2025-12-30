<?php

namespace Drupal\Core\Form;

use Symfony\Component\HttpFoundation\Response;

/**
 * Custom exception to break out of the main request and enforce a response.
 */
class EnforcedResponseException extends \Exception {

  /**
   * The response to be enforced.
   *
   * @var \Symfony\Component\HttpFoundation\Response
   */
  protected $response;

  public function __construct(Response $response, $message = "", $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);

    $this->response = $response;
  }

  /**
   * Return the response to be enforced.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response to be enforced.
   */
  public function getResponse() {
    return $this->response;
  }

}
