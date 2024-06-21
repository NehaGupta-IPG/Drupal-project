<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Tests the recursion protection in \Drupal\layout_builder\SectionComponent.
 *
 * @group layout_builder
 */
class SectionComponentRecursionTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'layout_builder',
    'layout_builder_test',
    'layout_discovery',
    'layout_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests that Layout Builder protects against recursive rendering.
   */
  public function testRecursion() {
    $entity_storage = $this->container->get('entity_type.manager')->getStorage('entity_test');
    // @todo Remove langcode workarounds after resolving
    //   https://www.drupal.org/node/2915034.
    $entity = $entity_storage->createWithSampleValues('entity_test', [
      'langcode' => 'en',
      'langcode_default' => TRUE,
    ]);
    $entity->save();

    $section = new Section('layout_onecol');
    $component = (new SectionComponent(\Drupal::service('uuid')->generate(), 'content', [
      'id' => 'layout_builder_test_entity_block',
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ]));
    $section->appendComponent($component);
    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
      'third_party_settings' => [
        'layout_builder' => [
          'sections' => [$section],
        ],
      ],
    ]);
    $display->enableLayoutBuilder();
    $display->save();

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('entity_test')
      ->view($entity);

    // This would recursively render $entity without our protection, and throw
    // an exception.
    $this->assertFalse(empty($this->render($build)));
  }

}
