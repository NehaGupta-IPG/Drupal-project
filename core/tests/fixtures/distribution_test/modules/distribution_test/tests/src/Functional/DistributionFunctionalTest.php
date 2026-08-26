<?php

declare(strict_types=1);

namespace Drupal\Tests\distribution_test\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the distribution package with a Functional test.
 */
#[Group('distribution_test')]
#[RunTestsInSeparateProcesses]
class DistributionFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the site can be installed and visited.
   */
  public function testSite(): void {
    $this->drupalGet('user/login');
    $this->assertSession()->statusCodeEquals(200);
  }

}
