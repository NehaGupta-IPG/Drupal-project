<?php

namespace Drupal\Core\Http\Exception;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;

/**
 * A cacheable TooManyRequestsHttpException.
 */
class CacheableTooManyRequestsHttpException extends \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(CacheableDependencyInterface $cacheability, $retryAfter = NULL, $message = '', ?\Throwable $previous = NULL, $code = 0) {
    $this->setCacheability($cacheability);
    parent::__construct($retryAfter, $message, $previous, $code);
  }

}
