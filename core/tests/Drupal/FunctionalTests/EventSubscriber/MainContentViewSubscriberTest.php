<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\EventSubscriber;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MainContentViewSubscriber functions.
 *
 * @see \Drupal\Core\EventSubscriber\MainContentViewSubscriber
 */
#[Group('EventSubscriber')]
class MainContentViewSubscriberTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Test that the url query gets sent when the page redirects.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testOnRedirectResponse(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet("/user?_wrapper_format=drupal_dialog");
    $this->assertSession()->addressEquals('/user%3F_wrapper_format%3Ddrupal_dialog');
  }

}
