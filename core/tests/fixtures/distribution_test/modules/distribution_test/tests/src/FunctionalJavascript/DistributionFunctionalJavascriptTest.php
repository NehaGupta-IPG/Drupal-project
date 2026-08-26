<?php

declare(strict_types=1);

namespace Drupal\Tests\distribution_test\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the distribution package with a FunctionalJavascript test.
 */
#[Group('distribution_test')]
#[RunTestsInSeparateProcesses]
class DistributionFunctionalJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the site can be installed and visited.
   */
  public function testSite(): void {
    $this->drupalGet('user/login');
    $this->assertSession()->pageTextContains('Log in');
  }

}
