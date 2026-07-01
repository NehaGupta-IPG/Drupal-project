<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\DefaultContent;

use Drupal\Core\DefaultContent\Existing;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\ImportException;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
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
   * @var bool
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
    self::assertSame('entity_test', $event->metadata['entity_type']);

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
   * Tests importing a single entity directly, via ::importEntity().
   */
  public function testImportEntity(): void {
    $this->setUpCurrentUser(admin: TRUE);

    /** @var \Drupal\Core\DefaultContent\Importer $importer */
    $importer = $this->container->get(Importer::class);
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $repository */
    $repository = $this->container->get(EntityRepositoryInterface::class);

    // A successful import returns the saved, syncing entity.
    $uuid = 'aaaaaaaa-0000-4000-8000-000000000001';
    $entity = $importer->importEntity($this->buildData($uuid, [
      'name' => [['value' => 'First entity']],
    ]));
    self::assertInstanceOf(ContentEntityInterface::class, $entity);
    self::assertFalse($entity->isNew());
    self::assertSame('First entity', $entity->label());
    self::assertTrue($this->wasSyncing);
    self::assertNotNull($repository->loadEntityByUuid('entity_test', $uuid));

    // Importing an existing entity with Existing::Skip returns NULL and leaves
    // the stored entity untouched.
    $this->wasSyncing = FALSE;
    $skipped = $importer->importEntity($this->buildData($uuid, [
      'name' => [['value' => 'This should be ignored']],
    ]), Existing::Skip);
    self::assertNull($skipped);
    $reloaded = $repository->loadEntityByUuid('entity_test', $uuid);
    self::assertSame('First entity', $reloaded?->label());
    self::assertFalse($this->wasSyncing);

    // Importing an existing entity with the default Existing::Error throws.
    try {
      $importer->importEntity($this->buildData($uuid));
      self::fail('Expected an ImportException for an existing entity.');
    }
    catch (ImportException $e) {
      self::assertSame("entity_test $uuid already exists.", $e->getMessage());
    }

    // A non-content entity type is rejected before any entity is built.
    $config_uuid = 'aaaaaaaa-0000-4000-8000-000000000002';
    try {
      $importer->importEntity($this->buildData($config_uuid, meta: ['entity_type' => 'user_role']));
      self::fail('Expected an ImportException for a config entity type.');
    }
    catch (ImportException $e) {
      self::assertSame("Content entity $config_uuid is a 'user_role', which is not a content entity type.", $e->getMessage());
    }

    // With an account, an entity that has no owner is assigned to it.
    $account = $this->createUser();
    $owned = $importer->importEntity($this->buildData('aaaaaaaa-0000-4000-8000-000000000003', [
      'user_id' => [['target_id' => 0]],
    ]), account: $account);
    self::assertSame($account->id(), $owned?->getOwner()->id());

    // Without an account, the owner is left as-is (the new NULL-guard branch).
    $unowned = $importer->importEntity($this->buildData('aaaaaaaa-0000-4000-8000-000000000004', [
      'user_id' => [['target_id' => 0]],
    ]));
    self::assertSame(0, (int) $unowned?->getOwnerId());
  }

  /**
   * Tests that a failed import does not poison a later one on the same importer.
   *
   * @see \Drupal\Core\DefaultContent\Importer::toEntity()
   */
  public function testDependenciesResetAfterFailure(): void {
    $this->setUpCurrentUser(admin: TRUE);

    /** @var \Drupal\Core\DefaultContent\Importer $importer */
    $importer = $this->container->get(Importer::class);

    // A root entity (it declares dependencies) whose build fails part-way
    // through, after the importer has recorded its dependencies.
    $failing = $this->buildData('bbbbbbbb-0000-4000-8000-000000000001', [
      'this_field_does_not_exist' => [['value' => 'boom']],
    ], [
      'depends' => ['cccccccc-0000-4000-8000-000000000099' => 'entity_test'],
    ]);
    try {
      $importer->importEntity($failing);
      self::fail('Expected the failing import to throw.');
    }
    catch (\InvalidArgumentException) {
      // Expected: the referenced field does not exist.
    }

    // A second entity that resolves its own dependency. If the failed import
    // left stale dependencies behind, this entity would not be treated as a
    // dependency-graph root and its reference would not resolve.
    $owner = $this->createUser();
    $data = $this->buildData('bbbbbbbb-0000-4000-8000-000000000002', [
      'user_id' => [['entity' => $owner->uuid()]],
    ], [
      'depends' => [$owner->uuid() => 'user'],
    ]);
    $entity = $importer->importEntity($data);
    self::assertSame($owner->id(), $entity?->getOwner()->id());
  }

  /**
   * Builds a decoded entity_test data array, as produced by the content Finder.
   *
   * @param string $uuid
   *   The entity UUID.
   * @param array $default
   *   (optional) Field values for the `default` section. Merged over a `name`.
   * @param array $meta
   *   (optional) Values for the `_meta` section, taking precedence over the
   *   defaults (e.g. to override `entity_type` or add `depends`).
   *
   * @return array
   *   The decoded data array.
   */
  private function buildData(string $uuid, array $default = [], array $meta = []): array {
    return [
      '_meta' => $meta + [
        'version' => '1.0',
        'entity_type' => 'entity_test',
        'uuid' => $uuid,
        'bundle' => 'entity_test',
        'default_langcode' => 'en',
        'path' => "fake/$uuid.yml",
      ],
      'default' => $default + [
        'name' => [['value' => 'Imported entity']],
      ],
    ];
  }

}
