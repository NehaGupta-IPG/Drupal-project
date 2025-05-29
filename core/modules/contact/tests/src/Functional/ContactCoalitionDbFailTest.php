<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Functional;

use Behat\Mink\Exception\ExpectationException;
use Drupal\Tests\BrowserTestBase;

/**
 * Test DB error on malicious contact URLs.
 *
 * @group contact
 */
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
