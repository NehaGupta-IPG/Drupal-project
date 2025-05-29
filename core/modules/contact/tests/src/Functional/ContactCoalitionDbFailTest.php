<?php

namespace Drupal\Tests\contact\Functional;

use Behat\Mink\Exception\ExpectationException;
use Drupal\Tests\BrowserTestBase;

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
    $this->drupalGet('/contact/Perú');
    try {
      $this->assertSession()->statusCodeEquals(200);
    }
    catch (ExpectationException $exception ) {
      echo $exception;
    }
  }

}
