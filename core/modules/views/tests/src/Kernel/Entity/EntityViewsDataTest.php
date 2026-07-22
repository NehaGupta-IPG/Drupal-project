<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Entity;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\EntityViewsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity views data.
 */
#[CoversClass(EntityViewsData::class)]
#[Group('views')]
#[RunTestsInSeparateProcesses]
class EntityViewsDataTest extends KernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The base entity type definition, which some tests modify.
   *
   * Uses a custom class which allows changing entity keys.
   *
   * @var \Drupal\Tests\views\Kernel\Entity\TestEntityType
   */
  protected $baseEntityType;

  /**
   * The common base fields for test entity types.
   *
   * @var \Drupal\Core\Field\BaseFieldDefinition[]
   */
  protected $commonBaseFields;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Under the pgsql (JSON) driver, content entity field data is embedded in
    // jsonb columns and views data is produced by the module's base-table-centric
    // \Drupal\pgsql\EntityViewsData rather than core's handler. The module is
    // not installed in this isolated handler test (only its database driver is
    // active), so its root namespace is not on the autoloader; register it so the
    // handler class resolves, then use it as the views_data handler.
    $views_data_handler = EntityViewsData::class;
    $storage_handler = NULL;
    if (Database::getConnection()->driver() === 'pgsql') {
      $this->classLoader->addPsr4('Drupal\\pgsql\\', $this->root . '/modules/custom/pgsql/src');
      $views_data_handler = 'Drupal\pgsql\EntityViewsData';
      $storage_handler = 'Drupal\pgsql\EntityStorage\JsonSqlContentEntityStorage';
    }

    // A common entity type definition. Tests may change this prior to passing
    // it to setUpEntityType().
    $this->baseEntityType = new TestEntityType([
      // A normal entity type would have its class picked up during discovery,
      // but as we're mocking this without an annotation we have to specify it.
      'class' => ViewsTestEntity::class,
      'base_table' => 'entity_test',
      'id' => 'entity_test',
      'label' => 'Entity test',
      'entity_keys' => [
        'uuid' => 'uuid',
        'id' => 'id',
        'langcode' => 'langcode',
        'bundle' => 'type',
        'revision' => 'revision_id',
      ],
      'handlers' => array_filter([
        'views_data' => $views_data_handler,
        'storage' => $storage_handler,
      ]),
      'provider' => 'entity_test',
      'list_cache_contexts' => ['entity_test_list_cache_context'],
    ]);

    // Base fields for the test entity types.
    $this->commonBaseFields['name'] = BaseFieldDefinition::create('string')
      ->setLabel('Name')
      ->setDescription('The name of the test entity.')
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 32);

    $this->commonBaseFields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Authored on')
      ->setDescription('Time the entity was created')
      ->setTranslatable(TRUE);

    $this->commonBaseFields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('User ID')
      ->setDescription('The ID of the associated user.')
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      // Default EntityTest entities to have the root user as the owner, to
      // simplify testing.
      ->setDefaultValue([0 => ['target_id' => 1]])
      ->setTranslatable(TRUE);

    // Add a description field. This example comes from the taxonomy Term
    // entity.
    $this->commonBaseFields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Description')
      ->setDescription('A description of the term.')
      ->setTranslatable(TRUE);

    // Add a URL field; this example is from the Comment entity.
    $this->commonBaseFields['homepage'] = BaseFieldDefinition::create('uri')
      ->setLabel('Homepage')
      ->setDescription("The comment author's home page address.")
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255);

    // A base field with cardinality > 1.
    $this->commonBaseFields['string'] = BaseFieldDefinition::create('string')
      ->setLabel('Strong')
      ->setTranslatable(TRUE)
      ->setCardinality(2);

    // Set up the basic 'entity_test' entity type. This is used by several
    // tests; others customize it and the base fields.
    $this->setUpEntityType($this->baseEntityType, $this->commonBaseFields);
  }

  /**
   * Asserts the base-table-centric shape of pgsql (JSON) storage views data.
   *
   * Under the pgsql driver a content entity's field data is embedded in jsonb
   * columns on the entity base table (and the "<entity>_current_revision" /
   * "<entity>_all_revisions" / "<entity>_translations" embedded document
   * tables). The module's \Drupal\pgsql\EntityViewsData therefore collapses all
   * field data onto the entity base table: the logical "<entity>_field_data",
   * "<entity>_revision" and dedicated "<entity>__<field>" tables a relational
   * views data set would declare do not exist, and every field records its
   * physical jsonb location in a "real field" key.
   *
   * @param array $data
   *   The views data returned by the handler.
   * @param string $base_table
   *   The entity base table; the only top-level table expected in $data.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $current_revision_table
   *   The embedded current-revision document table recorded on the base table.
   * @param string[] $absent_tables
   *   Logical tables that must NOT be present under JSON storage.
   */
  protected function assertJsonStorageBaseTable(array $data, string $base_table, string $entity_type_id, string $current_revision_table, array $absent_tables = []): void {
    // The entity base table is the only top-level table.
    $this->assertSame([$base_table], array_keys($data));
    foreach ($absent_tables as $absent) {
      $this->assertArrayNotHasKey($absent, $data);
    }
    $this->assertEquals($entity_type_id, $data[$base_table]['table']['entity type']);
    $this->assertFalse($data[$base_table]['table']['entity revision']);
    $this->assertEquals('id', $data[$base_table]['table']['base']['field']);
    $this->assertEquals('Entity test', $data[$base_table]['table']['base']['title']);
    // The embedded revision document tables are recorded on the base table so
    // the pgsql Views query layer can select the right jsonb document.
    $this->assertEquals($current_revision_table, $data[$base_table]['table']['current revision table']);
    // The id key is a physical base-table column, so it is never rewritten to a
    // jsonb document location.
    $this->assertArrayHasKey('id', $data[$base_table]);
    $this->assertArrayNotHasKey('real field', $data[$base_table]['id']);
  }

  /**
   * Mocks an entity type and its base fields.
   *
   * This works by:
   * - inserting the entity type definition into the entity type manager's cache
   * - setting the base fields on the ViewsTestEntity class as a static property
   *   for its baseFieldsDefinitions() method to use.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $definition
   *   An entity type definition to add to the entity type manager.
   * @param \Drupal\Core\Field\BaseFieldDefinition[] $base_fields
   *   An array of base field definitions.
   */
  protected function setUpEntityType(EntityTypeInterface $definition, array $base_fields = []): void {
    // Replace the cache backend in the entity type manager so it returns
    // our test entity type in addition to the existing ones.
    $definitions = $this->entityTypeManager->getDefinitions();
    $definitions[$definition->id()] = $definition;

    $cache_backend = $this->prophesize(CacheBackendInterface::class);
    $cache_data = new \stdClass();
    $cache_data->data = $definitions;
    $cache_backend->get('entity_type')->willReturn($cache_data);
    $this->entityTypeManager->setCacheBackend($cache_backend->reveal(), 'entity_type', ['entity_types']);
    $this->entityTypeManager->clearCachedDefinitions();

    if ($base_fields) {
      ViewsTestEntity::setMockedBaseFieldDefinitions($definition->id(), $base_fields);
    }
  }

  /**
   * Tests base tables.
   */
  public function testBaseTables(): void {
    $data = $this->entityTypeManager->getHandler('entity_test', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage all data is collapsed onto the entity base table.
      $this->assertJsonStorageBaseTable($data, 'entity_test', 'entity_test', 'entity_test_current_revision', [
        'entity_test_field_data',
        'entity_test_revision',
      ]);
      $this->assertEquals('Entity test', $data['entity_test']['table']['group']);
      $this->assertEquals('entity_test', $data['entity_test']['table']['provider']);
      $this->assertEquals(['entity_test_list_cache_context'], $data['entity_test']['table']['base']['cache_contexts']);
      $this->assertArrayNotHasKey('defaults', $data['entity_test']['table']);
      $this->assertEquals('entity_test_all_revisions', $data['entity_test']['table']['all revisions table']);
      return;
    }

    $this->assertEquals('entity_test', $data['entity_test']['table']['entity type']);
    $this->assertEquals(FALSE, $data['entity_test']['table']['entity revision']);
    $this->assertEquals('Entity test', $data['entity_test']['table']['group']);
    $this->assertEquals('entity_test', $data['entity_test']['table']['provider']);

    $this->assertEquals('id', $data['entity_test']['table']['base']['field']);
    $this->assertEquals(['entity_test_list_cache_context'], $data['entity_test']['table']['base']['cache_contexts']);
    $this->assertEquals('Entity test', $data['entity_test']['table']['base']['title']);

    // @todo Change these to assertArrayNotHasKey().
    $this->assertFalse(isset($data['entity_test']['table']['defaults']));

    $this->assertFalse(isset($data['entity_test_mul_property_data']));
    $this->assertFalse(isset($data['revision_table']));
    $this->assertFalse(isset($data['revision_data_table']));
  }

  /**
   * Tests data_table support.
   */
  public function testDataTable(): void {
    $entity_type = $this->baseEntityType
      ->set('data_table', 'entity_test_mul_property_data')
      ->set('id', 'entity_test_mul')
      ->set('translatable', TRUE)
      ->setKey('label', 'label');

    $this->setUpEntityType($entity_type);

    // Tests the join definition between the base and the data table.
    $data = $this->entityTypeManager->getHandler('entity_test_mul', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage there is no separate data table; the base info and
      // all field data live on the entity base table, with no base->data join.
      $this->assertJsonStorageBaseTable($data, 'entity_test', 'entity_test_mul', 'entity_test_current_revision', [
        'entity_test_mul_property_data',
      ]);
      // The label default is recorded on the base table (no 'table' redirect to
      // a data table).
      $this->assertEquals(['field' => 'label'], $data['entity_test']['table']['base']['defaults']);
      $this->assertArrayNotHasKey('join', $data['entity_test']['table']);
      $this->assertArrayNotHasKey('revision_table', $data);
      $this->assertArrayNotHasKey('revision_data_table', $data);
      return;
    }

    // @todo Change the base table in the entity type definition to match the
    //   changed entity ID.
    $base_views_data = $data['entity_test'];

    // Ensure that the base table is set to the data table.
    $this->assertEquals('id', $data['entity_test_mul_property_data']['table']['base']['field']);
    $this->assertEquals('Entity test', $data['entity_test_mul_property_data']['table']['base']['title']);
    $this->assertFalse(isset($data['entity_test']['table']['base']));

    $this->assertEquals('entity_test_mul', $data['entity_test_mul_property_data']['table']['entity type']);
    $this->assertEquals(FALSE, $data['entity_test_mul_property_data']['table']['entity revision']);
    $this->assertEquals('Entity test', $data['entity_test_mul_property_data']['table']['group']);
    $this->assertEquals('entity_test', $data['entity_test']['table']['provider']);
    $this->assertEquals(['field' => 'label', 'table' => 'entity_test_mul_property_data'], $data['entity_test_mul_property_data']['table']['base']['defaults']);

    // Ensure the join information is set up properly.
    $this->assertCount(1, $base_views_data['table']['join']);
    $this->assertEquals(['entity_test_mul_property_data' => ['left_field' => 'id', 'field' => 'id', 'type' => 'INNER']], $base_views_data['table']['join']);
    $this->assertFalse(isset($data['revision_table']));
    $this->assertFalse(isset($data['revision_data_table']));
  }

  /**
   * Tests revision table without data table support.
   */
  public function testRevisionTableWithoutDataTable(): void {
    $entity_type = $this->baseEntityType
      ->set('revision_table', 'entity_test_mulrev_revision')
      ->set('revision_data_table', NULL)
      ->set('id', 'entity_test_mulrev')
      ->setKey('revision', 'revision_id');

    $this->setUpEntityType($entity_type);

    $data = $this->entityTypeManager->getHandler('entity_test_mulrev', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage revisions are embedded documents, not a physical
      // revision table; everything lives on the entity base table.
      $this->assertJsonStorageBaseTable($data, 'entity_test', 'entity_test_mulrev', 'entity_test_current_revision', [
        'entity_test_mulrev_revision',
      ]);
      $this->assertEquals('revision_id', $data['entity_test']['table']['entity revision field']);
      $this->assertEquals('entity_test_all_revisions', $data['entity_test']['table']['all revisions table']);
      $this->assertArrayNotHasKey('data_table', $data);
      return;
    }

    $this->assertEquals('Entity test revisions', $data['entity_test_mulrev_revision']['table']['base']['title']);
    $this->assertEquals('revision_id', $data['entity_test_mulrev_revision']['table']['base']['field']);

    $this->assertEquals(FALSE, $data['entity_test']['table']['entity revision']);
    $this->assertEquals('entity_test_mulrev', $data['entity_test_mulrev_revision']['table']['entity type']);
    $this->assertEquals(TRUE, $data['entity_test_mulrev_revision']['table']['entity revision']);
    $this->assertEquals('entity_test_mulrev', $data['entity_test_mulrev_revision']['table']['entity type']);
    $this->assertEquals(TRUE, $data['entity_test_mulrev_revision']['table']['entity revision']);

    $this->assertEquals('Entity test revision', $data['entity_test_mulrev_revision']['table']['group']);
    $this->assertEquals('entity_test', $data['entity_test']['table']['provider']);

    // Ensure the join information is set up properly.
    // Tests the join definition between the base and the revision table.
    $revision_data = $data['entity_test_mulrev_revision'];
    $this->assertCount(1, $revision_data['table']['join']);
    $this->assertEquals(['entity_test' => ['left_field' => 'revision_id', 'field' => 'revision_id', 'type' => 'INNER']], $revision_data['table']['join']);
    $this->assertFalse(isset($data['data_table']));

    $this->assertEquals('entity_test', $revision_data['id']['relationship']['base']);
    $this->assertEquals('id', $revision_data['id']['relationship']['base field']);
    $this->assertEquals('entity_test', $revision_data['revision_id']['relationship']['base']);
    $this->assertEquals('revision_id', $revision_data['revision_id']['relationship']['base field']);
  }

  /**
   * Tests revision table with data table support.
   */
  public function testRevisionTableWithRevisionDataTableAndDataTable(): void {
    $entity_type = $this->baseEntityType
      ->set('data_table', 'entity_test_mul_property_data')
      ->set('revision_table', 'entity_test_mulrev_revision')
      ->set('revision_data_table', 'entity_test_mulrev_property_revision')
      ->set('id', 'entity_test_mulrev')
      ->set('translatable', TRUE)
      ->setKey('revision', 'revision_id');
    $this->setUpEntityType($entity_type);

    $data = $this->entityTypeManager->getHandler('entity_test_mulrev', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage the revision and revision-data tables are embedded
      // documents; everything is collapsed onto the entity base table.
      $this->assertJsonStorageBaseTable($data, 'entity_test', 'entity_test_mulrev', 'entity_test_current_revision', [
        'entity_test_mulrev_revision',
        'entity_test_mulrev_property_revision',
        'entity_test_mul_property_data',
      ]);
      $this->assertEquals('entity_test_all_revisions', $data['entity_test']['table']['all revisions table']);
      $this->assertArrayNotHasKey('data_table', $data);
      return;
    }

    $this->assertEquals('Entity test revisions', $data['entity_test_mulrev_property_revision']['table']['base']['title']);
    $this->assertEquals('revision_id', $data['entity_test_mulrev_property_revision']['table']['base']['field']);
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['table']['base']));

    $this->assertEquals('entity_test_mulrev', $data['entity_test_mulrev_property_revision']['table']['entity type']);
    $this->assertEquals('Entity test revision', $data['entity_test_mulrev_revision']['table']['group']);
    $this->assertTrue($data['entity_test_mulrev_revision']['table']['entity revision']);
    $this->assertEquals('entity_test', $data['entity_test']['table']['provider']);

    // Ensure the join information is set up properly.
    // Tests the join definition between the base and the revision table.
    $revision_field_data = $data['entity_test_mulrev_property_revision'];
    $this->assertCount(1, $revision_field_data['table']['join']);
    $this->assertEquals([
      'entity_test_mul_property_data' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
        'type' => 'INNER',
      ],
    ], $revision_field_data['table']['join']);

    $revision_base_data = $data['entity_test_mulrev_revision'];
    $this->assertCount(2, $revision_base_data['table']['join']);
    $this->assertEquals([
      'entity_test_mulrev_property_revision' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
        'type' => 'INNER',
      ],
      'entity_test_mul_property_data' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
      ],
    ], $revision_base_data['table']['join']);

    $this->assertFalse(isset($data['data_table']));

    $this->assertEquals('entity_test_mul_property_data', $revision_field_data['id']['relationship']['base']);
    $this->assertEquals('id', $revision_field_data['id']['relationship']['base field']);
    $this->assertEquals('entity_test_mul_property_data', $revision_field_data['revision_id']['relationship']['base']);
    $this->assertEquals('revision_id', $revision_field_data['revision_id']['relationship']['base field']);
  }

  /**
   * Tests revision table with data table support.
   */
  public function testRevisionTableWithRevisionDataTable(): void {
    $entity_type = $this->baseEntityType
      ->set('revision_table', 'entity_test_mulrev_revision')
      ->set('revision_data_table', 'entity_test_mulrev_property_revision')
      ->set('id', 'entity_test_mulrev')
      ->set('translatable', TRUE)
      ->setKey('revision', 'revision_id');
    $this->setUpEntityType($entity_type);

    $data = $this->entityTypeManager->getHandler('entity_test_mulrev', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage the revision and revision-data tables are embedded
      // documents; everything is collapsed onto the entity base table.
      $this->assertJsonStorageBaseTable($data, 'entity_test', 'entity_test_mulrev', 'entity_test_current_revision', [
        'entity_test_mulrev_revision',
        'entity_test_mulrev_property_revision',
        'entity_test_mulrev_field_data',
      ]);
      $this->assertEquals('entity_test_all_revisions', $data['entity_test']['table']['all revisions table']);
      $this->assertArrayNotHasKey('data_table', $data);
      return;
    }

    $this->assertEquals('Entity test revisions', $data['entity_test_mulrev_property_revision']['table']['base']['title']);
    $this->assertEquals('revision_id', $data['entity_test_mulrev_property_revision']['table']['base']['field']);
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['table']['base']));

    $this->assertEquals('entity_test_mulrev', $data['entity_test_mulrev_property_revision']['table']['entity type']);
    $this->assertEquals('Entity test revision', $data['entity_test_mulrev_revision']['table']['group']);
    $this->assertTrue($data['entity_test_mulrev_revision']['table']['entity revision']);
    $this->assertEquals('entity_test', $data['entity_test']['table']['provider']);

    // Ensure the join information is set up properly.
    // Tests the join definition between the base and the revision table.
    $revision_field_data = $data['entity_test_mulrev_property_revision'];
    $this->assertCount(1, $revision_field_data['table']['join']);
    $this->assertEquals([
      'entity_test_mulrev_field_data' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
        'type' => 'INNER',
      ],
    ], $revision_field_data['table']['join']);

    $revision_base_data = $data['entity_test_mulrev_revision'];
    $this->assertCount(2, $revision_base_data['table']['join']);
    $this->assertEquals([
      'entity_test_mulrev_property_revision' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
        'type' => 'INNER',
      ],
      'entity_test_mulrev_field_data' => [
        'left_field' => 'revision_id',
        'field' => 'revision_id',
      ],
    ], $revision_base_data['table']['join']);
    $this->assertFalse(isset($data['data_table']));

    $this->assertEquals('entity_test_mulrev_field_data', $revision_field_data['id']['relationship']['base']);
    $this->assertEquals('id', $revision_field_data['id']['relationship']['base field']);
    $this->assertEquals('entity_test_mulrev_field_data', $revision_field_data['revision_id']['relationship']['base']);
    $this->assertEquals('revision_id', $revision_field_data['revision_id']['relationship']['base field']);
  }

  /**
   * Tests fields on the base table.
   */
  public function testBaseTableFields(): void {
    $data = $this->entityTypeManager->getHandler('entity_test', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage every field is collapsed onto the entity base table
      // and carries a 'real field' pointing at its physical jsonb document
      // column; the dedicated "entity_test__string" table does not exist.
      $crt = 'entity_test_current_revision';
      $this->assertSame(['entity_test'], array_keys($data));
      $this->assertArrayNotHasKey('entity_test__string', $data);
      // The id key stays a physical base-table column.
      $this->assertNumericField($data['entity_test']['id']);
      $this->assertArrayNotHasKey('real field', $data['entity_test']['id']);
      // Single-value base fields are re-homed onto the base table.
      $this->assertStringField($data['entity_test']['name']);
      $this->assertEquals($crt . '.name', $data['entity_test']['name']['real field']);
      $this->assertUriField($data['entity_test']['homepage']);
      $this->assertEquals($crt . '.homepage', $data['entity_test']['homepage']['real field']);
      $this->assertLongTextField($data['entity_test'], 'description');
      $this->assertEquals($crt . '.description__value', $data['entity_test']['description__value']['real field']);
      $this->assertEquals($crt . '.description__format', $data['entity_test']['description__format']['real field']);
      $this->assertEntityReferenceField($data['entity_test']['user_id']);
      $this->assertEquals($crt . '.user_id', $data['entity_test']['user_id']['real field']);
      // The reference relationship targets the user entity base table.
      $this->assertEquals('users', $data['entity_test']['user_id']['relationship']['base']);
      $this->assertEquals('uid', $data['entity_test']['user_id']['relationship']['base field']);
      // The multi-value base field's value column is embedded as a dedicated
      // document nested in the current-revision document, still on the base
      // table.
      $this->assertStringField($data['entity_test']['string_value']);
      $this->assertEquals('string', $data['entity_test']['string_value']['entity field']);
      $this->assertEquals($crt . '.' . $crt . '__string.string_value', $data['entity_test']['string_value']['real field']);
      return;
    }

    $this->assertNumericField($data['entity_test']['id']);
    $this->assertViewsDataField($data['entity_test']['id'], 'id');
    $this->assertUuidField($data['entity_test']['uuid']);
    $this->assertViewsDataField($data['entity_test']['uuid'], 'uuid');
    $this->assertStringField($data['entity_test']['type']);
    $this->assertEquals('type', $data['entity_test']['type']['entity field']);

    $this->assertLanguageField($data['entity_test']['langcode']);
    $this->assertViewsDataField($data['entity_test']['langcode'], 'langcode');
    $this->assertEquals('Original language', $data['entity_test']['langcode']['title']);

    $this->assertStringField($data['entity_test']['name']);
    $this->assertViewsDataField($data['entity_test']['name'], 'name');

    $this->assertLongTextField($data['entity_test'], 'description');
    $this->assertViewsDataField($data['entity_test']['description__value'], 'description');
    $this->assertViewsDataField($data['entity_test']['description__format'], 'description');

    $this->assertUriField($data['entity_test']['homepage']);
    $this->assertViewsDataField($data['entity_test']['homepage'], 'homepage');

    $this->assertEntityReferenceField($data['entity_test']['user_id']);
    $this->assertViewsDataField($data['entity_test']['user_id'], 'user_id');

    $relationship = $data['entity_test']['user_id']['relationship'];
    $this->assertEquals('users_field_data', $relationship['base']);
    $this->assertEquals('uid', $relationship['base field']);

    // The string field name should be used as the 'entity field' but the actual
    // field should reflect what the column mapping is using for multi-value
    // base fields NOT just the field name. The actual column name returned from
    // mappings in the test mocks is 'value'.
    $this->assertStringField($data['entity_test__string']['string_value']);
    $this->assertViewsDataField($data['entity_test__string']['string_value'], 'string');
    $this->assertEquals([
      'left_field' => 'id',
      'field' => 'entity_id',
      'extra' => [
        [
          'field' => 'deleted',
          'value' => 0,
          'numeric' => TRUE,
        ],
      ],
    ], $data['entity_test__string']['table']['join']['entity_test']);

    // Check the "allow empty" option for non-required fields.
    $this->assertArrayHasKey('allow empty', $data['entity_test']['name']['filter']);
    $this->assertTrue($data['entity_test']['name']['filter']['allow empty']);
    // Reconfigure the field to be required.
    $this->commonBaseFields['name']->setRequired(TRUE);
    $data = $this->entityTypeManager->getHandler('entity_test', 'views_data')->getViewsData();
    $this->assertArrayNotHasKey('allow empty', $data['entity_test']['name']['filter']);
  }

  /**
   * Tests fields on the data table.
   */
  public function testDataTableFields(): void {
    $entity_test_type = new ConfigEntityType([
      'class' => ConfigEntityBase::class,
      'id' => 'entity_test_bundle',
      'entity_keys' => [
        'id' => 'type',
        'label' => 'name',
      ],
    ]);
    $this->setUpEntityType($entity_test_type);

    $entity_type = $this->baseEntityType
      ->set('data_table', 'entity_test_mul_property_data')
      ->set('base_table', 'entity_test_mul')
      ->set('translatable', TRUE)
      ->set('id', 'entity_test_mul')
      ->set('bundle_entity_type', 'entity_test_bundle')
      ->setKey('bundle', 'type');

    $base_field_definitions = $this->commonBaseFields;
    $base_field_definitions['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('entity test type')
      ->setSetting('target_type', 'entity_test_bundle');

    $this->setUpEntityType($entity_type, $base_field_definitions);

    $data = $this->entityTypeManager->getHandler('entity_test_mul', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage the translatable entity's field data is collapsed
      // onto the entity base table (no "entity_test_mul_property_data" table and
      // no dedicated "entity_test_mul__string" table); each field carries a
      // 'real field' pointing at its jsonb document column.
      $crt = 'entity_test_mul_current_revision';
      $this->assertSame(['entity_test_mul'], array_keys($data));
      $this->assertArrayNotHasKey('entity_test_mul_property_data', $data);
      $this->assertArrayNotHasKey('entity_test_mul__string', $data);
      // The id/bundle keys stay physical base-table columns.
      $this->assertNumericField($data['entity_test_mul']['id']);
      $this->assertArrayNotHasKey('real field', $data['entity_test_mul']['id']);
      $this->assertBundleField($data['entity_test_mul']['type']);
      $this->assertUuidField($data['entity_test_mul']['uuid']);
      // Translated field data is re-homed onto the base table.
      $this->assertLanguageField($data['entity_test_mul']['langcode']);
      $this->assertEquals($crt . '.langcode', $data['entity_test_mul']['langcode']['real field']);
      $this->assertStringField($data['entity_test_mul']['name']);
      $this->assertEquals($crt . '.name', $data['entity_test_mul']['name']['real field']);
      $this->assertLongTextField($data['entity_test_mul'], 'description');
      $this->assertEquals($crt . '.description__value', $data['entity_test_mul']['description__value']['real field']);
      $this->assertUriField($data['entity_test_mul']['homepage']);
      $this->assertEquals($crt . '.homepage', $data['entity_test_mul']['homepage']['real field']);
      $this->assertEntityReferenceField($data['entity_test_mul']['user_id']);
      $this->assertEquals($crt . '.user_id', $data['entity_test_mul']['user_id']['real field']);
      $this->assertEquals('users', $data['entity_test_mul']['user_id']['relationship']['base']);
      $this->assertEquals('uid', $data['entity_test_mul']['user_id']['relationship']['base field']);
      $this->assertStringField($data['entity_test_mul']['string_value']);
      $this->assertEquals($crt . '.' . $crt . '__string.string_value', $data['entity_test_mul']['string_value']['real field']);
      return;
    }

    // Check the base fields.
    $this->assertFalse(isset($data['entity_test_mul']['id']));
    $this->assertFalse(isset($data['entity_test_mul']['type']));
    $this->assertUuidField($data['entity_test_mul']['uuid']);
    $this->assertViewsDataField($data['entity_test_mul']['uuid'], 'uuid');

    $this->assertFalse(isset($data['entity_test_mul']['type']['relationship']));

    // Also ensure that field_data only fields don't appear on the base table.
    $this->assertFalse(isset($data['entity_test_mul']['name']));
    $this->assertFalse(isset($data['entity_test_mul']['description']));
    $this->assertFalse(isset($data['entity_test_mul']['description__value']));
    $this->assertFalse(isset($data['entity_test_mul']['description__format']));
    $this->assertFalse(isset($data['entity_test_mul']['user_id']));
    $this->assertFalse(isset($data['entity_test_mul']['homepage']));

    // Check the data fields.
    $this->assertNumericField($data['entity_test_mul_property_data']['id']);
    $this->assertViewsDataField($data['entity_test_mul_property_data']['id'], 'id');

    $this->assertBundleField($data['entity_test_mul_property_data']['type']);
    $this->assertViewsDataField($data['entity_test_mul_property_data']['type'], 'type');

    $this->assertLanguageField($data['entity_test_mul_property_data']['langcode']);
    $this->assertViewsDataField($data['entity_test_mul_property_data']['langcode'], 'langcode');
    $this->assertEquals('Translation language', $data['entity_test_mul_property_data']['langcode']['title']);

    $this->assertStringField($data['entity_test_mul_property_data']['name']);
    $this->assertViewsDataField($data['entity_test_mul_property_data']['name'], 'name');

    $this->assertLongTextField($data['entity_test_mul_property_data'], 'description');
    $this->assertViewsDataField($data['entity_test_mul_property_data']['description__value'], 'description');
    $this->assertViewsDataField($data['entity_test_mul_property_data']['description__format'], 'description');

    $this->assertUriField($data['entity_test_mul_property_data']['homepage']);
    $this->assertViewsDataField($data['entity_test_mul_property_data']['homepage'], 'homepage');

    $this->assertEntityReferenceField($data['entity_test_mul_property_data']['user_id']);
    $this->assertViewsDataField($data['entity_test_mul_property_data']['user_id'], 'user_id');
    $relationship = $data['entity_test_mul_property_data']['user_id']['relationship'];
    $this->assertEquals('users_field_data', $relationship['base']);
    $this->assertEquals('uid', $relationship['base field']);

    $this->assertStringField($data['entity_test_mul__string']['string_value']);
    $this->assertViewsDataField($data['entity_test_mul__string']['string_value'], 'string');
    $this->assertEquals([
      'left_field' => 'id',
      'field' => 'entity_id',
      'extra' => [
        [
          'field' => 'deleted',
          'value' => 0,
          'numeric' => TRUE,
        ],
      ],
    ], $data['entity_test_mul__string']['table']['join']['entity_test_mul_property_data']);
  }

  /**
   * Tests fields on the revision table.
   */
  public function testRevisionTableFields(): void {
    $entity_type = $this->baseEntityType
      ->set('id', 'entity_test_mulrev')
      ->set('base_table', 'entity_test_mulrev')
      ->set('revision_table', 'entity_test_mulrev_revision')
      ->set('data_table', 'entity_test_mulrev_property_data')
      ->set('revision_data_table', 'entity_test_mulrev_property_revision')
      ->set('translatable', TRUE);

    $base_field_definitions = $this->commonBaseFields;

    $base_field_definitions['name']->setRevisionable(TRUE);
    $base_field_definitions['description']->setRevisionable(TRUE);
    $base_field_definitions['homepage']->setRevisionable(TRUE);
    $base_field_definitions['user_id']->setRevisionable(TRUE);

    $base_field_definitions['non_rev_field'] = BaseFieldDefinition::create('string')
      ->setLabel('Non Revisionable Field')
      ->setDescription('A non-revisionable tes field.')
      ->setRevisionable(FALSE)
      ->setTranslatable(TRUE)
      ->setCardinality(1)
      ->setReadOnly(TRUE);

    $base_field_definitions['non_mul_field'] = BaseFieldDefinition::create('string')
      ->setLabel('Non translatable')
      ->setDescription('A non-translatable string field')
      ->setRevisionable(TRUE);

    $this->setUpEntityType($entity_type, $base_field_definitions);

    $data = $this->entityTypeManager->getHandler('entity_test_mulrev', 'views_data')->getViewsData();

    if (Database::getConnection()->driver() === 'pgsql') {
      // Under JSON storage the revisionable + translatable entity's field data
      // is collapsed onto the entity base table: no
      // "entity_test_mulrev_property_data" / "_property_revision" tables and no
      // dedicated "entity_test_mulrev__string" tables. Each field carries a
      // 'real field' pointing at its jsonb document column.
      $crt = 'entity_test_mulrev_current_revision';
      $this->assertSame(['entity_test_mulrev'], array_keys($data));
      $this->assertArrayNotHasKey('entity_test_mulrev_property_data', $data);
      $this->assertArrayNotHasKey('entity_test_mulrev_property_revision', $data);
      $this->assertArrayNotHasKey('entity_test_mulrev__string', $data);
      // The id key stays a physical base-table column; revision_id is embedded.
      $this->assertNumericField($data['entity_test_mulrev']['id']);
      $this->assertArrayNotHasKey('real field', $data['entity_test_mulrev']['id']);
      $this->assertNumericField($data['entity_test_mulrev']['revision_id']);
      $this->assertEquals($crt . '.revision_id', $data['entity_test_mulrev']['revision_id']['real field']);
      $this->assertUuidField($data['entity_test_mulrev']['uuid']);
      $this->assertLanguageField($data['entity_test_mulrev']['langcode']);
      $this->assertEquals($crt . '.langcode', $data['entity_test_mulrev']['langcode']['real field']);
      $this->assertStringField($data['entity_test_mulrev']['name']);
      $this->assertEquals($crt . '.name', $data['entity_test_mulrev']['name']['real field']);
      $this->assertLongTextField($data['entity_test_mulrev'], 'description');
      $this->assertEquals($crt . '.description__value', $data['entity_test_mulrev']['description__value']['real field']);
      $this->assertUriField($data['entity_test_mulrev']['homepage']);
      $this->assertEquals($crt . '.homepage', $data['entity_test_mulrev']['homepage']['real field']);
      $this->assertEntityReferenceField($data['entity_test_mulrev']['user_id']);
      $this->assertEquals($crt . '.user_id', $data['entity_test_mulrev']['user_id']['real field']);
      $this->assertEquals('users', $data['entity_test_mulrev']['user_id']['relationship']['base']);
      $this->assertEquals('uid', $data['entity_test_mulrev']['user_id']['relationship']['base field']);
      // The non-revisionable and non-multi-value base fields are re-homed too.
      $this->assertStringField($data['entity_test_mulrev']['non_rev_field']);
      $this->assertEquals($crt . '.non_rev_field', $data['entity_test_mulrev']['non_rev_field']['real field']);
      $this->assertStringField($data['entity_test_mulrev']['non_mul_field']);
      $this->assertEquals($crt . '.non_mul_field', $data['entity_test_mulrev']['non_mul_field']['real field']);
      $this->assertStringField($data['entity_test_mulrev']['string_value']);
      $this->assertEquals($crt . '.' . $crt . '__string.string_value', $data['entity_test_mulrev']['string_value']['real field']);
      return;
    }

    // Check the base fields.
    $this->assertFalse(isset($data['entity_test_mulrev']['id']));
    $this->assertFalse(isset($data['entity_test_mulrev']['type']));
    $this->assertFalse(isset($data['entity_test_mulrev']['revision_id']));
    $this->assertUuidField($data['entity_test_mulrev']['uuid']);
    $this->assertViewsDataField($data['entity_test_mulrev']['uuid'], 'uuid');

    // Also ensure that field_data only fields don't appear on the base table.
    $this->assertFalse(isset($data['entity_test_mulrev']['name']));
    $this->assertFalse(isset($data['entity_test_mul']['description']));
    $this->assertFalse(isset($data['entity_test_mul']['description__value']));
    $this->assertFalse(isset($data['entity_test_mul']['description__format']));
    $this->assertFalse(isset($data['entity_test_mul']['homepage']));
    // $this->assertFalse(isset($data['entity_test_mulrev']['langcode']));
    $this->assertFalse(isset($data['entity_test_mulrev']['user_id']));

    // Check the revision fields. The revision ID should only appear in the data
    // table.
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['revision_id']));

    // Also ensure that field_data only fields don't appear on the revision
    // table.
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['id']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['name']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['description']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['description__value']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['description__format']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['homepage']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['user_id']));

    // Check the data fields.
    $this->assertNumericField($data['entity_test_mulrev_property_data']['id']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['id'], 'id');
    $this->assertNumericField($data['entity_test_mulrev_property_data']['revision_id']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['revision_id'], 'revision_id');
    $this->assertLanguageField($data['entity_test_mulrev_property_data']['langcode']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['langcode'], 'langcode');
    $this->assertStringField($data['entity_test_mulrev_property_data']['name']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['name'], 'name');

    $this->assertLongTextField($data['entity_test_mulrev_property_data'], 'description');
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['description__value'], 'description');
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['description__format'], 'description');
    $this->assertUriField($data['entity_test_mulrev_property_data']['homepage']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['homepage'], 'homepage');

    $this->assertEntityReferenceField($data['entity_test_mulrev_property_data']['user_id']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_data']['user_id'], 'user_id');
    $relationship = $data['entity_test_mulrev_property_data']['user_id']['relationship'];
    $this->assertEquals('users_field_data', $relationship['base']);
    $this->assertEquals('uid', $relationship['base field']);

    // Check the property data fields.
    $this->assertNumericField($data['entity_test_mulrev_property_revision']['id']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['id'], 'id');

    $this->assertEquals('entity_test_mulrev_property_data', $data['entity_test_mulrev_property_revision']['id']['relationship']['base']);
    $this->assertEquals('id', $data['entity_test_mulrev_property_revision']['id']['relationship']['base field']);
    $this->assertEquals('entity_test_mulrev_property_data', $data['entity_test_mulrev_property_revision']['revision_id']['relationship']['base']);
    $this->assertEquals('revision_id', $data['entity_test_mulrev_property_revision']['revision_id']['relationship']['base field']);

    $this->assertLanguageField($data['entity_test_mulrev_property_revision']['langcode']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['langcode'], 'langcode');
    $this->assertEquals('Translation language', $data['entity_test_mulrev_property_revision']['langcode']['title']);

    $this->assertStringField($data['entity_test_mulrev_property_revision']['name']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['name'], 'name');

    $this->assertLongTextField($data['entity_test_mulrev_property_revision'], 'description');
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['description__value'], 'description');
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['description__format'], 'description');

    $this->assertUriField($data['entity_test_mulrev_property_revision']['homepage']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['homepage'], 'homepage');

    $this->assertEntityReferenceField($data['entity_test_mulrev_property_revision']['user_id']);
    $this->assertViewsDataField($data['entity_test_mulrev_property_revision']['user_id'], 'user_id');
    $relationship = $data['entity_test_mulrev_property_revision']['user_id']['relationship'];
    $this->assertEquals('users_field_data', $relationship['base']);
    $this->assertEquals('uid', $relationship['base field']);

    $this->assertStringField($data['entity_test_mulrev__string']['string_value']);
    $this->assertViewsDataField($data['entity_test_mulrev__string']['string_value'], 'string');
    $this->assertEquals([
      'left_field' => 'id',
      'field' => 'entity_id',
      'extra' => [
        [
          'field' => 'deleted',
          'value' => 0,
          'numeric' => TRUE,
        ],
      ],
    ], $data['entity_test_mulrev__string']['table']['join']['entity_test_mulrev_property_data']);

    $this->assertStringField($data['entity_test_mulrev_revision__string']['string_value']);
    $this->assertViewsDataField($data['entity_test_mulrev_revision__string']['string_value'], 'string');
    $this->assertEquals([
      'left_field' => 'revision_id',
      'field' => 'entity_id',
      'extra' => [
        [
          'field' => 'deleted',
          'value' => 0,
          'numeric' => TRUE,
        ],
      ],
    ], $data['entity_test_mulrev_revision__string']['table']['join']['entity_test_mulrev_property_revision']);
  }

  /**
   * Tests generic stuff per field.
   *
   * @param array $data
   *   The views data to check.
   * @param string $field_name
   *   The entity field name.
   *
   * @internal
   */
  protected function assertViewsDataField(array $data, string $field_name): void {
    $this->assertEquals($field_name, $data['entity field']);
  }

  /**
   * Tests views data for a string field.
   *
   * @param array $data
   *   The views data to check.
   *
   * @internal
   */
  protected function assertStringField(array $data): void {
    $this->assertEquals('field', $data['field']['id']);
    $this->assertEquals('string', $data['filter']['id']);
    $this->assertEquals('string', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a URI field.
   *
   * @param array $data
   *   The views data to check.
   *
   * @internal
   */
  protected function assertUriField(array $data): void {
    $this->assertEquals('field', $data['field']['id']);
    $this->assertEquals('string', $data['field']['default_formatter']);
    $this->assertEquals('string', $data['filter']['id']);
    $this->assertEquals('string', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a long text field.
   *
   * @param array $data
   *   The views data for the table this field is in.
   * @param string $field_name
   *   The name of the field being checked.
   *
   * @internal
   */
  protected function assertLongTextField(array $data, string $field_name): void {
    $value_field = $data[$field_name . '__value'];
    $this->assertEquals('field', $value_field['field']['id']);
    $this->assertEquals($field_name . '__format', $value_field['field']['format']);
    $this->assertEquals('string', $value_field['filter']['id']);
    $this->assertEquals('string', $value_field['argument']['id']);
    $this->assertEquals('standard', $value_field['sort']['id']);

    $this->assertStringField($data[$field_name . '__format']);
  }

  /**
   * Tests views data for a UUID field.
   *
   * @param array $data
   *   The views data to check.
   *
   * @internal
   */
  protected function assertUuidField(array $data): void {
    // @todo Can we provide additional support for UUIDs in views?
    $this->assertEquals('field', $data['field']['id']);
    $this->assertFalse($data['field']['click sortable']);
    $this->assertEquals('string', $data['filter']['id']);
    $this->assertEquals('string', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a numeric field.
   *
   * @param array $data
   *   The views data to check.
   *
   * @internal
   */
  protected function assertNumericField(array $data): void {
    $this->assertEquals('field', $data['field']['id']);
    $this->assertEquals('numeric', $data['filter']['id']);
    $this->assertEquals('numeric', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a language field.
   *
   * @param array $data
   *   The views data to check.
   *
   * @internal
   */
  protected function assertLanguageField(array $data): void {
    $this->assertEquals('field_language', $data['field']['id']);
    $this->assertEquals('language', $data['filter']['id']);
    $this->assertEquals('language', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for an entity reference field.
   *
   * @internal
   */
  protected function assertEntityReferenceField(array $data): void {
    $this->assertEquals('field', $data['field']['id']);
    $this->assertEquals('numeric', $data['filter']['id']);
    $this->assertEquals('entity_target_id', $data['argument']['id']);
    $this->assertEquals('user', $data['argument']['target_entity_type_id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a bundle field.
   *
   * @internal
   */
  protected function assertBundleField(array $data): void {
    $this->assertEquals('field', $data['field']['id']);
    $this->assertEquals('bundle', $data['filter']['id']);
    $this->assertEquals('string', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

}

/**
 * Entity type class which allows changing the entity keys.
 */
class TestEntityType extends ContentEntityType {

  /**
   * Sets a specific entity key.
   *
   * @param string $key
   *   The name of the entity key.
   * @param string $value
   *   The new value of the key.
   *
   * @return $this
   */
  public function setKey($key, $value): static {
    $this->entity_keys[$key] = $value;
    return $this;
  }

}

/**
 * Generic entity class for our test entity types.
 *
 * Allows mocked base field definitions.
 */
class ViewsTestEntity extends ContentEntityBase {

  /**
   * The mocked base fields for test entity types.
   *
   * An array keyed by entity type ID, whose values are arrays of base field
   * definitions.
   *
   * @var array
   */
  protected static $mockedBaseFieldDefinitions = [];

  /**
   * Sets up the mocked base field definitions.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param array $definitions
   *   The array of base field definitions to mock. These are added to the
   *   defaults ones from the parent class.
   */
  public static function setMockedBaseFieldDefinitions(string $entity_type_id, array $definitions): void {
    static::$mockedBaseFieldDefinitions[$entity_type_id] = $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    if (isset(static::$mockedBaseFieldDefinitions[$entity_type->id()])) {
      $mocked_fields = static::$mockedBaseFieldDefinitions[$entity_type->id()];
      // Mocked fields take priority over ones from the base class.
      $fields = $mocked_fields + $fields;
    }

    return $fields;
  }

}
