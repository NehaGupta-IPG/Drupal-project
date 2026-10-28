<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form description toggle.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class DescriptionToggleTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * Tests that the help icon toggles the description without submitting.
   *
   * The toggle behavior needs Drupal.behaviors, so this fails if the library
   * is loaded before core/drupal, which happens with JS aggregation.
   */
  public function testDescriptionToggle(): void {
    $this->config('default_admin.settings')->set('show_description_toggle', TRUE)->save();
    $this->config('system.performance')->set('js.preprocess', TRUE)->save();
    $this->drupalLogin($this->drupalCreateUser());

    $this->drupalGet('user/' . $this->loggedInUser->id() . '/edit');
    $url = $this->getSession()->getCurrentUrl();
    $page = $this->getSession()->getPage();
    $toggle = $page->find('css', '.help-icon__description-toggle');
    $this->assertNotNull($toggle);
    $description = $toggle->getParent()->getParent()->find('css', '.form-item__description');
    $this->assertNotNull($description);
    $this->assertTrue($description->hasClass('visually-hidden'));

    $toggle->click();

    $this->assertSame('true', $toggle->getAttribute('aria-expanded'));
    $this->assertFalse($description->hasClass('visually-hidden'));
    $this->assertSame($url, $this->getSession()->getCurrentUrl());
  }

}
