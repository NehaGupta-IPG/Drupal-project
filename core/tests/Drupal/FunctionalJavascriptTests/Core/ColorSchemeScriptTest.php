<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the script in the head applies the color scheme preference.
 *
 * The server renders data-color-scheme="auto" on every page and puts a
 * customized preference in a cookie, so a browser running the script is the
 * only place where the effect of the preference is observable. The script is
 * handed to the render array as one string, which makes it possible to break
 * it without any other test noticing.
 *
 * That the server renders 'auto' whatever the preference is asserted in
 * HtmlAttributesTest, and the cookie itself in ColorSchemeCookieTest.
 *
 * @see \Drupal\Core\Render\BareHtmlPageRenderer::systemPageAttachments()
 * @see \Drupal\Tests\system\Functional\Theme\HtmlAttributesTest
 * @see \Drupal\FunctionalTests\EventSubscriber\ColorSchemeCookieTest
 */
#[Group('Theme')]
#[RunTestsInSeparateProcesses]
class ColorSchemeScriptTest extends WebDriverTestBase {

  /**
   * A script that reads the color scheme attribute out of the document.
   */
  const READ_ATTRIBUTE = "document.documentElement.getAttribute('data-color-scheme')";

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the script applies the cookie to the html element.
   */
  public function testScriptAppliesThePreference(): void {
    $dark = $this->drupalCreateUser();
    $dark->set('color_scheme', 'dark')->save();
    $auto = $this->drupalCreateUser();

    // The cookie is written on the response to the login, so the script has it
    // and overrides the rendered 'auto' with it.
    $this->drupalLogin($dark);
    $this->drupalGet('user/' . $dark->id());
    $this->assertSame('dark', $this->getSession()->evaluateScript(self::READ_ATTRIBUTE));

    // Logging in as an account on 'auto' removes the cookie again, so the
    // script leaves the document alone and the rendered value stays.
    $this->drupalLogin($auto);
    $this->drupalGet('user/' . $auto->id());
    $this->assertSame('auto', $this->getSession()->evaluateScript(self::READ_ATTRIBUTE));
  }

}
