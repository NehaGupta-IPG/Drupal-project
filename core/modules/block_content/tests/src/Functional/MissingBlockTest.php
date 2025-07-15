<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test behavior of block_content blocks when the content entity doesn't exist.
 */
#[Group('block_content')]
class MissingBlockTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->placeBlock('page_title_block', ['region' => 'content']);
    $this->copyConfig(\Drupal::service('config.storage'), \Drupal::service('config.storage.sync'));
    $this->configImporter = $this->configImporter();
  }

  /**
   * Tests there is a simple way to add a missing block.
   */
  public function testMissingBlock(): void {
    // Create a block config entity that references a block content entity that
    // does not exist.
    $uuid = \Drupal::service('uuid')->generate();
    $block = $this->placeBlock('block_content:' . $uuid, [
      'id' => 'broken_block',
      'region' => 'content',
      'view_mode' => 'full',
    ]);
    // Update the block configuration entity in the sync directory to force a
    // dependency on the block content entity.
    $storage = \Drupal::service('config.storage');
    $sync = \Drupal::service('config.storage.sync');
    $block_content = BlockContent::create([
      'uuid' => $uuid,
      'type' => 'basic',
    ]);
    $block_data = $storage->read($block->getConfigDependencyName());
    // Add the dependency.
    $block_data['dependencies']['content'][] = $block_content->getConfigDependencyName();
    $sync->write($block->getConfigDependencyName(), $block_data);
    // Import which will fire the event.
    $this->configImporter->reset()->import();
    $missing = \Drupal::state()->get('block_content_missing_entities', []);
    $this->assertArrayHasKey($uuid, $missing);
    $this->assertEquals('basic', $missing[$uuid]['bundle']);

    $this->drupalLogin($this->adminUser);
    $frontPage = Url::fromRoute('<front>');
    $this->drupalGet($frontPage);
    $assert = $this->assertSession();
    $addMissingUrl = Url::fromRoute('block_content.add_missing', [
      'block_content_type' => 'basic',
      'uuid' => $uuid,
    ]);
    $assert->pageTextContains('The content of the (basic) block is missing.');
    $assert->linkByHrefExists($addMissingUrl->setAbsolute()->toString());
    $session = $this->getSession();
    $currentUrl = $session->getCurrentUrl();
    $this->clickLink('Add missing content');
    $body = $this->randomMachineName(16);
    $page = $this->getSession()->getPage();
    $page->fillField('Block description', 'Missing Block');
    $page->fillField('Body', $body);
    $page->pressButton('Save');
    // Assert we ended back where we started from. Just check starts with as
    // the URL may have the check_logged_in query param.
    $this->assertStringStartsWith($currentUrl, $session->getCurrentUrl());
    $block_content = \Drupal::service('entity.repository')->loadEntityByUuid('block_content', $uuid);
    $this->assertNotEmpty($block_content);
    $this->assertEquals('basic', $block_content->bundle());
    // The render cache will contain the broken block output.
    Cache::invalidateTags(['rendered']);
    $this->drupalGet($frontPage);
    $assert->pageTextContains($body);
    $this->drupalGet($addMissingUrl);
    $assert->statusCodeEquals(403);
    $missing = \Drupal::state()->get('block_content_missing_entities', []);
    $this->assertArrayHasKey($uuid, $missing);
  }

}
