<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
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
    'inline_form_errors',
    'navigation',
    'node',
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

    $this->assertTrue(\Drupal::service('theme_installer')->install(['default_admin']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('admin', 'default_admin')
      ->save();
    $this->applyRecipe('core/tests/fixtures/recipes/article_content_type');

    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);
  }

  /**
   * Tests that Inline Form Errors links open sidebar and focus field.
   */
  public function testSidebarOpensForInlineFormErrorsLinks(): void {
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Narrow viewport so sidebar starts closed.
    $session->resizeWindow(
      800,
      823
    );
    $this->drupalGet('/node/add/article');

    $toggle = $page->find('css', '[aria-controls="default_admin_sidebar"]');
    $sidebar = $page->findById('default_admin_sidebar');
    $url_alias_field = $sidebar->findField('URL alias');

    $this->assertFalse($url_alias_field->isVisible());

    // Open sidebar.
    $toggle->press();
    // Open URL alias details element to expose field.
    $assert_session
      ->waitForElementVisible('named', ['button', 'URL alias'])
      ->press();
    // $details->press();
    // Wait for details to open.
    $session->wait(500);
    $url_alias_field->setValue('no-slash');

    // Close sidebar and submit form.
    $toggle->press();
    $page->pressButton('Save');

    $error_alert = $assert_session
      ->waitForElementVisible('css', '[role="alert"]');

    $this->assertFalse($url_alias_field->isVisible());
    $this->assertFalse($this->hasFocus($url_alias_field));

    $error_alert
      ->clickLink('URL alias');

    $this->assertTrue($url_alias_field->isVisible());
    $this->assertTrue($this->hasFocus($url_alias_field));
  }

  /**
   * Checks the focus of a node.
   *
   * @param \Behat\Mink\Element\NodeElement $node
   *   A valid node.
   *
   * @return bool
   *   Returns TRUE if the node has focus, FALSE otherwise.
   *
   * @todo Replace with assertHasFocus() in https://drupal.org/i/3041768.
   */
  private function hasFocus(NodeElement $node): bool {
    $xpath = str_replace("\n", '', $node->getXpath());

    // This JavaScript tests if the given DOM node (as resolved by XPath) is
    // also the active element of the current document. The active element is a
    // standard property of the document object (or a shadow DOM root) pointing
    // to the node that has focus. It's best to test this using vanilla
    // JavaScript, not jQuery, since jQuery may not be available.
    // @see https://developer.mozilla.org/en-US/docs/Web/API/DocumentOrShadowRoot/activeElement
    $js = <<<JS
return document.evaluate("$xpath", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue === document.activeElement;
JS;
    return $this->getSession()->evaluateScript($js);
  }

}
