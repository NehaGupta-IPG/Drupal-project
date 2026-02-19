<?php

namespace Drupal\migrate\Plugin;

/**
 * An interface to check for a migrate plugin requirements.
 */
interface RequirementsInterface {

  /**
   * Checks if requirements for this plugin are OK.
   *
   * @throws RequirementsException
   *   Thrown when requirements are not met.
   */
  public function checkRequirements();

}
