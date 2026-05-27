<?php

namespace Drupal\Core\Routing\Exception;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;

/**
 * A cacheable ResourceNotFoundException.
 */
class CacheableResourceNotFoundException extends \Symfony\Component\Routing\Exception\ResourceNotFoundException implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(CacheableDependencyInterface $cacheability, $message = '', $code = 0, ?\Throwable $previous = NULL) {
    $this->setCacheability($cacheability);
    parent::__construct($message, $code, $previous);
  }

}
