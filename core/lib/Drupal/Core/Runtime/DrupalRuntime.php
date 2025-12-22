<?php

declare(strict_types=1);

namespace Drupal\Core\Runtime;

use Symfony\Component\Runtime\GenericRuntime;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * The custom Drupal framework runtime.
 */
class DrupalRuntime extends SymfonyRuntime {

  /**
   * Construct a Drupal runtime.
   *
   * In addition to the options managed by GenericRuntime, SymfonyRuntime
   * accepts dotenv related options and Drupal at this time does not support
   * dotenv so the role of this class is to bypass that constructor.
   */
  public function __construct() {
    GenericRuntime::__construct();
  }

}
