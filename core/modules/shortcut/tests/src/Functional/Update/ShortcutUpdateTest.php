<?php

declare(strict_types=1);

namespace Drupal\Tests\shortcut\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\shortcut\Entity\Shortcut;

/**
 * Tests update functions for the Shortcut module.
 *
 * @group shortcut
 */
class ShortcutUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the update to fix empty shortcut titles.
   *
   * @see shortcut_post_update_fix_empty_titles()
   */
  public function testFixShortcutEmptyTitle(): void {
    // Create a shortcut without a title.
    $shortcut = Shortcut::create([
      'shortcut_set' => 'default',
      'weight' => -20,
      'link' => [
        'uri' => 'internal:/admin/content',
        'options' => [
          'fragment' => 'new',
        ],
      ],
    ]);
    $shortcut->save();
    $this->assertEmpty($shortcut->getTitle());

    $this->runUpdates();

    $shortcut = ShortCut::load($shortcut->id());
    $this->assertEquals('(Empty)', $shortcut->getTitle());

  }

}
