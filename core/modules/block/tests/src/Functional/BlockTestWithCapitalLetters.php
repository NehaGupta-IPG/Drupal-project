<?php

namespace Drupal\Tests\block\Functional;

use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Tests block with capital letter path functionality.
 *
 * @group block
 */
class BlockTestWithCapitalLetters extends BlockTestBase {

  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests block visibility.
   */
  public function testBlockVisibility() {
    $block_name = 'system_powered_by_block';
    // Create a random title for the block.
    $title = $this->randomMachineName(8);
    // Enable a standard block.
    $default_theme = $this->config('system.theme')->get('default');
    $edit = [
      'id' => strtolower($this->randomMachineName(8)),
      'region' => 'sidebar_first',
      'settings[label]' => $title,
      'settings[label_display]' => TRUE,
    ];
    // Set the block to be shown only on 'BlockTestPageWithCapitalLetters' path.
    $edit['visibility[request_path][pages]'] = '/BlockTestPageWithCapitalLetters';
    $edit['visibility[request_path][negate]'] = "0";
    $path = 'admin/structure/block/add/' . $block_name . '/' . $default_theme;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Save block');

    // Test the frontpage where the block is not present.
    $this->drupalGet('');
    $this->assertSession()->pageTextNotContains($title);

    // Test that the block is visible on /BlockTestPageWithCapitalLetters where block
    // should be visible.
    $this->drupalGet('BlockTestPageWithCapitalLetters');
    $this->assertSession()->pageTextContains($title);

    // Create a path alias for 'BlockTestPageWithCapitalLetters'.
    $this->createPathAlias('/BlockTestPageWithCapitalLetters', '/block-test-page-with-capital-letters');

    // Use the alias and test that the block is visible on /BlockTestPageWithCapitalLetters.
    $this->drupalGet('block-test-page-with-capital-letters');
    $this->assertSession()->pageTextContains($title);
  }

}
