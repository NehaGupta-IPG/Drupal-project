<?php

declare(strict_types=1);

namespace Drupal\Tests\block\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the JS functionality in the block add form.
 */
#[Group('block')]
#[RunTestsInSeparateProcesses]
class BlockAddTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the AJAX for the theme selector.
   */
  public function testBlockAddThemeSelector(): void {
    \Drupal::service('theme_installer')->install(['claro']);

    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
    ]));

    $this->drupalGet('admin/structure/block/add/system_powered_by_block');
    $assert_session = $this->assertSession();

    // Using selector because the label collides with current theme condition.
    $theme_select = $this->cssSelect('.block-form > .form-item-theme select');
    $this->assertCount(1, $theme_select);
    $theme_select[0]->selectOption('claro');

    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->selectExists('Region')->selectOption('pre_content');
    // Switch to a theme that doesn't contain the region selected above.
    $theme_select[0]->selectOption('stark');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains('The submitted value Pre-content in the Region element is not allowed.');
    $assert_session->optionExists('Region', '- Select -');
    // Check that the summary line is not present in the title.
    $summary_text = $this->getSession()->getPage()->find('css', 'li.vertical-tabs__menu-item:nth-child(1) > a:nth-child(1) > span:nth-child(2)')->getText();
    $assert_session->elementTextContains('css', '.vertical-tabs__menu-item-title', 'Response status');
    $assert_session->elementTextNotContains('css', '.vertical-tabs__menu-item-title', $summary_text);

    // Search for the "Pages" tab link and click it.
    $this->getSession()->getPage()->find('css', 'a[href="#edit-visibility-request-path"]')->click();
    // Check that the corresponding form section is open and visible.
    $form_section = $this->getSession()->getPage()->find('css', '#edit-visibility-request-path');
    $this->assertNotEmpty($form_section, 'The "Pages" form section exists.');
    $this->assertTrue($form_section->isVisible(), 'The "Pages" form section is visible after clicking the tab.');
  }

}
