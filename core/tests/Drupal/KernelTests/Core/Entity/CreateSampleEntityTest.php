<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ContentEntityStorageBase::createWithSampleValues method.
 */
#[CoversClass(ContentEntityStorageBase::class)]
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class CreateSampleEntityTest extends KernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path_alias',
    'filter',
    'text',
    'file',
    'user',
    'node',
    'comment',
    'taxonomy',
    'menu_link_content',
    'link',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('comment_type');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('menu_link_content');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Vocabulary::create(['name' => 'Tags', 'vid' => 'tags'])->save();
  }

  /**
   * Tests sample value content entity creation of all types.
   *
   * @legacy-covers ::createWithSampleValues
   */
  public function testSampleValueContentEntity(): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
      if (!$entity_storage instanceof ContentEntityStorageInterface) {
        continue;
      }
      $values = [];
      if ($label = $definition->getKey('label')) {
        $values[$label] = $this->randomString();
      }

      // Create sample entities with bundles.
      if ($bundle_type = $definition->getBundleEntityType()) {
        $bundles = array_map(fn (EntityInterface $bundle) => $bundle->id(), $this->entityTypeManager->getStorage($bundle_type)->loadMultiple());
      }
      // Create sample entities without bundles.
      else {
        $bundles = [FALSE];
      }
      foreach ($bundles as $bundle) {
        $entity = $entity_storage->createWithSampleValues($bundle, $values);
        $entity->save();
        $entity_storage->resetCache([$entity->id()]);
        $entity = $entity_storage->load($entity->id());
        $violations = $entity->validate();
        $this->assertCount(0, $violations, (string) $violations);
        if ($label) {
          $this->assertEquals($values[$label], $entity->label());
        }
      }
    }
  }

  /**
   * Tests that sample entities are always saved as the default translation.
   *
   * @legacy-covers ::createWithSampleValues
   */
  public function testSampleValueDefaultTranslation(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    // The default_langcode field would otherwise get a random boolean, so try
    // enough times that a regression cannot pass by chance.
    for ($i = 0; $i < 20; $i++) {
      $title = $this->randomMachineName();
      $node = $storage->createWithSampleValues('article', ['title' => $title]);
      $node->save();

      $storage->resetCache([$node->id()]);
      $node = $storage->load($node->id());
      $this->assertTrue($node->isDefaultTranslation());
      $this->assertSame($title, $node->label());
    }
  }

}
