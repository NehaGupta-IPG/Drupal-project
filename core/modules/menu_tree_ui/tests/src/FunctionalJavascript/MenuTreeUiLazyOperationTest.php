<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\menu_tree_ui_test\Hook\MenuTreeUiTestHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a lazily built operation in a loaded row reaches the page.
 *
 * BigPipe is enabled so a placeholder could be streamed after the content,
 * outside the part of the response the client keeps.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiLazyOperationTest extends MenuTreeUiJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'big_pipe',
    'menu_link_content',
    'menu_tree_ui',
    'menu_tree_ui_test',
  ];

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
    \Drupal::state()->set(MenuTreeUiTestHooks::LAZY_OPERATION_KEY, TRUE);
  }

  /**
   * A loaded row's operations contain the lazily built element.
   */
  public function testLazyBuiltOperationInLoadedRow(): void {
    $this->expandRow('P');

    $selector = sprintf('tr[data-id="%s"] [data-menu-tree-ui-test-lazy]', $this->linkIds['C1']);
    $this->assertNotNull($this->assertSession()->waitForElement('css', $selector));
  }

}
