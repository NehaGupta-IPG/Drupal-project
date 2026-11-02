<?php

namespace Drupal\Core\ParamConverter;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides an exception class for a request parameter that was not converted.
 */
class ParamNotConvertedException extends \Exception implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  /**
   * The route name that was not converted.
   *
   * @var string
   */
  protected $routeName = "";

  /**
   * The raw parameters that were not converted.
   *
   * @var array
   */
  protected $rawParameters = [];

  /**
   * Constructs the ParamNotConvertedException.
   *
   * @param string $message
   *   The Exception message to throw.
   * @param int $code
   *   The Exception code.
   * @param \Throwable $previous
   *   The previous exception used for the exception chaining.
   * @param string $route_name
   *   The route name that was not converted.
   * @param array $raw_parameters
   *   The raw parameters that were not converted.
   * @param ?\Drupal\Core\Cache\CacheableDependencyInterface $cacheability
   *   (optional) Any cacheable metadata about why the conversion failed.
   */
  public function __construct($message = "", $code = 0, ?\Throwable $previous = NULL, $route_name = "", array $raw_parameters = [], ?CacheableDependencyInterface $cacheability = NULL) {
    parent::__construct($message, $code, $previous);
    $this->routeName = $route_name;
    $this->rawParameters = $raw_parameters;

    if ($cacheability === NULL) {
      // Uncacheable because conversion of the parameter may not have been
      // possible due to dynamic circumstances.
      $cacheability = (new CacheableMetadata())->setCacheMaxAge(0);
    }
    $this->setCacheability($cacheability);
  }

  /**
   * Get the route name that was not converted.
   *
   * @return string
   *   The route name that was not converted.
   */
  public function getRouteName() {
    return $this->routeName;
  }

  /**
   * Get the raw parameters that were not converted.
   *
   * @return array
   *   The raw parameters that were not converted.
   */
  public function getRawParameters() {
    return $this->rawParameters;
  }

}
