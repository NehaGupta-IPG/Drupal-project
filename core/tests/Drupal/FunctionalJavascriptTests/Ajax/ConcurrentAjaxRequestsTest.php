<?php

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests concurrency of AJAX requests.
 *
 * @group Ajax
 */
class ConcurrentAjaxRequestsTest extends WebDriverTestBase
{

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ajax_test',
    'hold_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that AJAX blocking requests do not allow concurrent requests.
   */
  public function testBlockingRequests()
  {
    $this->drupalGet('ajax-test/concurrent-requests-form');

    $assert_session = $this->assertSession();
    // Execute a first, slow AJAX request. Since it's a button, the request is
    // marked as blocking.
    $assert_session->buttonExists('Slow request')->press();
    // Trying to execute another request should have no effect.
    $assert_session->buttonExists('Fast request')->press();
    // Wait for requests to be completed.
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->buttonExists('Submit')->press();
    // The slow counter request should be one.
    $assert_session->pageTextContains('Submitted slow counter: 1.');
    // No fast request should have been executed.
    $assert_session->pageTextContains('Submitted fast counter: 0.');

    // Blocking requests should not allow other blocking requests, but
    // non-blocking ones can be run.
    $this->drupalGet('ajax-test/concurrent-requests-form');
    $assert_session->buttonExists('Slow request')->press();
    $this->clickLink('Non-blocking request');
    // Wait for the non-blocking request to complete successfully.
    $this->assertTrue($assert_session->waitForText('Current theme: stark', 2000));
    // Blocking requests are still not allowed.
    $assert_session->buttonExists('Fast request')->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->buttonExists('Submit')->press();
    $assert_session->pageTextContains('Submitted slow counter: 1.');
    $assert_session->pageTextContains('Submitted fast counter: 0.');

    // Test that blocking requests correctly allow new AJAX requests to be
    // executed after they are completed.
    $this->drupalGet('ajax-test/concurrent-requests-form');
    $assert_session->buttonExists('Slow request')->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->buttonExists('Fast request')->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->buttonExists('Submit')->press();
    $assert_session->pageTextContains('Submitted slow counter: 1.');
    $assert_session->pageTextContains('Submitted fast counter: 1.');

    // Test that AJAX requests that are a result of another AJAX request are
    // correctly executed.
    $this->drupalGet('ajax-test/concurrent-requests-form');
    $assert_session->buttonExists('Chained requests')->press();
    $assert_session->assertWaitOnAjaxRequest();
    // The chained requests button triggered a click on the non-blocking link.
    $assert_session->pageTextContains('Current theme: stark');
  }

  /**
   * Tests that non-blocking AJAX requests allow concurrent requests.
   */
  public function testNonBlockingRequests()
  {
    $this->drupalGet('ajax-test/concurrent-requests-form');

    $assert_session = $this->assertSession();
    // Hold all the responses.
    hold_test_response(TRUE);
    // Launch a non-blocking request and a blocking request.
    $this->clickLink('Non-blocking request');
    $assert_session->buttonExists('Fast request')->press();
    // Release all the paused responses.
    hold_test_response(FALSE);
    $assert_session->assertWaitOnAjaxRequest();
    // Verify that the link AJAX request has been completed.
    $assert_session->pageTextContains('Current theme: stark');
    $assert_session->buttonExists('Submit')->press();
    // Since the link request was a non-blocking, the button request has been
    // executed too.
    $assert_session->pageTextContains('Submitted fast counter: 1.');
  }

}
