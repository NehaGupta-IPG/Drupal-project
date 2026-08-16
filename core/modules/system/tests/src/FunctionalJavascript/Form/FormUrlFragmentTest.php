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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests that a form's complex url fragment does not give errors.
   */
  public function testFormComplexUrlFragment(): void {
    $this->drupalGet('/form-test/url');
    $this->clickLink('This is an anchor link with a broken fragment');
    $this->failOnJavaScriptErrors();
  }

}
