<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript\Form;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a form's complex url fragment.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class FormUrlFragmentTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a form's complex url fragment does not give errors.
   */
  public function testFormComplexUrlFragment(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/form-test/url');
    $this->clickLink('This is an anchor link with a broken fragment');
    $this->failOnJavaScriptErrors();
  }

  /**
   * Tests that a form's valid URL fragment is focused.
   */
  public function testFormUrlFragment(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/form-test/url');
    $this->clickLink('This is an anchor link with a valid fragment');
    $this->assertJsCondition('document.activeElement.id === "edit-url"');
  }

}
