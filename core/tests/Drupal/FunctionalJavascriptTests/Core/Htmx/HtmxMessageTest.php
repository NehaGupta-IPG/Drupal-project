<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core\Htmx;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that messages delivered by HTMX work as expected.
 */
#[Group('Htmx')]
#[RunTestsInSeparateProcesses]
class HtmxMessageTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
    'test_htmx',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('system_messages_block', [
      'region' => 'content',
    ]);
  }

  /**
   * Tests that messages delivered by HTMX are displayed and appended.
   */
  public function testMessages(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
    ]));
    $this->drupalGet('/htmx-test/message-test');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    // Verify that the message sets are present on the page.
    $message_sets = $page->findAll('css', '[data-drupal-message-set]');
    $this->assertCount(3, $message_sets);
    // Verify that the message sets are visually hidden.
    foreach ($message_sets as $message_set) {
      $this->assertFalse($message_set->isVisible());
    }

    $messages = [
      'status' => 'PHP Status',
      'error' => 'PHP Error',
      'warning' => 'PHP Warning',
    ];
    foreach ($messages as $type => $message) {
      $list_selector = '[data-drupal-message-list-type="' . $type . '"]';
      // Trigger an htmx request for the message.
      $page->pressButton("Add $type");
      $assert_session->assertWaitOnAjaxRequest();
      // Verify that the message list becomes visible.
      $message_list = $assert_session->waitForElementVisible('css', $list_selector);
      $this->assertTrue($message_list->getParent()->isVisible());
      // Verify that the message is displayed.
      $assert_session->pageTextContains($message);
      // Verify that the message log is updated.
      $assert_session->pageTextContains("Message of type $type requested.");
      // Verify that new messages of the same type
      // are appended to the message list.
      $message_count = count($page->findAll('css', "$list_selector > li"));
      $log_count = count($page->findAll('css', '.message-log'));
      $page->pressButton("Add $type");
      $assert_session->assertWaitOnAjaxRequest();
      // Check the element count to ensure the new message was appended.
      $message_count++;
      $this->assertCount($message_count, $page->findAll('css', "$list_selector > li"));
      // Check the text count for this message type as well.
      $assert_session->pageTextMatchesCount($message_count, '/' . $message . '/');
      $assert_session->pageTextMatchesCount($message_count, "/Message of type $type requested/");
      // Verify the log increased by one.
      $log_count++;
      $this->assertCount($log_count, $page->findAll('css', '.message-log'));
    }
  }

}
