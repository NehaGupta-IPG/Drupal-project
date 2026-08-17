<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DefaultContent;

use Drupal\Core\DefaultContent\Exporter;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMapField;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the Default Content Importer.
 */
#[Group('DefaultContent')]
#[CoversClass(Importer::class)]
#[RunTestsInSeparateProcesses]
class ImporterTest extends KernelTestBase implements EventSubscriberInterface {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'entity_test'];

  /**
   * Whether the imported content entities were syncing.
   *
   * @see ::onPreSave()
   */
  private bool $wasSyncing = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');

    $this->container->get(EventDispatcherInterface::class)
      ->addSubscriber($this);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreEntityImportEvent::class => 'onPreEntityImport',
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for entity_test entities.
   */
  #[Hook('entity_test_presave')]
  public function onPreSave(ContentEntityInterface $entity): void {
    $this->wasSyncing = $entity->isSyncing();
  }

  /**
   * Modify entity data before it is imported.
   *
   * @param \Drupal\Core\DefaultContent\PreEntityImportEvent $event
   *   The event being handled.
   */
  public function onPreEntityImport(PreEntityImportEvent $event): void {
    self::assertContains($event->metadata['entity_type'], ['entity_test', 'entity_test_map_field']);

    if ($event->metadata['uuid'] === '2ab4da9f-7132-4578-99ac-3ee46ce8ce09') {
      $event->data['default']['name'] = [
        ['value' => 'Changed name'],
      ];
    }
    // Anything we put in `_meta` should be discarded.
    $event->data['_meta']['entity_type'] = 'This will be ignored.';
  }

  /**
   * Tests changing entity data upon import.
   */
  public function testChangeDataOnImport(): void {
    $this->setUpCurrentUser(admin: TRUE);

    $finder = new Finder($this->root . '/core/tests/fixtures/pre_entity_import_default_content');
    $this->container->get(Importer::class)->importContent($finder);
    self::assertTrue($this->wasSyncing);

    $entity = $this->container->get(EntityRepositoryInterface::class)
      ->loadEntityByUuid('entity_test', '2ab4da9f-7132-4578-99ac-3ee46ce8ce09');
    self::assertSame('Changed name', $entity?->label());
  }

  /**
   * Tests that a `map` field survives an export/import round trip.
   *
   * A `map` field item has no statically defined properties, so it cannot be
   * exported or imported the way most other field types are.
   */
  public function testMapFieldRoundTrip(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test_map_field');
    $this->setUpCurrentUser(admin: TRUE);

    $map = [
      'foo' => 'bar',
      'nested' => ['baz' => 'qux'],
    ];
    $entity = EntityTestMapField::create([
      'name' => 'Has a map field',
      'data' => [$map],
    ]);
    $entity->save();

    $export = $this->container->get(Exporter::class)->export($entity);
    $dir = 'public://' . uniqid('map-field-');
    mkdir($dir);
    file_put_contents($dir . '/' . $entity->uuid() . '.yml', (string) $export);
    $entity->delete();

    $this->container->get(Importer::class)->importContent(new Finder($dir));

    $imported = $this->container->get(EntityRepositoryInterface::class)
      ->loadEntityByUuid('entity_test_map_field', $entity->uuid());
    self::assertInstanceOf(EntityTestMapField::class, $imported);
    self::assertSame($map, $imported->get('data')->first()?->getValue());
  }

  /**
   * Tests that a compound, computed field survives an export/import round trip.
   *
   * An entity reference field item has more than one statically defined
   * property, and its `entity` property is computed. Computed properties are
   * excluded from `FieldItemInterface::getProperties()` by default, but must
   * still be resolved during import.
   */
  public function testEntityReferenceFieldRoundTrip(): void {
    $this->installEntitySchema('user');
    $this->setUpCurrentUser(admin: TRUE);

    $owner = $this->createUser();
    $entity = EntityTest::create([
      'name' => 'Has an entity reference field',
      'user_id' => $owner->id(),
    ]);
    $entity->save();

    $export = $this->container->get(Exporter::class)->export($entity);
    $dir = 'public://' . uniqid('entity-reference-field-');
    mkdir($dir);
    file_put_contents($dir . '/' . $entity->uuid() . '.yml', (string) $export);
    $entity->delete();

    $this->container->get(Importer::class)->importContent(new Finder($dir));

    $imported = $this->container->get(EntityRepositoryInterface::class)
      ->loadEntityByUuid('entity_test', $entity->uuid());
    self::assertInstanceOf(EntityTest::class, $imported);
    self::assertSame($owner->id(), $imported->get('user_id')->target_id);
    self::assertSame($owner->uuid(), $imported->get('user_id')->entity?->uuid());
  }

}
