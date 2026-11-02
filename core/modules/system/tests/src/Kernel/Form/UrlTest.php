<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\HttpKernelFormHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form API URL element.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class UrlTest extends KernelTestBase {

  use HttpKernelFormHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test', 'system'];

  /**
   * Tests that #type 'url' fields are properly validated and trimmed.
   */
  public function testFormUrl(): void {
    $edit = [];
    $edit['url'] = 'http://';
    $edit['url_required'] = ' ';
    $this->drupalGet('form-test/url');
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains("The URL http:// is not valid.");
    $this->assertSession()->pageTextContains("Required URL field is required.");

    // Clear the static form error flag set by the validation failure above.
    // In BrowserTestBase each request is a fresh process; in kernel tests all
    // requests share the same process, so the flag must be reset manually.
    (new FormState())->clearErrors();
    $edit = [];
    $edit['url'] = "\n";
    $edit['url_required'] = 'http://example.com/   ';
    $this->drupalGet('form-test/url');
    $this->submitForm($edit, 'Submit');
    $values = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertSame('', $values['url']);
    $this->assertEquals('http://example.com/', $values['url_required']);

    $edit = [];
    $edit['url'] = 'http://foo.bar.example.com/';
    $edit['url_required'] = 'https://www.drupal.org/node/1174630?page=0&foo=bar#new';
    $this->drupalGet('form-test/url');
    $this->submitForm($edit, 'Submit');
    $values = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertEquals($edit['url'], $values['url']);
    $this->assertEquals($edit['url_required'], $values['url_required']);
  }

}
