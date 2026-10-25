<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that row labels are the same plain text whoever builds the row.
 *
 * Rows built by Twig and rows built by the client from the JSON response must
 * give the same accessible name, including for a title holding markup and an
 * ampersand.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiRowLabelTest extends MenuTreeUiJavascriptTestBase {

  /**
   * A link title that is markup and holds an ampersand.
   */
  private const TITLE = 'Fish & <b>Chips</b>';

  /**
   * {@inheritdoc}
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $this->linkIds['P'] = $this->createLink('Parent P', '', 0);
    $this->linkIds['T'] = $this->createLink(self::TITLE, '', 1);
    $this->linkIds['R'] = $this->createLink('Leaf R', '', 2);
    $this->linkIds['C'] = $this->createLink(self::TITLE, $this->linkIds['P'], 0);
    $this->linkIds['G'] = $this->createLink('Grandchild G', $this->linkIds['C'], 0);
    $this->linkIds['TP'] = $this->createLink(self::TITLE, '', 3);
    $this->linkIds['TG'] = $this->createLink('Grandchild TG', $this->linkIds['TP'], 0);
  }

  /**
   * A client-built row is named the same as a Twig-built row.
   */
  public function testClientBuiltRowNameMatchesTwigBuiltRow(): void {
    $this->expandRow('P');
    $twig_name = $this->row('T')->getAttribute('aria-label');
    $this->assertSame(self::TITLE, $twig_name);
    $this->assertSame($twig_name, $this->row('C')->getAttribute('aria-label'));
  }

  /**
   * A client-built toggle is named the same as a Twig-built toggle.
   */
  public function testClientBuiltToggleNameMatchesTwigBuiltToggle(): void {
    $this->expandRow('P');
    $selector = '.menu-tree-ui-row__name button.menu-tree-ui-row__toggle';
    $twig_name = $this->row('TP')->find('css', $selector)->getAttribute('aria-label');
    $client_name = $this->row('C')->find('css', $selector)->getAttribute('aria-label');
    $this->assertStringContainsString(self::TITLE, $twig_name);
    $this->assertSame($twig_name, $client_name);
  }

  /**
   * A client-built locked row names the title as plain text.
   */
  public function testClientBuiltLockedRowNameIsPlainText(): void {
    $title = json_encode(self::TITLE);
    $label = $this->getSession()->evaluateScript(<<<JS
      return Drupal.treegrid.providers.menu_tree_ui.buildRow({
        id: 'locked',
        parent_id: '',
        title: $title,
        enabled: true,
        has_children: false,
        depth: 1,
        url: '/',
        weight: 0,
        movable: false,
        columns: {},
      }, 1).getAttribute('aria-label');
    JS);
    $this->assertStringStartsWith(self::TITLE, $label);
    $this->assertStringNotContainsString('&amp;', $label);
    $this->assertStringNotContainsString('&lt;', $label);
  }

}
