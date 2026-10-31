<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the interactive column checkbox end-to-end.
 *
 * The "Enabled" column renders the treegrid checkbox cell — a native
 * <input type="checkbox">. Clicking it flips the checkbox immediately (no
 * preventDefault) and POSTs the new value to the column-update endpoint, which
 * writes it to the menu_link_content entity. This drives a real click and
 * asserts both the optimistic flip and that the change reaches storage.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiColumnToggleTest extends WebDriverTestBase {

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
   * The plugin ID of the seeded "A" link.
   */
  private string $linkId;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $entity = MenuLinkContent::create([
      'menu_name' => 'test',
      'title' => 'A',
      'link' => ['uri' => 'route:<nolink>'],
      'enabled' => TRUE,
    ]);
    $entity->save();
    $this->linkId = $entity->getPluginId();

    $admin = $this->drupalCreateUser(['administer menu']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/structure/menu/manage/test/tree');
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * Clicking the Enabled checkbox unchecks it and persists enabled = FALSE.
   */
  public function testTogglePersists(): void {
    $checkbox = $this->enabledCheckbox();
    $this->assertTrue($checkbox->isChecked(), 'The Enabled checkbox starts checked.');

    $checkbox->click();
    $this->assertFalse(
      $checkbox->isChecked(),
      'Clicking the checkbox unchecks it immediately, before the round-trip.',
    );

    $this->assertEnabledPersistsAs(FALSE);
  }

  /**
   * The Enabled checkbox of a row in a loaded level stores the new value.
   */
  public function testToggleInLoadedRowPersists(): void {
    $parent = $this->createLink('P', '');
    $child = $this->createLink('C', $parent);
    $this->createLink('Q', '');
    $this->createLink('R', '');
    $this->drupalGet('/admin/structure/menu/manage/test/tree');
    $toggle = $this->assertSession()->waitForElement('css', sprintf('tr[data-id="%s"] [data-treegrid-toggle]', $parent));
    $toggle->click();
    $this->assertSession()->waitForElement('css', sprintf('tr[data-id="%s"]', $child));

    $checkbox = $this->enabledCheckbox($child);
    $this->assertTrue($checkbox->isChecked());
    $checkbox->click();

    $this->assertEnabledPersistsAs(FALSE, 'C');
  }

  /**
   * Creates an enabled link in the test menu and returns its plugin ID.
   */
  private function createLink(string $title, string $parent): string {
    $entity = MenuLinkContent::create([
      'menu_name' => 'test',
      'title' => $title,
      'link' => ['uri' => 'route:<nolink>'],
      'parent' => $parent,
      'enabled' => TRUE,
    ]);
    $entity->save();
    return $entity->getPluginId();
  }

  /**
   * The Enabled column checkbox of a link, the seeded one by default.
   */
  private function enabledCheckbox(?string $link_id = NULL): NodeElement {
    $selector = sprintf(
      'tr[role="row"][data-id="%s"] input.treegrid-cell-checkbox[data-column-id="enabled"]',
      $link_id ?? $this->linkId,
    );
    $checkbox = $this->getSession()->getPage()->find('css', $selector);
    $this->assertNotNull($checkbox, 'The Enabled checkbox should be present.');
    return $checkbox;
  }

  /**
   * Polls storage until a link's enabled flag matches, or fails.
   *
   * The persistence POST is a plain fetch with no DOM-visible completion
   * signal, so the write is observed by re-reading the entity rather than
   * waiting on the page.
   *
   * @param bool $expected
   *   The enabled value the link should settle on.
   * @param string $title
   *   The link's title.
   */
  private function assertEnabledPersistsAs(bool $expected, string $title = 'A'): void {
    $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    for ($i = 0; $i < 50; $i++) {
      $storage->resetCache();
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('title', $title)
        ->execute();
      $entity = $storage->load(reset($ids));
      assert($entity instanceof MenuLinkContentInterface);
      if ($entity->isEnabled() === $expected) {
        return;
      }
      usleep(100000);
    }
    $this->fail('The Enabled change was not persisted to storage.');
  }

}
