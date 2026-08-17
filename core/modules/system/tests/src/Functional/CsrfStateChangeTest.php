<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests protection of state changes made by GET routes.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class CsrfStateChangeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'csrf_test', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a GET route cannot write configuration without CSRF protection.
   */
  public function testUnprotectedGetCannotWriteConfig(): void {
    $this->drupalGet('csrf/unprotected-config-write');
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSession()->pageTextContains('unprotected GET request');
  }

  /**
   * Tests that a GET route cannot write an entity without CSRF protection.
   */
  public function testUnprotectedGetCannotWriteEntity(): void {
    $this->drupalGet('csrf/unprotected-entity-write');
    $this->assertSession()->statusCodeEquals(500);
    $this->assertSession()->pageTextContains('unprotected GET request');
  }

}
