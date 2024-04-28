<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional\Update;

use Drupal\block_content\Entity\BlockContent;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update functions for the Block Content module.
 *
 * @group block_content
 */
class BlockContentUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests adding an owner to block content.
   *
   * @see block_content_update_10301()
   * @see block_content_post_update_set_owner()
   */
  public function testAddAndSetOwnerField(): void {
    $user1 = $this->drupalCreateUser();
    $user2 = $this->drupalCreateUser();
    $user3 = $this->drupalCreateUser();

    $block1 = BlockContent::create([
      'info' => 'Test 1',
      'type' => 'basic',
      'revision_user' => $user1->id(),
    ]);
    $block1->save();
    $block1->setNewRevision();
    $block1->setRevisionUserId($user2->id())->save();

    $block2 = BlockContent::create([
      'info' => 'Test 2',
      'type' => 'basic',
      'revision_user' => $user2->id(),
    ]);
    $block2->save();
    $block2->setNewRevision();
    $block2->setRevisionUserId($user3->id())->save();

    $block3 = BlockContent::create([
      'info' => 'Test 3',
      'type' => 'basic',
      'revision_user' => $user3->id(),
    ]);
    $block3->save();
    $block3->setNewRevision();
    $block3->setRevisionUserId($user1->id())->save();

    $block4 = BlockContent::create([
      'info' => 'Test 4',
      'type' => 'basic',
      'revision_user' => NULL,
    ]);
    $block4->save();

    $this->runUpdates();

    $block1 = BlockContent::load($block1->id());
    $this->assertEquals($user1->id(), $block1->getOwnerId());
    $block2 = BlockContent::load($block2->id());
    $this->assertEquals($user2->id(), $block2->getOwnerId());
    $block3 = BlockContent::load($block3->id());
    $this->assertEquals($user3->id(), $block3->getOwnerId());
    $block4 = BlockContent::load($block4->id());
    $this->assertEquals(0, $block4->getOwnerId());
  }

}
