<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that field item lists are held as weak references without data loss.
 *
 * @see \Drupal\Core\Entity\ContentEntityBase
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class ContentEntityWeakReferenceTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'entity_test'];

  /**
   * The entity_test_mulrev storage.
   */
  protected EntityStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->installEntitySchema('entity_test_mulrev');
    $this->storage = $this->container->get('entity_type.manager')->getStorage('entity_test_mulrev');
  }

  /**
   * Tests that weak field references engage without losing any data.
   */
  public function testWeakReferences(): void {
    // Reading a field on a loaded entity stores it as a weak reference rather
    // than a strong one.
    $entity = EntityTestMulRev::create(['name' => 'name a', 'language' => 'en']);
    $entity->save();
    $reloaded = $this->storage->loadUnchanged($entity->id());
    $this->assertSame('name a', $reloaded->get('name')->value);
    $this->assertArrayNotHasKey('name', $this->peek($reloaded, 'fields'));
    $this->assertArrayHasKey('name', $this->peek($reloaded, 'fieldWeakReferences'));

    // Item-level default values (uuid, created) are applied without notifying
    // the entity, so they must be held as strong references during creation and
    // survive garbage collection before the entity is saved.
    $entity = EntityTestMulRev::create(['name' => 'name b', 'language' => 'en']);
    $uuid = $entity->uuid();
    $this->assertNotEmpty($uuid);
    gc_collect_cycles();
    $entity->save();
    $reloaded = $this->storage->loadUnchanged($entity->id());
    $this->assertSame($uuid, $reloaded->uuid());
    $this->assertNotEmpty($reloaded->get('created')->value);

    // Changes persist through a save via every way of editing a field, even
    // when the field object is not retained and a collection cycle is forced.
    $entity = EntityTestMulRev::create(['name' => 'start', 'language' => 'en']);
    $entity->save();
    $id = $entity->id();
    $cases = [
      'set' => fn (EntityTestMulRev $e) => $e->set('name', 'via set'),
      '__set' => fn (EntityTestMulRev $e) => $e->name = 'via __set',
      'get-value' => fn (EntityTestMulRev $e) => $e->get('name')->value = 'via get-value',
      'magic-value' => fn (EntityTestMulRev $e) => $e->name->value = 'via magic-value',
    ];
    foreach ($cases as $expected => $edit) {
      $reloaded = $this->storage->loadUnchanged($id);
      $edit($reloaded);
      gc_collect_cycles();
      $reloaded->save();
      $this->assertSame("via $expected", $this->storage->loadUnchanged($id)->get('name')->value, "Edit via $expected persists.");
    }

    // A weakly-accessed modification survives serialization.
    $reloaded = $this->storage->loadUnchanged($id);
    $reloaded->get('name')->value = 'after serialize';
    $restored = unserialize(serialize($reloaded));
    $this->assertSame('after serialize', $restored->get('name')->value);

    // A weakly-accessed modification survives cloning.
    $reloaded = $this->storage->loadUnchanged($id);
    $reloaded->get('name')->value = 'after clone';
    $clone = clone $reloaded;
    $this->assertSame('after clone', $clone->get('name')->value);

    // Creating a translation persists its default values.
    $reloaded = $this->storage->loadUnchanged($id);
    $reloaded->addTranslation('de', ['name' => 'german']);
    gc_collect_cycles();
    $reloaded->save();
    $translation = $this->storage->loadUnchanged($id)->getTranslation('de');
    $this->assertSame('german', $translation->get('name')->value);
    $this->assertSame('de', $translation->language()->getId());
  }

  /**
   * Reads a protected property.
   */
  protected function peek(object $entity, string $name): mixed {
    return (new \ReflectionProperty($entity, $name))->getValue($entity);
  }

}
