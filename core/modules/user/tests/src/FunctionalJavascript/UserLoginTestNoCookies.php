<?php

declare(strict_types=1);

namespace Drupal\Tests\user\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Ensure that login works as expected.
 *
 * @group user
 */
class UserLoginTestNoCookies extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * With cookies disabled, unable to read 'sessionStorage' so we need to
   * disabled failOnJavascriptConsoleErrors otherwise an error occurs.
   *
   * @see \Drupal\FunctionalJavascriptTests\WebDriverTestBase::failOnJavaScriptErrors()
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * {@inheritdoc}
   *
   * With cookies disabled, unable to read 'sessionStorage' so we need to
   * disabled errorOnJavascriptDeprecationWarnings otherwise an error occurs.
   *
   * @see \Drupal\FunctionalJavascriptTests\WebDriverTestBase::tearDown()
   */
  protected $errorOnJavascriptDeprecationWarnings = FALSE;

  /**
   * {@inheritdoc}
   *
   * Override the default mink driver args to disabled cookies.
   *
   * @return string
   *   The JSON encoded Mink driver arguments with updated cookie settings.
   */
  protected function getMinkDriverArgs(): string {
    $parent_driver_args = parent::getMinkDriverArgs();
    $driver_args = json_decode($parent_driver_args, TRUE);
    // Support legacy key.
    $chrome_options_key = isset($driver_args[1]['chromeOptions']) ? 'chromeOptions' : 'goog:chromeOptions';
    $driver_args[1][$chrome_options_key]['prefs']['profile.default_content_setting_values.cookies'] = 2;
    return json_encode($driver_args);
  }

  /**
   * Tests Login with a browser that denies cookies.
   */
  public function testCookiesNotAccepted(): void {
    // Ensure when cookies are disabled the user is redirected to the verify
    // cookies page and displayed the error message.
    $page = $this->getSession()->getPage();
    $this->drupalGet('user/login');
    $page->fillField('name', 'admin');
    $page->fillField('pass', 'admin');
    $page->pressButton('Log in');
    $this->assertEquals($this->baseUrl . '/user/verify-cookies?destination=/user/1', $this->getUrl());
    $this->assertSession()
      ->pageTextContains('To log in to this site, your browser must accept cookies from the domain');

    // Ensure accessing url directly with the check_logged_in query parameter
    // still displays the error message.
    $this->drupalGet('/user/1', ['query' => ['check_logged_in' => 1]]);
    $this->assertEquals($this->baseUrl . '/user/verify-cookies?destination=/user/1', $this->getUrl());

    // Ensure presence of both check_logged_in and destination query parameters
    // is handled correctly.
    $this->drupalGet('user/login', [
      'query' => [
        'check_logged_in' => 1,
        'destination' => '/',
      ],
    ]);
    $this->assertEquals($this->baseUrl . '/user/verify-cookies?destination=/user/login%3Fdestination%3D/', $this->getUrl());
    $this->assertSession()
      ->pageTextContains('To log in to this site, your browser must accept cookies from the domain');
  }

}
