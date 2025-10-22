<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\block\Entity\Block;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that hide_untranslated_menu_links is set.
 *
 * @see system_post_update_implement_schema_for_hide_untranslated_menu_links()
 */
#[Group('update')]
#[RunTestsInSeparateProcesses]
class HideUntranslatedMenuUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Ensures that hide_untranslated_menu_links is set after updating.
   */
  public function testUpdate(): void {
    $blocks = Block::loadMultiple();
    foreach ($blocks as $block) {
      if (strpos($block->getPluginId(), 'menu_block:') === 0) {
        $block_settings = $block->get('settings');
        $this->assertFalse(array_key_exists('hide_untranslated_menu_links', $block_settings));
      }
    }

    $this->runUpdates();

    $blocks = Block::loadMultiple();
    foreach ($blocks as $block) {
      if (strpos($block->getPluginId(), 'menu_block:') === 0) {
        $block_settings = $block->get('settings');
        $this->assertTrue(array_key_exists('hide_untranslated_menu_links', $block_settings));
      }
    }
  }

}
