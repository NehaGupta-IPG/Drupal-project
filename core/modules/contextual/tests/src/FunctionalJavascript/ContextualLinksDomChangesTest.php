<?php

declare(strict_types=1);

namespace Drupal\Tests\contextual\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests contextual links when other scripts change the surrounding DOM.
 */
#[Group('contextual')]
#[RunTestsInSeparateProcesses]
class ContextualLinksDomChangesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contextual_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that contextual links are resilient to DOM changes from other JS.
   */
  public function testContextualLinksDomChanges(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
    ]));

    // Prime the session storage cache so the reload takes the cached code
    // path, which resolves each placeholder again after the move.
    // @see Drupal.behaviors.contextual
    $this->drupalGet('contextual-tests/relocated-links');
    $this->assertNotNull($this->assertSession()->waitForElement('css', '#region-first .contextual button.trigger'));

    $this->getSession()->reload();
    $this->assertNotNull($this->assertSession()->waitForElement('css', '#region-first .contextual button.trigger'));
    $this->assertSession()->elementNotExists('css', '#region-second');
    $this->assertSame(1, $this->getSession()->evaluateScript('Drupal.contextual.instances.length'));

    // Hovering the region renders the links again, so the trigger is looked
    // up after another script has emptied the placeholder.
    // @see Drupal.contextual.ContextualModelView
    $this->drupalGet('contextual-tests/duplicate-links');
    $this->assertJsCondition("jQuery('[data-contextual-id]:empty').length === 0");
    $this->getSession()->executeScript("document.querySelector('#region-first .contextual').innerHTML = '';");
    $this->getSession()->executeScript("jQuery('#region-first').trigger('mouseenter');");
    $this->assertSession()->elementExists('css', '#region-first .contextual');
  }

}
