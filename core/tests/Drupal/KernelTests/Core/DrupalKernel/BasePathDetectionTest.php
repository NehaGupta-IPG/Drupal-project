<?php

namespace Drupal\KernelTests\Core\DrupalKernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the /index.php base URL bug in Drupal core.
 *
 * This test demonstrates that when accessing Drupal via /index.php,
 * the generated URLs incorrectly include /index.php/ prefix.
 */
#[Group("DrupalKernel")]
#[RunInSeparateProcesses]
class BasePathDetectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Tests that accessing via /index.php causes incorrect base URL.
   *
   * This is the main test that demonstrates the bug.
   */
  public function testIndexPhpBaseUrlBug() : void {
    // Test 1: Clean URL access (baseline - what we expect)
    $clean_request = Request::create('http://example.com/node/1');
    $this->setCurrentRequest($clean_request);

    $url = Url::fromRoute('<front>')->toString();
    $this->assertEquals('/', $url, 'Clean URL access should generate "/" for front page');

    // Test 2: Access via /index.php (demonstrates the bug)
    // When accessing via /index.php, Drupal incorrectly sets the base URL.
    $index_request = Request::create('http://example.com/index.php');
    $index_request->server->set('SCRIPT_FILENAME', '/var/www/html/index.php');
    $this->setCurrentRequest($index_request);

    // This should still generate '/',
    // but due to the bug it generates '/index.php/'.
    $url = Url::fromRoute('<front>')->toString();
    $this->assertEquals('/', $url, 'Front page should be "/" even when accessed via /index.php');
  }

  /**
   * Tests the impact on generated links in content.
   *
   * This shows how the bug affects links generated for various routes.
   */
  public function testGeneratedLinksImpact() : void {
    // Set up /index.php context.
    $index_request = Request::create('http://example.com/index.php');
    $index_request->server->set('SCRIPT_FILENAME', '/var/www/html/index.php');
    $this->setCurrentRequest($index_request);

    $url = Url::fromRoute('user.login')->toString();
    $this->assertEquals('/user/login', $url, 'User login URL should be "/user/login" even when accessed via /index.php');
  }

  /**
   * Helper method to set current request in the container.
   */
  protected function setCurrentRequest(Request $request) : void {
    // Get the current request from parent class to preserve session.
    $current_request = \Drupal::request();
    if ($current_request && $current_request->hasSession()) {
      $request->setSession($current_request->getSession());
    }
    // Clear existing requests and add ours.
    $request_stack = $this->container->get('request_stack');

    while ($request_stack->getCurrentRequest()) {
      $request_stack->pop();
    }
    $request_stack->push($request);

    // Update request context for URL generator.
    $this->container->get('router.request_context')->fromRequest($request);
  }

}
