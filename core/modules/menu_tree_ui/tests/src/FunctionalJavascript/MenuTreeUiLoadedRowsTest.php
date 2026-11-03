<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that rows loaded after the first page behave like the first page's.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiLoadedRowsTest extends MenuTreeUiJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $this->linkIds['P'] = $this->createLink('Parent P', '', 0);
    $this->linkIds['C1'] = $this->createLink('Child C1', $this->linkIds['P'], 0);
    $this->linkIds['Q'] = $this->createLink('Leaf Q', '', 1);
    $this->linkIds['R'] = $this->createLink('Leaf R', '', 2);
    $this->linkIds['S'] = $this->createLink('Leaf S', '', 3);
  }

  /**
   * A dropbutton in a loaded row opens on a click on its toggle.
   */
  public function testDropbuttonInLoadedRowOpens(): void {
    $this->row('P')->find('css', '[data-treegrid-toggle]')->click();
    $toggle_selector = sprintf('tr[data-id="%s"] .dropbutton-wrapper .dropbutton-toggle button', $this->linkIds['C1']);
    $toggle = $this->assertSession()->waitForElement('css', $toggle_selector);
    $this->assertNotNull($toggle, 'The loaded row has a dropbutton toggle.');

    $toggle->click();

    $this->assertNotNull($this->assertSession()->waitForElement('css', sprintf('tr[data-id="%s"] .dropbutton-wrapper.open', $this->linkIds['C1'])));
  }

}
