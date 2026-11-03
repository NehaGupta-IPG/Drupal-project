<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\menu_tree_ui\MenuTreeUiConstants;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a wide top level renders capped and pages in on scroll.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiTopLevelPagingTest extends MenuTreeUiJavascriptTestBase {

  /**
   * The number of top-level links seeded (one page plus a remainder).
   */
  private const SEEDED = MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE + 50;

  /**
   * {@inheritdoc}
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    for ($i = 1; $i <= self::SEEDED; $i++) {
      $this->createLink(sprintf('Item %03d', $i), '', $i);
    }
  }

  /**
   * The first page renders; scrolling loads the rest with no duplicates.
   */
  public function testTopLevelPagesInOnScroll(): void {
    $selector = 'drupal-treegrid tbody tr[role="row"][data-parent-id=""]';

    $initial = (int) $this->getSession()->evaluateScript(
      sprintf('return document.querySelectorAll(%s).length;', json_encode($selector)),
    );
    $this->assertSame(MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE, $initial);
    $this->assertSession()->pageTextContains(
      sprintf('Showing the first %d of %d links', MenuTreeUiConstants::TOP_LEVEL_PAGE_SIZE, self::SEEDED),
    );

    // Scroll the sentinel into view to trigger the remaining page.
    $this->getSession()->executeScript('window.scrollTo(0, document.body.scrollHeight);');
    $this->getSession()->wait(
      10000,
      sprintf('document.querySelectorAll(%s).length === %d', json_encode($selector), self::SEEDED),
    );

    $final = (int) $this->getSession()->evaluateScript(
      sprintf('return document.querySelectorAll(%s).length;', json_encode($selector)),
    );
    $this->assertSame(self::SEEDED, $final, 'All top-level rows loaded after scrolling.');
    $this->assertNoDuplicateRows();
  }

}
