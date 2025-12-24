<?php

namespace Drupal\Core;

/**
 * The app context.
 */
readonly class AppContext {

  public function __construct(public array $options = []) {

  }

  /**
   * Get the environment.
   */
  public function getEnvironment(): string {
    return isset($this->options['env_var_name']) && isset($_ENV[$this->options['env_var_name']]) ?
      $_ENV[$this->options['env_var_name']] :
      'prod';
  }

}
