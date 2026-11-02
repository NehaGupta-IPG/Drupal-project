<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\HttpKernelFormHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form API Response element.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class ResponseTest extends KernelTestBase {

  use HttpKernelFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test', 'system'];

  /**
   * Tests that enforced responses propagate through subscribers and middleware.
   */
  public function testFormResponse(): void {
    $edit = [
      'content' => $this->randomString(),
      'status' => 200,
    ];
    $this->drupalGet('form-test/response');
    $this->submitForm($edit, 'Submit');
    $content = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame($edit['content'], $content, 'Response content matches');
    // Verify that response was handled by kernel response subscriber.
    $this->assertSession()->responseHeaderEquals('X-Form-Test-Response-Event', 'invoked');
    // Verify that response was handled by kernel middleware.
    $this->assertSession()->responseHeaderEquals('X-Form-Test-Stack-Middleware', 'invoked');

    $edit = [
      'content' => $this->randomString(),
      'status' => 418,
    ];
    $this->drupalGet('form-test/response');
    $this->submitForm($edit, 'Submit');
    $content = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertSession()->statusCodeEquals(418);
    $this->assertSame($edit['content'], $content, 'Response content matches');
    // Verify that response was handled by kernel response subscriber.
    $this->assertSession()->responseHeaderEquals('X-Form-Test-Response-Event', 'invoked');
    // Verify that response was handled by kernel middleware.
    $this->assertSession()->responseHeaderEquals('X-Form-Test-Stack-Middleware', 'invoked');
  }

}
