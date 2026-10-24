<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests granular inline block permissions in layout builder.
 *
 * @group layout_builder
 */
final class InlineBlockAccessTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
    setCurrentUser as drupalSetCurrentUser;
    setUpCurrentUser as drupalSetUpCurrentUser;
  }

  /**
   * Block type IDs for testing.
   */
  private const BLOCK_TYPE_IDS = [
    'accessible_block_bundle',
    'restricted_block_bundle',
  ];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'block',
    'block_content',
    'layout_discovery',
    'layout_builder',
  ];

  /**
   * Section storage used for this test.
   */
  private SectionStorageInterface $sectionStorage;

  /**
   * UUIDs of inline blocks added to the test layout.
   */
  private array $uuids;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installConfig([
      'user',
      'system',
      'field',
    ]);

    // Create inline block types.
    foreach (self::BLOCK_TYPE_IDS as $block_type_id) {
      $block_content_type = BlockContentType::create([
        'id' => $block_type_id,
        'label' => $block_type_id,
        'revision' => 0,
      ]);
      $block_content_type->save();
    }

    // Set up test section storage.
    $this->sectionStorage = $this->container->get('plugin.manager.layout_builder.section_storage')
      ->createInstance('defaults');
    $entity_view_display = $this->container->get('entity_type.manager')->getStorage('entity_view_display')->create([
      'targetEntityType' => 'user',
      'bundle' => 'user',
      'mode' => 'default',
    ]);
    $entity_view_display->enableLayoutBuilder();
    $entity_view_display->save();
    $this->sectionStorage->setContextValue('display', $entity_view_display);

    // Add 2 blocks to the layout.
    $uuidGenerator = $this->container->get('uuid');
    foreach (self::BLOCK_TYPE_IDS as $block_type_id) {
      $uuid = $uuidGenerator->generate();
      $this->uuids[$block_type_id] = $uuid;
      $component = new SectionComponent($uuid, 'content', [
        'id' => 'inline_block:' . $block_type_id,
      ]);
      $this->sectionStorage->getSection(0)->appendComponent($component);
    }
    $this->sectionStorage->save();
  }

  /**
   * Test inline block list contents.
   */
  public function testInlineBlockList(): void {
    // Login as a restricted access user.
    $this->drupalSetUpCurrentUser([], [
      'configure any layout',
      'create and edit accessible custom blocks',
      'create ' . self::BLOCK_TYPE_IDS[0] . ' block content',
      'edit any ' . self::BLOCK_TYPE_IDS[0] . ' block content',
    ]);

    $url = Url::fromRoute('layout_builder.choose_inline_block', [
      'section_storage_type' => 'defaults',
      'section_storage' => $this->sectionStorage->getStorageId(),
      'delta' => 0,
      'region' => 'content',
    ]);
    $request = Request::create($url->toString());
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
    $content = $response->getContent();
    $this->assertStringContainsString(self::BLOCK_TYPE_IDS[0], $content);
    $this->assertStringNotContainsString(self::BLOCK_TYPE_IDS[1], $content);

    foreach (self::BLOCK_TYPE_IDS as $block_type_id) {
      // Create access test.
      $url = Url::fromRoute('layout_builder.add_block', [
        'section_storage_type' => 'defaults',
        'section_storage' => $this->sectionStorage->getStorageId(),
        'delta' => 0,
        'region' => 'content',
        'plugin_id' => 'inline_block:' . $block_type_id,
      ]);
      $request = Request::create($url->toString());
      $response = $this->container->get('http_kernel')->handle($request);
      if ($block_type_id === 'accessible_block_bundle') {
        $this->assertEquals(200, $response->getStatusCode());
      }
      else {
        $this->assertEquals(403, $response->getStatusCode());
      }

      // Edit access test.
      $url = Url::fromRoute('layout_builder.update_block', [
        'section_storage_type' => 'defaults',
        'section_storage' => $this->sectionStorage->getStorageId(),
        'delta' => 0,
        'region' => 'content',
        'uuid' => $this->uuids[$block_type_id],
      ]);
      $request = Request::create($url->toString());
      $response = $this->container->get('http_kernel')->handle($request);
      if ($block_type_id === 'accessible_block_bundle') {
        $this->assertEquals(200, $response->getStatusCode());
      }
      else {
        $this->assertEquals(403, $response->getStatusCode());
      }
    }
  }

}
