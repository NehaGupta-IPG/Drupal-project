<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Performs tests on to links that use the 'use-ajax' class.
 *
 * @group Ajax
 */
class LinkTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test links that use the 'use-ajax' class.
   */
  public function testLinks(): void {
    // Visit the page with test links.
    $this->drupalGet('ajax-test/links');

    // Assert the default content is shown in the wrapper.
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-content-wrapper', 'Default');
    // Click the link to replace the wrapper content.
    $this->getSession()->getPage()->clickLink('Link 1 (test replace)');
    // Assert the progress type is set to 'throbber' by default.
    $this->assertNotNull($this->assertSession()->waitForElement('css', '.ajax-progress-throbber'));
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Assert the wrapper content is replaced.
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-content-wrapper', 'The content has been replaced!');
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-ajax-response-wrapper', 'Default');
    $this->assertTrue($this->getSession()->evaluateScript('jQuery(".link-one").is(":focus")'));

    // Assert the progress type can be changed to 'fullscreen'.
    $this->drupalGet('ajax-test/links');
    $this->getSession()->getPage()->clickLink('Link 2 (test progress type)');
    $this->assertNotNull($this->assertSession()->waitForElement('css', '.ajax-progress-fullscreen'));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-content-wrapper', 'The content has been replaced!');
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-ajax-response-wrapper', 'Default');
    $this->assertTrue($this->getSession()->evaluateScript('jQuery(".link-two").is(":focus")'));

    // Assert the progress type can be changed to an empty string.
    $this->drupalGet('ajax-test/links');
    $this->getSession()->getPage()->clickLink('Link 3 (test progress type empty)');
    $this->assertNull($this->assertSession()->waitForElement('css', '.ajax-progress-throbber'));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-content-wrapper', 'The content has been replaced!');
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-ajax-response-wrapper', 'Default');
    $this->assertTrue($this->getSession()->evaluateScript('jQuery(".link-three").is(":focus")'));

    // Assert the focus can be changed.
    $this->drupalGet('ajax-test/links');
    $this->getSession()->getPage()->clickLink('Link 4 (test focus change)');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-content-wrapper', 'The content has been replaced!');
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-ajax-response-wrapper', 'Default');
    $this->assertTrue($this->getSession()->evaluateScript('jQuery("#ajax-test-link-content-wrapper").is(":focus")'));

    // Assert an AJAX response takes precedence over attributes set on the link.
    $this->drupalGet('ajax-test/links');
    $this->getSession()->getPage()->clickLink('Link 5 (test AJAX response)');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-content-wrapper', 'Default');
    $this->assertSession()->elementTextContains('css', '#ajax-test-link-ajax-response-wrapper', 'The content has been replaced!');
    $this->assertTrue($this->getSession()->evaluateScript('jQuery("#ajax-test-link-ajax-response-wrapper").is(":focus")'));
  }

}
