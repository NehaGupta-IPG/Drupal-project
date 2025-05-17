<?php

namespace Drupal\Core\Render\Element;

/**
 * Process autocomplete trait.
 */
trait ProcessAutocompleteTrait {

  /**
   * The autocomplete route name.
   *
   * @var string
   */
  protected string $autocomplete_route_name = '';

  /**
   * Autocomplete route parameters.
   *
   * @var array
   */
  protected array $autocomplete_route_parameters = [];

  /**
   * Autocomplete URL query parameters.
   *
   * @var array
   */
  protected array $autocomplete_query_parameters = [];

}
