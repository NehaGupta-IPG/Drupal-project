<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\BlockUsage\BlockContentBlockUsageInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Url;

// cspell:ignore testblock

/**
 * Tests optional integration from Block Content to Block module.
 *
 * @group block_content
 * @group legacy
 */
final class BlockContentBlockTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Creates a "Basic block" block and verifies its consistency in the database.
   */
  public function testBlockContentCreation(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer block content',
      'administer blocks',
    ]));

    // Create a block.
    $edit = [];
    $edit['info[0][value]'] = 'Test Block';
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit, 'Save and configure');

    // Check that the Basic block has been created.
    $this->assertSession()->pageTextContains('basic ' . $edit['info[0][value]'] . ' has been created.');

    // Check that the view mode setting is hidden because only one exists.
    $this->assertSession()->pageTextContains('Block description Test Block');
    $this->assertSession()->fieldNotExists('settings[view_mode]');

    // Check that the block exists in the database.
    $block = $this->getBlockByLabel($edit['info[0][value]']);
    $this->assertNotEmpty($block, 'Content Block found in database.');

    // Ensure a user with just the create permission can access the page.
    $this->drupalLogin($this->drupalCreateUser([
      'create basic block content',
    ]));
    $this->drupalGet('block/add/basic');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Creates a "Basic page" block with multiple view modes.
   */
  public function testBlockContentCreationMultipleViewModes(): void {
    // Add a new view mode and verify if it is selected as expected.
    $this->drupalLogin($this->drupalCreateUser(['administer display modes']));
    $this->drupalGet('admin/structure/display-modes/view/add/block_content');
    $edit = [
      'id' => 'test_view_mode',
      'label' => 'Test View Mode',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the ' . $edit['label'] . ' view mode.');

    $this->drupalLogin($this->drupalCreateUser([
      'administer block_content display',
      'administer block content',
      'administer blocks',
    ]));

    // Create a block.
    $edit = [];
    $edit['info[0][value]'] = 'Test Block';
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalGet('block/add/basic');

    $this->submitForm($edit, 'Save and configure');

    // Save our block permanently
    $this->submitForm(['region' => 'content'], 'Save block');

    // Set test_view_mode as a custom display to be available on the list.
    $this->drupalGet('admin/structure/block-content/manage/basic/display');
    $custom_view_mode = [
      'display_modes_custom[test_view_mode]' => 1,
    ];
    $this->submitForm($custom_view_mode, 'Save');

    // Go to the configure page and change the view mode.
    $this->drupalGet('admin/structure/block/manage/stark_testblock');

    // Test the available view mode options.
    // Verify that the default view mode is available.
    $this->assertSession()->optionExists('edit-settings-view-mode', 'default');
    // Verify that the test view mode is available.
    $this->assertSession()->optionExists('edit-settings-view-mode', 'test_view_mode');

    $view_mode['settings[view_mode]'] = 'test_view_mode';
    $this->submitForm($view_mode, 'Save block');

    // Check that the view mode setting is shown because more than one exists.
    $this->drupalGet('admin/structure/block/manage/stark_testblock');
    $this->assertSession()->fieldExists('settings[view_mode]');

    // Change the view mode.
    $view_mode['region'] = 'content';
    $view_mode['settings[view_mode]'] = 'test_view_mode';
    $this->submitForm($view_mode, 'Save block');

    // Go to the configure page and verify the view mode has changed.
    $this->drupalGet('admin/structure/block/manage/stark_testblock');
    $this->assertSession()->fieldValueEquals('settings[view_mode]', 'test_view_mode');

    // Check that the block exists in the database.
    $block = $this->getBlockByLabel($edit['info[0][value]']);
    $this->assertNotEmpty($block, 'Content Block found in database.');
  }

  /**
   * Tests deleting Block Content deletes Block configs.
   *
   * @covers \Drupal\block_content\Form\BlockContentDeleteForm::getDescription
   * @covers \Drupal\block_content\Entity\BlockContent::preDelete
   */
  public function testBlockDelete(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer block content',
      'administer blocks',
    ]));

    // Create a block.
    $edit = [];
    $edit['info[0][value]'] = $this->randomMachineName(8);
    $body = $this->randomMachineName(16);
    $edit['body[0][value]'] = $body;
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit, 'Save');

    // Place the block.
    $instance = [
      'id' => mb_strtolower($edit['info[0][value]']),
      'settings[label]' => $edit['info[0][value]'],
      'region' => 'sidebar_first',
    ];
    $block = BlockContent::load(1);
    $this->drupalGet(Url::fromRoute('block.admin_add', [
      'plugin_id' => 'block_content:' . $block->uuid(),
      'theme' => $this->config('system.theme')->get('default'),
    ]));
    $this->submitForm($instance, 'Save block');

    $block = BlockContent::load(1);

    // Test getInstances method.
    $this->expectDeprecation(BlockContentInterface::class . '::getInstances() is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use the functionality provided by ' . BlockContentBlockUsageInterface::class . ' instead. See https://www.drupal.org/node/3523951');
    $this->assertCount(1, $block->getInstances());

    // Navigate to home page.
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains($body);

    // Delete the block.
    $this->drupalGet('admin/content/block/1/delete');
    $this->assertSession()->pageTextContains('This will also remove 1 placed block instance.');

    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('The content block ' . $edit['info[0][value]'] . ' has been deleted.');

    // Create another block and force the plugin cache to flush.
    $edit2 = [];
    $edit2['info[0][value]'] = $this->randomMachineName(8);
    $body2 = $this->randomMachineName(16);
    $edit2['body[0][value]'] = $body2;
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit2, 'Save');

    $this->assertSession()->responseNotContains('Error message');

    // Create another block with no instances, and test we don't get a
    // confirmation message about deleting instances.
    $edit3 = [];
    $edit3['info[0][value]'] = $this->randomMachineName(8);
    $body = $this->randomMachineName(16);
    $edit3['body[0][value]'] = $body;
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit3, 'Save');

    // Show the delete confirm form.
    $this->drupalGet('admin/content/block/3/delete');
    $this->assertSession()->pageTextNotContains('This will also remove');
  }

  /**
   * Tests placed content blocks create a dependency in Block config.
   */
  public function testConfigDependencies(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
    ]));

    $block = $this->createBlockContent();
    // Place the block.
    $block_placement_id = mb_strtolower($block->label());
    $instance = [
      'id' => $block_placement_id,
      'settings[label]' => $block->label(),
      'region' => 'sidebar_first',
    ];
    $block = BlockContent::load(1);
    $this->drupalGet(Url::fromRoute('block.admin_add', [
      'plugin_id' => 'block_content:' . $block->uuid(),
      'theme' => $this->config('system.theme')->get('default'),
    ]));
    $this->submitForm($instance, 'Save block');

    $dependencies = \Drupal::service('config.manager')->findConfigEntityDependenciesAsEntities('content', [$block->getConfigDependencyName()]);
    $block_placement = reset($dependencies);
    $this->assertEquals($block_placement_id, $block_placement->id(), "The block placement config entity has a dependency on the block content entity.");
  }

  /**
   * Load a block based on the label.
   */
  private function getBlockByLabel(string $label): ?BlockContentInterface {
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['info' => $label]);
    if ([] === $blocks) {
      return NULL;
    }
    return reset($blocks);
  }

}
