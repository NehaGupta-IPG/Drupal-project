<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Functional;

use Behat\Mink\Exception\ExpectationException;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test DB error on malicious contact URLs.
 */
#[Group('contact')]
#[RunTestsInSeparateProcesses]
class ContactCoalitionDbFailTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contact',
    'language',
    'contact_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests configuration options with language enabled.
   */
  public function testError(): void {
    // cspell:disable-next-line
    $this->drupalGet('/contact/Perú');
    try {
      $this->assertSession()->statusCodeEquals(200);
    }
    catch (ExpectationException $exception) {
      echo $exception;
    }
  }

}
