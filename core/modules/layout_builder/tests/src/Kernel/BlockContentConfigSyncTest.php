<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\layout_builder\Section;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config for inline_block components is correctly imported using UUIDs.
 */
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class BlockContentConfigSyncTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config',
    'block_content',
    'layout_builder',
    'layout_discovery',
  ];

  /**
   * The name of the config the test imports.
   *
   * @var string
   */
  protected const CONFIG_NAME = 'core.entity_view_display.entity_test.entity_test.default';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('block_content');
  }

  /**
   * Tests inline_block components import when revision IDs don't match.
   */
  public function testBlockContentConfigSync(): void {
    // Create a block_content type.
    $block_content_type_storage = $this->entityTypeManager->getStorage('block_content_type');
    $basic_block_type = $block_content_type_storage->create([
      'id' => 'basic',
      'label' => 'Basic block',
      'status' => TRUE,
    ]);
    $basic_block_type->save();

    // Create a view display for the entity_test entity type, enabled for layout
    // builder.
    $view_display = $this->entityTypeManager->getStorage('entity_view_display')->create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $view_display->enableLayoutBuilder()
      ->save();

    $section_storage_manager = $this->container->get('plugin.manager.layout_builder.section_storage');

    // Get the section storage for our entity display.
    $section_storage = $section_storage_manager->loadEmpty('defaults');
    $contexts['display'] = EntityContext::fromEntity($view_display);
    $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_display->getMode());
    $section_storage->setContext('display', EntityContext::fromEntity($view_display));

    // Add a new section to the layout.
    $section_storage->insertSection(0, new Section('layout_onecol'));
    $view_display->save();

    // Create a block_content entity. This simulates a content synchronization
    // procedure, which brings the block_content entity from an external
    // environment into this one.
    $block_content_storage = $this->entityTypeManager->getStorage('block_content');
    $block_content = $block_content_storage->create([
      'type' => 'basic',
      'info' => 'Layout block',
    ]);
    $block_content->save();
    $block_revision_id = $block_content->getRevisionId();
    $block_uuid = $block_content->uuid->value;

    // Simulate a config import of the view display from the external
    // environment, where the layout has a new inline block component.
    $active = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($active, $sync);

    // Set up the config for the new component.
    $display_config = $active->read(self::CONFIG_NAME);
    $uuid_generator = $this->container->get('uuid');
    $uuid = $uuid_generator->generate();
    $inline_block_component = [
      'uuid' => $uuid,
      'region' => 'content',
      'configuration' => [
        'id' => 'inline_block:basic',
        'label' => 'Layout block',
        'label_display' => 'visible',
        'provider' => 'layout_builder',
        'view_mode' => 'full',
        'context_mapping' => [],
        // Use a different revision ID to simulate the external environment
        // having different revision IDs for block_content entities.
        'block_revision_id' => $block_revision_id + 42,
        'block_serialized' => NULL,
        'block_uuid' => $block_uuid,
      ],
      'additional' => [],
      'weight' => 0,
    ];
    $display_config['third_party_settings']['layout_builder']['sections'][0]['components'][$uuid] = $inline_block_component;

    $sync->write(self::CONFIG_NAME, $display_config);

    // Import the config.
    $this->configImporter()->import();

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $view_display */
    $view_display = $this->reloadEntity($view_display);

    // Verify the config saves without errors.
    $view_display->save();

    // Verify the inline_block component is now in the view display's layout
    // configuration.
    $section = $view_display->getSection(0);
    $components = $section->getComponents();
    $this->assertCount(1, $components);

    /** @var \Drupal\layout_builder\SectionComponent $component */
    $component = reset($components);
    /** @var \Drupal\Core\Block\BlockPluginInterface $component_plugin */
    $component_plugin = $component->getPlugin();
    $component_plugin_configuration = $component_plugin->getConfiguration();
    $this->assertEquals('Layout block', $component_plugin_configuration['label']);

    // Get the render array for the inline_block component and verify that the
    // correct block_content entity is being rendered.
    $build = $component_plugin->build();
    $build_array_block_content_entity = $build['#block_content'];
    $this->assertEquals($block_content->id(), $build_array_block_content_entity->id());
    $this->assertEquals($block_content->getRevisionId(), $build_array_block_content_entity->getRevisionId());
    $this->assertEquals($block_content->uuid->value, $build_array_block_content_entity->uuid->value);
  }

}
