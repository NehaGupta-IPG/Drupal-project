<?php

declare(strict_types=1);

namespace Drupal\Core\Runtime;

use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Runtime\GenericRuntime;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * The custom Drupal framework runtime.
 *
 * This runtime understands how to provide the arguments that Drupal
 * front-controllers need to instantiate the DrupalKernel.
 */
class DrupalRuntime extends GenericRuntime {

  /**
   * {@inheritdoc}
   */
  public function getRunner(?object $application): RunnerInterface {
    if ($application instanceof DrupalKernelInterface) {
      return new DrupalKernelRunner($application, Request::createFromGlobals());
    }

    return parent::getRunner($application);
  }

}
