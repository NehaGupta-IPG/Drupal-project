<?php

declare(strict_types=1);

namespace Drupal\TestSite;

use Drupal\Tests\RandomGeneratorTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Assert;

/**
 * Setup file used by TestSiteApplicationTest.
 *
 * @see \Drupal\KernelTests\Scripts\TestSiteApplicationTest
 */
class TestSiteInstallTestScript extends Assert implements TestSetupInterface {

  use RandomGeneratorTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function setup() {
    \Drupal::service('module_installer')->install(['test_page_test']);
    $this->createUser();
  }

}
