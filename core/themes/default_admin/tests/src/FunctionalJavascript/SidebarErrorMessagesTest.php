<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the error messages of fields in the sidebar.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class SidebarErrorMessagesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'path',
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

    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('admin', 'default_admin')
      ->save();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'view the administration theme',
      'create page content',
      'administer nodes',
      'create url aliases',
    ]));
  }

  /**
   * Tests that the error message button opens the sidebar at the field.
   *
   * Below the sidebar breakpoint the sidebar starts collapsed, and showing the
   * error message changes the height of the page, which must not collapse the
   * sidebar again once it is opened.
   */
  public function testOpenSidebarAtField(): void {
    $this->getSession()->resizeWindow(800, 1000);
    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Page');
    // The alias field is in the collapsed sidebar.
    $this->getSession()->executeScript('document.querySelector(\'[name="path[0][alias]"]\').value = "no-slash";');
    $page->pressButton('Save');

    $assert_session = $this->assertSession();
    $button = $assert_session->waitForButton('Open the sidebar');
    $this->assertNotNull($button);
    $assert_session->pageTextContains('The alias path has to start with a slash.');
    $this->assertJsCondition('document.body.dataset.metaSidebar === "closed"');

    $button->click();
    $this->assertJsCondition('document.body.dataset.metaSidebar === "open"');
    $this->assertJsCondition('document.activeElement.name === "path[0][alias]"');

    // Grow the page, and wait for the resize observers to be notified.
    $this->getSession()->executeScript(<<<'JS'
      new ResizeObserver(() => { window.pageResized = true; }).observe(document.documentElement);
      window.pageResized = false;
      document.body.append(Object.assign(document.createElement('div'), { style: 'height: 100px' }));
      JS);
    $this->assertJsCondition('window.pageResized === true');
    $this->assertJsCondition('document.body.dataset.metaSidebar === "open"');
  }

}
