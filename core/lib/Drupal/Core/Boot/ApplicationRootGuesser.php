<?php

namespace Drupal\Core\Boot;

/**
 * Application root guesser.
 */
class ApplicationRootGuesser {

  /**
   * Guess.
   */
  public function __invoke(): string {
    // Determine the application root by:
    // - Removing the namespace directories from the path.
    // - Getting the path to the directory two levels up from the path
    //   determined in the previous step.
    return dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__)), 2);
  }

}
