<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Add link" action on the menu_tree_ui page.
 *
 * The tree page server-renders this action (it is not JavaScript-dependent),
 * so a plain functional test covers it: the button points at the standard
 * menu-link add form with a destination back to the tree, and adding a link
 * returns to the tree with the new link present.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiAddLinkTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'menu_tree_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The menu being edited.
   */
  protected Menu $menu;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->menu = Menu::create([
      'id' => 'tools-test',
      'label' => 'Tools test',
    ]);
    $this->menu->save();
    $this->drupalLogin($this->drupalCreateUser([
      'administer menu',
      'access administration pages',
    ]));
  }

  /**
   * The tree page offers an "Add link" action pointing back to itself.
   */
  public function testAddLinkActionPresent(): void {
    $tree_path = '/admin/structure/menu/manage/' . $this->menu->id() . '/tree';
    $this->drupalGet($tree_path);
    $this->assertSession()->statusCodeEquals(200);

    $link = $this->assertSession()->elementExists('named', ['link', 'Add link']);
    $href = $link->getAttribute('href');
    $this->assertStringContainsString(
      '/admin/structure/menu/manage/' . $this->menu->id() . '/add',
      $href,
      'Add link points at the standard menu-link add form.',
    );
    $this->assertStringContainsString(
      'destination=' . $tree_path,
      $href,
      'Add link returns to the tree page after the link is added.',
    );
  }

  /**
   * Adding a link from the tree page returns to the tree with the new link.
   */
  public function testAddLinkRoundTrip(): void {
    $tree_path = '/admin/structure/menu/manage/' . $this->menu->id() . '/tree';
    $this->drupalGet($tree_path);
    $this->clickLink('Add link');

    $this->submitForm([
      'title[0][value]' => 'Brand new link',
      'link[0][uri]' => '<front>',
    ], 'Save');

    // The destination query sends the editor back to the tree, where the
    // server-rendered top level now includes the new link.
    $this->assertSession()->addressEquals($tree_path);
    $this->assertSession()->pageTextContains('Brand new link');
  }

}
