<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Database\Database;

/**
 * Create a block and test saving it.
 *
 * @group block_content
 */
class BlockContentCreationTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content_test',
    'navigation_variant_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the redirect workflow of creating a block_content and block.
   */
  public function testBlockContentFormSubmitHandlers(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer block content',
    ]));

    // Create a block and place in block layout.
    $this->drupalGet('/admin/content/block');
    $this->clickLink('Add content block');
    $this->assertSession()->addressEquals('/block/add/basic');
    $edit = [];
    $edit['info[0][value]'] = 'Test Block';
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('basic ' . $edit['info[0][value]'] . ' has been created.');

    // Verify when editing a block "Save and configure" does not appear.
    $this->drupalGet('/admin/content/block/1');
    $this->assertSession()->buttonNotExists('Save and configure');

    // Create a block but go back to block library.
    $edit = [];
    $edit['info[0][value]'] = 'Test Block';
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit, 'Save');
    // Check that the Basic block has been created.
    $this->assertSession()->pageTextContains('basic ' . $edit['info[0][value]'] . ' has been created.');
    $this->assertSession()->addressEquals('/admin/content/block');

    // Check that the user is redirected to the block library on edit.
    $block = $this->getBlockByLabel($edit['info[0][value]']);
    $this->drupalGet($block->toUrl('edit-form'));
    $this->submitForm([
      'info[0][value]' => 'Test Block Updated',
    ], 'Save');
    $this->assertSession()->addressEquals('admin/content/block');

    // Test with user who doesn't have permission to place a block.
    $this->drupalLogin($this->drupalCreateUser(['administer block content']));
    $this->drupalGet('block/add/basic');
    $this->assertSession()->buttonNotExists('Save and configure');
  }

  /**
   * Create a default content block.
   *
   * Creates a content block from defaults and ensures that the 'basic block'
   * type is being used.
   */
  public function testDefaultBlockContentCreation(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer block content',
    ]));

    $edit = [];
    $edit['info[0][value]'] = $this->randomMachineName(8);
    $edit['body[0][value]'] = $this->randomMachineName(16);
    // Don't pass the content block type in the URL so the default is forced.
    $this->drupalGet('block/add');
    $this->submitForm($edit, 'Save');

    // Check that the block has been created and that it is a basic block.
    $this->assertSession()->pageTextContains('basic ' . $edit['info[0][value]'] . ' has been created.');

    // Check that the block exists in the database.
    $block = $this->getBlockByLabel($edit['info[0][value]']);
    $this->assertNotEmpty($block, 'Default Content Block found in database.');
  }

  /**
   * Verifies that a transaction rolls back the failed creation.
   */
  public function testFailedBlockCreation(): void {
    // Create a block.
    try {
      $this->createBlockContent('fail_creation');
      $this->fail('Expected exception has not been thrown.');
    }
    catch (\Exception) {
      // Expected exception; just continue testing.
    }

    $connection = Database::getConnection();

    // Check that the block does not exist in the database.
    $id = $connection->select('block_content_field_data', 'b')
      ->fields('b', ['id'])
      ->condition('info', 'fail_creation')
      ->execute()
      ->fetchField();
    $this->assertFalse($id);
  }

  /**
   * Load a block based on the label.
   */
  private function getBlockByLabel(string $label): ?BlockContentInterface {
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['info' => $label]);
    if (empty($blocks)) {
      return NULL;
    }
    return reset($blocks);
  }

}
