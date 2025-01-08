<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests warnings for menu item changes on entity delete forms.
 *
 * @group menu_ui
 *
 * @coversDefaultClass \Drupal\menu_ui\EntityDeleteMenuDescendants
 */
final class MenuUiEntityDeleteMenuDescendantsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'menu_link_content',
    'menu_ui',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests an entity with a menu item, with children items, displays a warning.
   *
   * The message warns the user that the children menu items will be moved up
   * one level.
   *
   * @param class-string<\Drupal\entity_test\Entity\EntityTest|\Drupal\menu_link_content\Entity\MenuLinkContent> $testEntityType
   *   An entity type with a menu item, or just a menu link content.
   * @param bool $hasMultipleMenuItemChildren
   *   Whether the menu item has one or multiple children.
   *
   * @dataProvider providerDeleteEntityWithMenuAndChildren
   */
  public function testDeleteEntityWithMenuAndChildren(string $testEntityType, bool $hasMultipleMenuItemChildren): void {
    $testingAsMenuDelete = $testEntityType === MenuLinkContent::class;
    $this->drupalLogin($this->drupalCreateUser(permissions: [
      'administer menu',
      'view test entity',
      // Needed for delete permission.
      // See \Drupal\entity_test\EntityTestAccessControlHandler::checkAccess()
      'administer entity_test content',
    ]));

    $menu = Menu::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomMachineName(),
    ]);
    $menu->save();

    // Create a tree of menu links where each link uses the previously created
    // menu link as its parent.
    $entities = [];
    $menuLinkContentEntities = [];
    foreach (range(1, 3) as $i) {
      $entity = EntityTest::create([
        'name' => 'Test entity #' . $i,
      ]);
      $entity->save();
      $entities[$i] = $entity;

      $previousMenuItem = $menuLinkContentEntities[$i - 1] ?? NULL;
      $menuLinkContent = MenuLinkContent::create()
        ->set('title', 'Menu link for ' . $entity->label())
        ->set('link', 'entity:' . $entity->toUrl()->getInternalPath())
        ->set('enabled', TRUE)
        ->set('menu_name', $menu->id())
        ->set('parent', $previousMenuItem !== NULL ? 'menu_link_content:' . $previousMenuItem->uuid() : NULL);
      $menuLinkContent->save();

      $menuLinkContentEntities[$i] = $menuLinkContent;
    }

    if ($hasMultipleMenuItemChildren) {
      $menuLinkContent = MenuLinkContent::create()
        ->set('title', 'Another menu item')
        // The link does not need to point to an entity.
        ->set('link', 'route:<front>')
        ->set('enabled', TRUE)
        ->set('menu_name', $menu->id())
        ->set('parent', 'menu_link_content:' . $menuLinkContentEntities[2]->uuid());
      $menuLinkContent->save();
    }

    // Navigate to the delete form for the second entity. The second entity is
    // used since we are testing parents and children are not displayed in the
    // message.
    $this->drupalGet(($testingAsMenuDelete ? $menuLinkContentEntities : $entities)[2]->toUrl('delete-form', [
      'query' => [
        // Adds a redirect back to front so when we're testing menu link content
        // we can assert against the links in the messenger message rather than
        // the list of links on the menu edit form.
        'destination' => Url::fromRoute('<front>')->toString(),
      ],
    ]));

    $this->assertSession()->pageTextContains('Are you sure you want to delete the');
    if ($hasMultipleMenuItemChildren) {
      $this->assertSession()->pageTextContains($testingAsMenuDelete ? 'Deleting this custom menu link will make these 2 child menu items top level:' : 'Deleting this test entity will make these 2 child menu items top level:');
      $this->assertSession()->linkNotExists('Menu link for Test entity #1');
      $this->assertSession()->linkNotExists('Menu link for Test entity #2');
      $this->assertSession()->linkExists('Menu link for Test entity #3');
      $this->assertSession()->linkExists('Another menu item');
    }
    else {
      $this->assertSession()->pageTextContains($testingAsMenuDelete ? 'Deleting this custom menu link will make this 1 child menu item top level:' : 'Deleting this test entity will make this 1 child menu item top level:');
      $this->assertSession()->linkNotExists('Menu link for Test entity #1');
      $this->assertSession()->linkNotExists('Menu link for Test entity #2');
      $this->assertSession()->linkExists('Menu link for Test entity #3');
      $this->assertSession()->linkNotExists('Another menu item');
    }

    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('has been deleted.');

    if ($hasMultipleMenuItemChildren) {
      $this->assertSession()->pageTextContains($testingAsMenuDelete ? 'The menu items for Menu link for Test entity #2 had 2 children menu items. These children menu items moved to the same level as the now-deleted menu item. Affected menu items include:' : 'The menu items for Test entity #2 had 2 children menu items. These children menu items moved to the same level as the now-deleted menu item. Affected menu items include:');
      $this->assertSession()->linkNotExists('Menu link for Test entity #1');
      $this->assertSession()->linkNotExists('Menu link for Test entity #2');
      $this->assertSession()->linkExists('Menu link for Test entity #3');
      $this->assertSession()->linkExists('Another menu item');
    }
    else {
      $this->assertSession()->pageTextContains($testingAsMenuDelete ? 'The menu item for Menu link for Test entity #2 had 1 child menu item. This child menu item moved to the same level as the now-deleted menu item. The affected menu item is:' : 'The menu item for Test entity #2 had 1 child menu item. This child menu item moved to the same level as the now-deleted menu item. The affected menu item is:');
      $this->assertSession()->linkNotExists('Menu link for Test entity #1');
      $this->assertSession()->linkNotExists('Menu link for Test entity #2');
      $this->assertSession()->linkExists('Menu link for Test entity #3');
      $this->assertSession()->linkNotExists('Another menu item');
    }
  }

  /**
   * Data provider for testDeleteEntityWithMenuAndChildren().
   *
   * @return array<string, array{\Drupal\menu_link_content\Entity\MenuLinkContent|\Drupal\entity_test\Entity\EntityTest, bool}>
   *   Data for testing.
   */
  public static function providerDeleteEntityWithMenuAndChildren(): array {
    return [
      'A menu item with one direct child menu item' => [MenuLinkContent::class, FALSE],
      'A menu item with multiple direct child menu items' => [MenuLinkContent::class, TRUE],
      'An entity with a menu item with one direct child menu item' => [EntityTest::class, FALSE],
      'An entity with a menu item with multiple direct child menu items' => [EntityTest::class, TRUE],
    ];
  }

}
