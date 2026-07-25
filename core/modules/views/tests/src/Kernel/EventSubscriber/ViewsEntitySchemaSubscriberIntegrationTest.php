<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeEvent;
use Drupal\Core\Entity\EntityTypeEvents;
use Drupal\Tests\system\Functional\Entity\Traits\EntityDefinitionTestTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\views\EventSubscriber\ViewsEntitySchemaSubscriber.
 */
#[Group('Views')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
class ViewsEntitySchemaSubscriberIntegrationTest extends ViewsKernelTestBase {

  use EntityDefinitionTestTrait;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'entity_test_update',
    'text',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_view_entity_test',
    'test_view_entity_test_revision',
    'test_view_entity_test_data',
    'test_view_entity_test_additional_base_field',
  ];

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Whether the database driver embeds entity field data in JSON documents.
   *
   * Under the pgsql (JSON storage) driver a content entity's field data is
   * embedded in "jsonb" columns on the entity base table; the logical
   * "<entity>_field_data"/"<entity>_revision" tables do not exist physically
   * and \Drupal\pgsql\modules\views\ViewStorage collapses every view's base
   * table and handler tables onto the entity base table at config load/create.
   * The views therefore reference the base table for all fields regardless of
   * the entity's data/revision table layout, which changes both the expected
   * handler table names and which views a schema change actually rewrites.
   *
   * @var bool
   */
  protected bool $supportsJsonEntityStorage = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();

    $this->supportsJsonEntityStorage = Database::getConnection()->supportsJsonEntityStorage();

    $this->eventDispatcher = $this->container->get('event_dispatcher');
    $this->entityDefinitionUpdateManager = $this->container->get('entity.definition_update_manager');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->state = $this->container->get('state');

    // Install every entity type's schema that wasn't installed in the parent
    // method.
    foreach (array_diff_key($this->entityTypeManager->getDefinitions(), array_flip(['user', 'entity_test'])) as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $this->installEntitySchema($entity_type_id);
      }
    }
  }

  /**
   * Tests that views are disabled when an entity type is deleted.
   */
  public function testDeleteEntityType(): void {
    $entity_storage = $this->entityTypeManager->getStorage('view');

    // Make the test entity type revisionable.
    $this->updateEntityTypeToRevisionable(TRUE);

    $views = $entity_storage->loadMultiple();

    // Ensure that all test views exists.
    $this->assertTrue(isset($views['test_view_entity_test']));
    $this->assertTrue(isset($views['test_view_entity_test_revision']));
    $this->assertTrue(isset($views['test_view_entity_test_data']));
    $this->assertTrue(isset($views['test_view_entity_test_additional_base_field']));

    $event = new EntityTypeEvent($this->entityTypeManager->getDefinition('entity_test_update'));
    $this->eventDispatcher->dispatch($event, EntityTypeEvents::DELETE);

    // We expect that views which use 'entity_test_update' as base tables are
    // disabled.
    $views = $entity_storage->loadMultiple();

    // Ensure that all test views still exists after the deletion of the
    // entity type.
    $this->assertTrue(isset($views['test_view_entity_test']));
    $this->assertTrue(isset($views['test_view_entity_test_revision']));
    $this->assertTrue(isset($views['test_view_entity_test_data']));
    $this->assertTrue(isset($views['test_view_entity_test_additional_base_field']));

    // Ensure that they are all disabled.
    $this->assertFalse($views['test_view_entity_test']->status());
    $this->assertFalse($views['test_view_entity_test_revision']->status());
    $this->assertFalse($views['test_view_entity_test_data']->status());
    $this->assertFalse($views['test_view_entity_test_additional_base_field']->status());
  }

  /**
   * Tests that renaming base tables adapts the views.
   */
  public function testBaseTableRename(): void {
    $this->renameBaseTable();
    $this->applyEntityUpdates('entity_test_update');

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test');

    // Ensure the base table got renamed, so also the views fields.
    $this->assertEquals('entity_test_update_new', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update_new', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update_new', $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated. Under JSON storage
    // the revision view is collapsed onto the same base table, so renaming the
    // base table rewrites it as well.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [
      'test_view_entity_test',
      'test_view_entity_test_data',
      'test_view_entity_test_additional_base_field',
      'test_view_entity_test_revision',
    ] : [
      'test_view_entity_test',
      'test_view_entity_test_data',
      'test_view_entity_test_additional_base_field',
    ]);
  }

  /**
   * Tests that renaming data tables adapts the views.
   */
  public function testDataTableRename(): void {
    $this->updateEntityTypeToTranslatable(TRUE);

    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_data');
    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    // Ensure that the data table is used. Under JSON storage the data table is
    // collapsed onto the base table.
    $this->assertEquals($this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_data', $display['display_options']['fields']['name']['table']);

    $this->renameDataTable();
    $this->applyEntityUpdates('entity_test_update');

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_data');

    // Ensure the data table got renamed, so also the views fields.
    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    // Under JSON storage the data table (and thus its rename) is collapsed onto
    // the base table, so the handler keeps referencing the base table.
    $this->assertEquals($this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_data_new', $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated. Under JSON storage
    // only the data view is rewritten; the base view keeps referencing the base
    // table, which is unaffected by the data table rename.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [
      'test_view_entity_test_data',
    ] : [
      'test_view_entity_test',
      'test_view_entity_test_data',
    ]);
  }

  /**
   * Tests that renaming revision tables adapts the views.
   */
  public function testRevisionBaseTableRename(): void {
    $this->updateEntityTypeToRevisionable(TRUE);

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_revision');
    // Under JSON storage the revision table is collapsed onto the base table.
    $revision_table = $this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision';
    $this->assertEquals($revision_table, $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals($revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($revision_table, $display['display_options']['fields']['name']['table']);

    $this->renameRevisionBaseTable();
    $this->applyEntityUpdates('entity_test_update');

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_revision');

    // Ensure the base table got renamed, so also the views fields. Under JSON
    // storage no view references the revision table (it is collapsed onto the
    // base table), so the rename leaves every handler on the base table.
    $renamed_revision_table = $this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision_new';
    $this->assertEquals($renamed_revision_table, $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals($renamed_revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($renamed_revision_table, $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated.
    $this->assertUpdatedViews([
      'test_view_entity_test_revision',
    ]);
  }

  /**
   * Tests that renaming revision tables adapts the views.
   */
  public function testRevisionDataTableRename(): void {
    $this->updateEntityTypeToRevisionableAndTranslatable(TRUE);

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_revision');
    // Under JSON storage the revision and revision data tables are collapsed
    // onto the base table.
    $revision_table = $this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision';
    $this->assertEquals($revision_table, $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals($revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision_data', $display['display_options']['fields']['name']['table']);

    $this->renameRevisionDataTable();
    $this->applyEntityUpdates('entity_test_update');

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_revision');

    // Ensure the base table got renamed, so also the views fields. Under JSON
    // storage no view references the revision data table, so the rename leaves
    // every handler on the base table.
    $this->assertEquals($revision_table, $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals($revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision_data_new', $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [] : [
      'test_view_entity_test',
      'test_view_entity_test_revision',
    ]);
  }

  /**
   * Tests that adding data tables adapts the views.
   */
  public function testDataTableAddition(): void {
    $this->updateEntityTypeToTranslatable(TRUE);

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test');

    // Ensure the data table got renamed, so also the views fields. Under JSON
    // storage the data table is collapsed onto the base table.
    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_data', $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [] : [
      'test_view_entity_test',
    ]);
  }

  /**
   * Tests that enabling revisions doesn't do anything.
   */
  public function testRevisionEnabling(): void {
    $this->updateEntityTypeToRevisionable(TRUE);

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test');

    // Ensure that nothing happens.
    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated.
    $this->assertUpdatedViews([]);
  }

  /**
   * Tests that removing revision support disables the view.
   */
  public function testRevisionDisabling(): void {
    $this->updateEntityTypeToRevisionable(TRUE);
    $this->updateEntityTypeToNotRevisionable(TRUE);

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test_revision');

    if ($this->supportsJsonEntityStorage) {
      // Under JSON storage the revision view is collapsed onto the (still
      // existing) entity base table, so removing revision support does not
      // disable it and no view is rewritten.
      $this->assertTrue($view->status());
      $this->assertUpdatedViews([]);
    }
    else {
      $this->assertFalse($view->status());

      // Check that only the impacted views have been updated.
      $this->assertUpdatedViews([
        'test_view_entity_test_revision',
      ]);
    }
  }

  /**
   * Tests a bunch possible entity definition table updates.
   */
  public function testVariousTableUpdates(): void {
    // We want to test the following permutations of entity definition updates:
    // base <-> base + translation
    // base + translation <-> base + translation + revision
    // base + revision <-> base + translation + revision
    // base <-> base + revision
    // base <-> base + translation + revision.

    // Under JSON entity storage the data table is collapsed onto the base table, so
    // translatable fields keep referencing the base table.
    $data_table = $this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_data';

    // Base <-> base + translation.
    $this->updateEntityTypeToTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($data_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToNotTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    $this->resetEntityType();

    // Base + translation <-> base + translation + revision.
    $this->updateEntityTypeToTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($data_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToRevisionable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($data_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToNotRevisionable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($data_table, $display['display_options']['fields']['name']['table']);

    $this->resetEntityType();

    // Base + revision <-> base + translation + revision.
    $this->updateEntityTypeToRevisionable();
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($data_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToNotTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    $this->resetEntityType();

    // Base <-> base + revision.
    $this->updateEntityTypeToRevisionable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToNotRevisionable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    $this->resetEntityType();

    // Base <-> base + translation + revision.
    $this->updateEntityTypeToRevisionable(TRUE);
    $this->updateEntityTypeToTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals($data_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToNotRevisionable(TRUE);
    $this->updateEntityTypeToNotTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay();

    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated. Under JSON storage
    // the data/revision tables collapse onto the base table, so only the data
    // view (which references the collapsed data table) is rewritten.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [
      'test_view_entity_test_data',
    ] : [
      'test_view_entity_test',
      'test_view_entity_test_data',
      'test_view_entity_test_revision',
    ]);
  }

  /**
   * Tests some possible entity table updates for a revision view.
   */
  public function testVariousTableUpdatesForRevisionView(): void {
    // Under JSON storage the revision and revision data tables are collapsed
    // onto the base table, so the revision view references the base table for
    // all of its fields regardless of the entity's revision/translation layout.
    $revision_table = $this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision';
    $revision_data_table = $this->supportsJsonEntityStorage ? 'entity_test_update' : 'entity_test_update_revision_data';

    // Base + revision <-> base + translation + revision.
    $this->updateEntityTypeToRevisionable(TRUE);

    [$view, $display] = $this->getUpdatedViewAndDisplay(TRUE);

    $this->assertEquals($revision_table, $view->get('base_table'));
    $this->assertEquals($revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($revision_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay(TRUE);

    $this->assertEquals($revision_table, $view->get('base_table'));
    $this->assertEquals($revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($revision_data_table, $display['display_options']['fields']['name']['table']);

    $this->updateEntityTypeToNotTranslatable(TRUE);
    [$view, $display] = $this->getUpdatedViewAndDisplay(TRUE);

    $this->assertEquals($revision_table, $view->get('base_table'));
    $this->assertEquals($revision_table, $display['display_options']['fields']['id']['table']);
    $this->assertEquals($revision_table, $display['display_options']['fields']['name']['table']);

    // Check that only the impacted views have been updated. Under JSON storage
    // the data/revision tables collapse onto the base table, so only the data
    // view (which references the collapsed data table) is rewritten.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [
      'test_view_entity_test_data',
    ] : [
      'test_view_entity_test',
      'test_view_entity_test_data',
      'test_view_entity_test_revision',
    ]);
  }

  /**
   * Tests the case when a view could not be updated automatically.
   */
  public function testViewSaveException(): void {
    $this->renameBaseTable();
    \Drupal::state()->set('entity_test_update.throw_view_exception', 'test_view_entity_test');
    $this->applyEntityUpdates('entity_test_update');

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load('test_view_entity_test');

    // Check that the table names were not updated automatically for the
    // 'test_view_entity_test' view.
    $this->assertEquals('entity_test_update', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update', $display['display_options']['fields']['name']['table']);

    // Check that the other two views impacted by the entity update were updated
    // automatically.
    $view = $entity_storage->load('test_view_entity_test_data');
    $this->assertEquals('entity_test_update_new', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update_new', $display['display_options']['fields']['id']['table']);
    // The 'name' handler references the data table, which is only collapsed onto
    // the base table once the entity type becomes translatable. This entity type
    // never does, so the handler keeps referencing the (unrenamed) data table
    // under every driver.
    $this->assertEquals('entity_test_update_data', $display['display_options']['fields']['name']['table']);

    $view = $entity_storage->load('test_view_entity_test_additional_base_field');
    $this->assertEquals('entity_test_update_new', $view->get('base_table'));
    $display = $view->getDisplay('default');
    $this->assertEquals('entity_test_update_new', $display['display_options']['fields']['id']['table']);
    $this->assertEquals('entity_test_update_new', $display['display_options']['fields']['new_base_field']['table']);

    // Under JSON storage the revision view is collapsed onto the same base
    // table, so it is rewritten by the base table rename as well.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [
      'test_view_entity_test_data',
      'test_view_entity_test_additional_base_field',
      'test_view_entity_test_revision',
    ] : [
      'test_view_entity_test_data',
      'test_view_entity_test_additional_base_field',
    ]);
  }

  /**
   * Tests that broken views are handled gracefully.
   */
  public function testBrokenView(): void {
    $view_id = 'test_view_entity_test';
    $this->state->set('views_test_config.broken_view', $view_id);
    $this->updateEntityTypeToTranslatable(TRUE);

    /** @var \Drupal\views\Entity\View $view */
    $entity_storage = $this->entityTypeManager->getStorage('view');
    $view = $entity_storage->load($view_id);

    // The broken handler should have been removed.
    $display = $view->getDisplay('default');
    $this->assertFalse(isset($display['display_options']['fields']['id_broken']));

    // Under JSON storage adding a data table collapses onto the base table and
    // moves no handler, so the subscriber rewrites no view.
    $this->assertUpdatedViews($this->supportsJsonEntityStorage ? [] : [$view_id]);
  }

  /**
   * Gets a view and its display.
   *
   * @param bool $revision
   *   (optional) TRUE if we want to get a revision view.
   *
   * @return array
   *   An array with the view as first item, and the display as second.
   */
  protected function getUpdatedViewAndDisplay($revision = FALSE): array {
    $entity_storage = $this->entityTypeManager->getStorage('view');
    /** @var \Drupal\views\Entity\View $view */
    $view = $entity_storage->load($revision ? 'test_view_entity_test_revision' : 'test_view_entity_test');
    $display = $view->getDisplay('default');

    return [$view, $display];
  }

  /**
   * Checks that the passed-in view IDs were the only ones updated.
   *
   * @param string[] $updated_view_ids
   *   An array of view IDs.
   *
   * @internal
   */
  protected function assertUpdatedViews(array $updated_view_ids): void {
    $all_view_ids = array_keys($this->entityTypeManager->getStorage('view')->loadMultiple());

    $view_save_count = \Drupal::state()->get('views_test_data.view_save_count', []);
    foreach ($all_view_ids as $view_id) {
      if (in_array($view_id, $updated_view_ids, TRUE)) {
        $this->assertTrue(isset($view_save_count[$view_id]), "The $view_id view has been updated.");
      }
      else {
        $this->assertFalse(isset($view_save_count[$view_id]), "The $view_id view has not been updated.");
      }
    }

    // Check that all test cases are updating only a subset of all the available
    // views.
    $this->assertGreaterThan(count($updated_view_ids), count($all_view_ids));
  }

}
