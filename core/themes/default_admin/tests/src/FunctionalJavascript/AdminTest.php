<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Default Admin theme.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class AdminTest extends WebDriverTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->applyRecipe('core/tests/fixtures/recipes/article_content_type');

    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);
  }

  /**
   * Tests that the toggle indicates that sidebar has child errors.
   */
  public function testSidebarToggleIndicatesChildErrors(): void {
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Narrow viewport so sidebar starts closed.
    $session->resizeWindow(
      1000,
      823
    );
    $this->drupalGet('/node/add/article');

    $toggle = $page->find('css', '[aria-controls="default_admin_sidebar"]');
    $sidebar = $page->findById('default_admin_sidebar');

    // Open sidebar.
    $toggle->press();
    // Open URL alias details element to expose field.
    $assert_session
      ->waitForElementVisible('named', ['button', 'URL alias'])
      ->press();
    // Wait for details to open.
    $session->wait(500);
    $sidebar->findField('URL alias')->setValue('no-slash');

    // Close sidebar and submit form.
    $toggle->press();
    $this->assertFalse($toggle->hasClass('has-error'));

    $page->pressButton('Save');

    $assert_session
      ->waitForElementVisible('css', '[role="alert"]');

    // Check that toggle has error indicators.
    $this->assertTrue($toggle->hasClass('has-error'));
    $this->assertStringEndsWith(' (sidebar contains error)', $toggle->getText());

    // Check that error indicators aren't cached.
    $this->drupalGet('/node/add/article');
    $this->assertFalse($toggle->hasClass('has-error'));
    $this->assertStringNotContainsString('(sidebar contains error)', $toggle->getText());
  }

}
