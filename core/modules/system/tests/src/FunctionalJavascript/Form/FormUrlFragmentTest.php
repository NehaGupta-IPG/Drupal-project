<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript\Form;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests a form's complex url fragment.
 */
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
  public function testFormComplexUrlFragment() {
    $this->drupalGet('/form-test/url');
    $this->clickLink('This is an anchor link with a broken fragment');
    $this->failOnJavaScriptErrors();

  }

}
