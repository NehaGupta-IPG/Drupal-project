<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Common;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests XSS filtering.
 *
 * @see \Drupal\Component\Utility\Xss::filter()
 *
 * @group Common
 */
class XssUnitTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

}
