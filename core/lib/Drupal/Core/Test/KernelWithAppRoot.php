<?php

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;

/**
 * Kernel which allows setting the app root.
 *
 * This is for use by tests which test the DrupalKernel class but need to set
 * the app root.
 */
class KernelWithAppRoot extends DrupalKernel {

  /**
   * The app root.
   *
   * This is public to allow it to be set by test code. This must be done before
   * the kernel is instantiated.
   *
   * @var string
   */
  public static $appRoot;

  /**
   * {@inheritdoc}
   */
  public static function getApplicationRoot() {
    // Override the normal app root logic with the static app root if set.
    if (!empty(static::$appRoot)) {
      return static::$appRoot;
    }

    return parent::getApplicationRoot();
  }

  /**
   * {@inheritdoc}
   */
  public function __destruct() {
    // Unset the app root to prevent pollution in later tests.
    static::$appRoot = NULL;
  }

}
