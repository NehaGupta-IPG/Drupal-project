<?php

namespace Drupal\Tests\block_content\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Url;

/**
 * Test behavior of block_content blocks when the content entity doesn't exist.
 *
 * @group block_content
 */
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
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
    $this->configImporter = $this->configImporter();
  }

  /**
   * Tests there is a simple way to add a missing block.
   */
  public function testMissingBlock() {
    // Create a block config entity that references a block content entity that
    // does not exist.
    $uuid = $this->container->get('uuid')->generate();
    $block = $this->placeBlock('block_content:' . $uuid, [
      'id' => 'broken_block',
      'region' => 'content',
    ]);
    // Update the block configuration entity in the sync directory to force a
    // dependency on the block content entity.
    $storage = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
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
    $missing = $this->container->get('state')
      ->get('block_content_missing_entities', []);
    $this->assertTrue(!empty($missing[$uuid]));
    $this->assertEquals('basic', $missing[$uuid]['bundle']);

    $this->drupalLogin($this->adminUser);
    $frontPage = Url::fromRoute('<front>');
    $this->drupalGet($frontPage);
    $assert = $this->assertSession();
    $addMissingUrl = Url::fromRoute('block_content.add_missing', [
      'uuid' => $uuid,
      'block_content_type' => 'basic',
      // Default front page is user, which redirects to user/x for logged in
      // user.
    ], ['query' => ['destination' => $this->adminUser->toUrl()->toString()]]);
    $assert->responseContains(t('The content of the (%bundle) block is missing. <a href=":url">Add missing content</a>.', [
      '%bundle' => 'basic',
      ':url' => $addMissingUrl->toString(),
    ]));
    $session = $this->getSession();
    $currentUrl = $session->getCurrentUrl();
    $this->clickLink('Add missing content');
    $body = $this->randomMachineName(16);
    $page = $this->getSession()->getPage();
    $page->fillField('Block description', 'Missing Block');
    $page->fillField('Body', $body);
    $page->pressButton('Save');
    // Assert we ended back where we started from.
    $this->assertEquals($currentUrl, $session->getCurrentUrl());
    $block_content = $this->container->get('entity.repository')
      ->loadEntityByUuid('block_content', $uuid);
    $this->assertNotEmpty($block_content);
    $this->assertEquals('basic', $block_content->bundle());
    $this->drupalGet($frontPage);
    $assert->pageTextContains($body);
    $this->drupalGet($addMissingUrl);
    $assert->statusCodeEquals(403);
    $missing = $this->container->get('state')
      ->get('block_content_missing_entities', []);
    $this->assertTrue(!empty($missing[$uuid]));
  }

}
