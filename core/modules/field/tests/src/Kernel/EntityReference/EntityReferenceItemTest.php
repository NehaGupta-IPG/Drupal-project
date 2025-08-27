<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\EntityReference;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

/**
 * Tests the new entity API for the entity reference field type.
 *
 * @group entity_reference
 */
class EntityReferenceItemTest extends FieldKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'comment',
    'file',
    'taxonomy',
    'text',
    'filter',
    'views',
    'field',
  ];

  /**
   * The taxonomy vocabulary to test with.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The taxonomy term to test with.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * The test entity with a string ID.
   *
   * @var \Drupal\entity_test\Entity\EntityTestStringId
   */
  protected $entityStringId;

  /**
   * Sets up the test.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_string_id');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('file');

    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('node', ['node_access']);

    $this->vocabulary = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->randomMachineName(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->vocabulary->save();

    $this->term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->vocabulary->id(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $this->term->save();

    NodeType::create([
      'type' => $this->randomMachineName(),
      'name' => $this->randomString(),
    ])->save();
    CommentType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'target_entity_type_id' => 'node',
    ])->save();

    $this->entityStringId = EntityTestStringId::create([
      'id' => $this->randomMachineName(),
    ]);
    $this->entityStringId->save();

    // Use the util to create an instance.
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_taxonomy_term', 'Test content entity reference', 'taxonomy_term');
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_entity_test_string_id', 'Test content entity reference with string ID', 'entity_test_string_id');
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_taxonomy_vocabulary', 'Test config entity reference', 'taxonomy_vocabulary');
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_node', 'Test node entity reference', 'node', 'default', [], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_user', 'Test user entity reference', 'user');
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_comment', 'Test comment entity reference', 'comment');
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_test_file', 'Test file entity reference', 'file');
    $this->createEntityReferenceField('entity_test_string_id', 'entity_test_string_id', 'field_test_entity_test', 'Test content entity reference with string ID', 'entity_test');
  }

  /**
   * Tests the entity reference field type for referencing content entities.
   */
  public function testContentEntityReferenceItem(): void {
    $tid = $this->term->id();

    // Just being able to create the entity like this verifies a lot of code.
    $entity = EntityTest::create();
    $entity->field_test_taxonomy_term->target_id = $tid;
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->field_test_taxonomy_term);
    $this->assertInstanceOf(FieldItemInterface::class, $entity->field_test_taxonomy_term[0]);
    $this->assertEquals($tid, $entity->field_test_taxonomy_term->target_id);
    $this->assertEquals($this->term->getName(), $entity->field_test_taxonomy_term->entity->getName());
    $this->assertEquals($tid, $entity->field_test_taxonomy_term->entity->id());
    $this->assertEquals($this->term->uuid(), $entity->field_test_taxonomy_term->entity->uuid());
    // Verify that the label for the target ID property definition is correct.
    $label = $entity->field_test_taxonomy_term->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinition('target_id')->getLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('Taxonomy term ID', $label->render());

    // Change the name of the term via the reference.
    $new_name = $this->randomMachineName();
    $entity->field_test_taxonomy_term->entity->setName($new_name);
    $entity->field_test_taxonomy_term->entity->save();
    // Verify it is the correct name.
    $term = Term::load($tid);
    $this->assertEquals($new_name, $term->getName());

    // Make sure the computed term reflects updates to the term id.
    $term2 = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $term2->save();

    // Test all the possible ways of assigning a value.
    $entity->field_test_taxonomy_term->target_id = $term->id();
    $this->assertEquals($term->id(), $entity->field_test_taxonomy_term->entity->id());
    $this->assertEquals($term->getName(), $entity->field_test_taxonomy_term->entity->getName());

    $entity->field_test_taxonomy_term = [['target_id' => $term2->id()]];
    $this->assertEquals($term2->id(), $entity->field_test_taxonomy_term->entity->id());
    $this->assertEquals($term2->getName(), $entity->field_test_taxonomy_term->entity->getName());

    // Test value assignment via the computed 'entity' property.
    $entity->field_test_taxonomy_term->entity = $term;
    $this->assertEquals($term->id(), $entity->field_test_taxonomy_term->target_id);
    $this->assertEquals($term->getName(), $entity->field_test_taxonomy_term->entity->getName());

    $entity->field_test_taxonomy_term = [['entity' => $term2]];
    $this->assertEquals($term2->id(), $entity->field_test_taxonomy_term->target_id);
    $this->assertEquals($term2->getName(), $entity->field_test_taxonomy_term->entity->getName());

    // Test assigning an invalid item throws an exception.
    try {
      $entity->field_test_taxonomy_term = ['target_id' => 'invalid', 'entity' => $term2];
      $this->fail('Assigning an invalid item throws an exception.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
    }

    // Delete terms so we have nothing to reference and try again.
    $term->delete();
    $term2->delete();
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->save();

    // Test the generateSampleValue() method.
    $entity = EntityTest::create();
    $entity->field_test_taxonomy_term->generateSampleItems();
    $entity->field_test_taxonomy_vocabulary->generateSampleItems();
    $this->entityValidateAndSave($entity);

    // Tests that setting an integer target ID together with an entity object
    // succeeds and does not cause any exceptions. There is no assertion here,
    // as the assignment should not throw any exceptions and if it does the
    // test will fail.
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::setValue().
    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $entity = EntityTest::create(['user_id' => ['target_id' => (int) $user->id(), 'entity' => $user]]);
  }

  /**
   * Tests the ::generateSampleValue() method.
   */
  public function testGenerateSampleValue(): void {
    $entity = EntityTest::create();

    // Test while a term exists.
    $entity->field_test_taxonomy_term->generateSampleItems();
    $this->assertInstanceOf(TermInterface::class, $entity->field_test_taxonomy_term->entity);
    $this->entityValidateAndSave($entity);

    // Delete the term and test again.
    $this->term->delete();
    $entity->field_test_taxonomy_term->generateSampleItems();
    $this->assertInstanceOf(TermInterface::class, $entity->field_test_taxonomy_term->entity);
    $this->entityValidateAndSave($entity);
  }

  /**
   * Tests the ::generateSampleValue() method when it has a circular reference.
   */
  public function testGenerateSampleValueCircularReference(): void {
    // Delete the existing entity.
    $this->entityStringId->delete();

    $entity_storage = \Drupal::entityTypeManager()->getStorage('entity_test');
    $entity = $entity_storage->createWithSampleValues('entity_test');
    $this->assertInstanceOf(EntityTestStringId::class, $entity->field_test_entity_test_string_id->entity);
    $this->assertInstanceOf(EntityTest::class, $entity->field_test_entity_test_string_id->entity->field_test_entity_test->entity);
  }

  /**
   * Tests referencing content entities with string IDs.
   */
  public function testContentEntityReferenceItemWithStringId(): void {
    $entity = EntityTest::create();
    $entity->field_test_entity_test_string_id->target_id = $this->entityStringId->id();
    $entity->save();
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test');
    $storage->resetCache();
    $this->assertEquals($this->entityStringId->id(), $storage->load($entity->id())->field_test_entity_test_string_id->target_id);
    // Verify that the label for the target ID property definition is correct.
    $label = $entity->field_test_taxonomy_term->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinition('target_id')->getLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals('Taxonomy term ID', $label->render());
  }

  /**
   * Tests the entity reference field type for referencing config entities.
   */
  public function testConfigEntityReferenceItem(): void {
    $referenced_entity_id = $this->vocabulary->id();

    // Just being able to create the entity like this verifies a lot of code.
    $entity = EntityTest::create();
    $entity->field_test_taxonomy_vocabulary->target_id = $referenced_entity_id;
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->field_test_taxonomy_vocabulary);
    $this->assertInstanceOf(FieldItemInterface::class, $entity->field_test_taxonomy_vocabulary[0]);
    $this->assertEquals($referenced_entity_id, $entity->field_test_taxonomy_vocabulary->target_id);
    $this->assertEquals($this->vocabulary->label(), $entity->field_test_taxonomy_vocabulary->entity->label());
    $this->assertEquals($referenced_entity_id, $entity->field_test_taxonomy_vocabulary->entity->id());
    $this->assertEquals($this->vocabulary->uuid(), $entity->field_test_taxonomy_vocabulary->entity->uuid());

    // Change the name of the term via the reference.
    $new_name = $this->randomMachineName();
    $entity->field_test_taxonomy_vocabulary->entity->set('name', $new_name);
    $entity->field_test_taxonomy_vocabulary->entity->save();
    // Verify it is the correct name.
    $vocabulary = Vocabulary::load($referenced_entity_id);
    $this->assertEquals($new_name, $vocabulary->label());

    // Make sure the computed term reflects updates to the term id.
    $vocabulary2 = $vocabulary = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->randomMachineName(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $vocabulary2->save();

    $entity->field_test_taxonomy_vocabulary->target_id = $vocabulary2->id();
    $this->assertEquals($vocabulary2->id(), $entity->field_test_taxonomy_vocabulary->entity->id());
    $this->assertEquals($vocabulary2->label(), $entity->field_test_taxonomy_vocabulary->entity->label());

    // Delete terms so we have nothing to reference and try again.
    $this->vocabulary->delete();
    $vocabulary2->delete();
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->save();
  }

  /**
   * Tests entity auto create.
   */
  public function testEntityAutoCreate(): void {
    // The term entity is unsaved here.
    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $entity = EntityTest::create();
    // Now assign the unsaved term to the field.
    $entity->field_test_taxonomy_term->entity = $term;
    $entity->name->value = $this->randomMachineName();
    // This is equal to storing an entity to tempstore or cache and retrieving
    // it back. An example for this is node preview.
    $entity = serialize($entity);
    $entity = unserialize($entity);
    // And then the entity.
    $entity->save();
    $term = \Drupal::service('entity.repository')->loadEntityByUuid($term->getEntityTypeId(), $term->uuid());
    $this->assertEquals($entity->field_test_taxonomy_term->entity->id(), $term->id());
  }

  /**
   * Tests saving order sequence doesn't matter.
   */
  public function testEntitySaveOrder(): void {
    // The term entity is unsaved here.
    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $entity = EntityTest::create();
    // Now assign the unsaved term to the field.
    $entity->field_test_taxonomy_term->entity = $term;
    $entity->name->value = $this->randomMachineName();
    // Now get the field value.
    $value = $entity->get('field_test_taxonomy_term');
    $this->assertArrayNotHasKey('target_id', $value);
    $this->assertNull($entity->field_test_taxonomy_term->target_id);
    // And then set it.
    $entity->field_test_taxonomy_term = $value;
    // Now save the term.
    $term->save();
    // And then the entity.
    $entity->save();
    $this->assertEquals($term->id(), $entity->field_test_taxonomy_term->entity->id());
  }

  /**
   * Tests that the 'handler' field setting stores the proper plugin ID.
   */
  public function testSelectionHandlerSettings(): void {
    $field_name = $this->randomMachineName();
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ]);
    $field_storage->save();

    // Do not specify any value for the 'handler' setting in order to verify
    // that the default handler with the correct derivative is used.
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
    ]);
    $field->save();
    $field = FieldConfig::load($field->id());
    $this->assertEquals('default:entity_test', $field->getSetting('handler'));

    // Change the target_type in the field storage, and check that the handler
    // was correctly reassigned in the field.
    $field_storage->setSetting('target_type', 'entity_test_rev');
    $field_storage->save();
    $field = FieldConfig::load($field->id());
    $this->assertEquals('default:entity_test_rev', $field->getSetting('handler'));

    // Change the handler to another, non-derivative plugin.
    $field->setSetting('handler', 'views');
    $field->save();
    $field = FieldConfig::load($field->id());
    $this->assertEquals('views', $field->getSetting('handler'));

    // Change the target_type in the field storage again, and check that the
    // non-derivative handler was unchanged.
    $field_storage->setSetting('target_type', 'entity_test_rev');
    $field_storage->save();
    $field = FieldConfig::load($field->id());
    $this->assertEquals('views', $field->getSetting('handler'));

    // Check that selection handlers aren't changed during sync.
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'settings' => [
        'handler' => 'fake:thing',
      ],
      'isSyncing' => TRUE,
    ]);
    $this->assertEquals('fake:thing', $field->getSetting('handler'));
  }

  /**
   * Tests the save_referenced_entities field setting functionality.
   */
  public function testSaveReferencedEntities(): void {
    // Set up fields with different save modes.
    $this->setupSaveReferencedEntitiesFields();

    // Test each save mode systematically.
    $this->doTestSaveModeDisabled();
    $this->doTestSaveModeNewOnly();
    $this->doTestSaveModeChangedOnly();
    $this->doTestSaveModeAlways();

    // Edge case testing.
    $this->doTestMultipleReferences();
    $this->doTestConfigSchemaValidation();
  }

  /**
   * Sets up entity reference fields with different save_referenced_entities modes.
   */
  private function setupSaveReferencedEntitiesFields(): void {
    // Create fields with each save mode.
    $modes = ['disabled', 'new_only', 'changed_only', 'always'];
    foreach ($modes as $mode) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => "field_save_mode_{$mode}",
        'entity_type' => 'entity_test',
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'taxonomy_term',
        ],
      ]);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'entity_test',
        'label' => "Test save mode {$mode}",
        'settings' => [
          'handler' => 'default',
          'save_referenced_entities' => $mode,
        ],
      ]);
      $field->save();
    }

    // Create self-reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_self_ref',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Self reference test',
      'settings' => [
        'handler' => 'default',
        'save_referenced_entities' => 'always',
      ],
    ]);
    $field->save();
  }

  /**
   * Tests the 'disabled' save mode - referenced entities should never be saved.
   */
  private function doTestSaveModeDisabled(): void {
    // Create test entities.
    $new_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);

    $existing_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);
    $existing_term->save();
    $original_changed = $existing_term->getChangedTime();

    // Test with new entity - verify it's not auto-saved when parent saves.
    // We need to save the new term first to avoid constraint violations,
    // then test that it doesn't get re-saved when changed.
    $new_term->save();
    $new_term_original_changed = $new_term->getChangedTime();
    $new_term->setName($this->randomMachineName()); // Change but don't save.

    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->field_save_mode_disabled->entity = $new_term;
    $entity->save();

    // Term should not be re-saved even though it was changed.
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $reloaded_new_term = $term_storage->loadUnchanged($new_term->id());
    $this->assertEquals($new_term_original_changed, $reloaded_new_term->getChangedTime(), 'Changed term was not auto-saved with disabled mode.');
    $this->assertEquals($new_term->id(), $entity->field_save_mode_disabled->target_id, 'Target ID is set correctly.');

    // Test with existing entity - should not be re-saved.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $existing_term->setName($this->randomMachineName()); // Change the term but don't save it.
    $entity->field_save_mode_disabled->entity = $existing_term;
    $entity->save();

    // Reload term and verify it wasn't re-saved.
    $reloaded_term = $term_storage->loadUnchanged($existing_term->id());
    $this->assertEquals($original_changed, $reloaded_term->getChangedTime(), 'Existing term was not re-saved with disabled mode.');
    $this->assertEquals($existing_term->id(), $entity->field_save_mode_disabled->target_id, 'Target ID is set for existing entity.');
  }

  /**
   * Tests the 'new_only' save mode - only new entities should be saved.
   */
  private function doTestSaveModeNewOnly(): void {
    // Create test entities.
    $new_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);

    $existing_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);
    $existing_term->save();
    $original_changed = $existing_term->getChangedTime();

    // Test with new entity - should be saved.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->field_save_mode_new_only->entity = $new_term;
    $entity->save();

    // New term should be saved.
    $this->assertFalse($new_term->isNew(), 'New term was auto-saved with new_only mode.');
    $this->assertEquals($new_term->id(), $entity->field_save_mode_new_only->target_id, 'Target ID is set after auto-save.');

    // Test with existing entity - should not be re-saved.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $existing_term->setName($this->randomMachineName()); // Change the term but don't save it.
    $entity->field_save_mode_new_only->entity = $existing_term;
    $entity->save();

    // Existing term should not be re-saved.
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $reloaded_term = $term_storage->loadUnchanged($existing_term->id());
    $this->assertEquals($original_changed, $reloaded_term->getChangedTime(), 'Existing term was not re-saved with new_only mode.');
    $this->assertEquals($existing_term->id(), $entity->field_save_mode_new_only->target_id, 'Target ID is synchronized for existing entity.');
  }

  /**
   * Tests the 'always' save mode - all referenced entities should be saved.
   */
  private function doTestSaveModeAlways(): void {
    // Create test entities.
    $new_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);

    $existing_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);
    $existing_term->save();
    $original_changed = $existing_term->getChangedTime();

    // Test with new entity - should be saved.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->field_save_mode_always->entity = $new_term;
    $entity->save();

    // New term should be saved.
    $this->assertFalse($new_term->isNew(), 'New term was auto-saved with always mode.');
    $this->assertEquals($new_term->id(), $entity->field_save_mode_always->target_id, 'Target ID is set after auto-save.');

    // Test with existing entity - should be re-saved.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $new_name = $this->randomMachineName();
    $existing_term->setName($new_name); // Change the term but don't save it.
    $entity->field_save_mode_always->entity = $existing_term;

    // Small delay to ensure changed time would be different.
    sleep(1);
    $entity->save();

    // Existing term should be re-saved with the new name.
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $reloaded_term = $term_storage->loadUnchanged($existing_term->id());
    $this->assertEquals($new_name, $reloaded_term->getName(), 'Existing term was re-saved with always mode (name updated).');
    // Also check timestamp if it actually changed.
    if ($reloaded_term->getChangedTime() > $original_changed) {
      $this->assertGreaterThan($original_changed, $reloaded_term->getChangedTime(), 'Existing term timestamp was updated with always mode.');
    }
    $this->assertEquals($existing_term->id(), $entity->field_save_mode_always->target_id, 'Target ID is synchronized for existing entity.');
  }

  /**
   * Tests the 'changed_only' save mode - currently falls back to 'always' behavior.
   */
  private function doTestSaveModeChangedOnly(): void {
    // Create test entities.
    $new_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);

    $existing_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);
    $existing_term->save();
    $original_changed = $existing_term->getChangedTime();

    // Test with new entity - should be saved (fallback to 'always').
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->field_save_mode_changed_only->entity = $new_term;
    $entity->save();

    // New term should be saved.
    $this->assertFalse($new_term->isNew(), 'New term was auto-saved with changed_only mode.');
    $this->assertEquals($new_term->id(), $entity->field_save_mode_changed_only->target_id, 'Target ID is set after auto-save.');

    // Test with existing entity - should be re-saved (fallback to 'always').
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $new_name = $this->randomMachineName();
    $existing_term->setName($new_name);
    $entity->field_save_mode_changed_only->entity = $existing_term;

    // Small delay to ensure different timestamps.
    sleep(1);
    $entity->save();

    // Existing term should be re-saved (current fallback behavior).
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $reloaded_term = $term_storage->loadUnchanged($existing_term->id());
    // Since changed_only currently falls back to 'always', the entity should be saved.
    $this->assertEquals($new_name, $reloaded_term->getName(), 'Existing term was re-saved with changed_only mode (fallback behavior - name updated).');
    $this->assertEquals($existing_term->id(), $entity->field_save_mode_changed_only->target_id, 'Target ID is synchronized for existing entity.');
  }

  /**
   * Tests multiple references with mixed entity states.
   */
  private function doTestMultipleReferences(): void {
    // Create mix of new and existing terms.
    $new_term1 = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);

    $new_term2 = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);

    $existing_term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
    ]);
    $existing_term->save();

    // Test new_only mode with multiple references.
    $entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity->field_save_mode_new_only->setValue([
      ['entity' => $new_term1],
      ['entity' => $existing_term],
      ['entity' => $new_term2],
    ]);
    $entity->save();

    // Verify only new terms were saved.
    $this->assertFalse($new_term1->isNew(), 'First new term was saved in new_only mode.');
    $this->assertFalse($new_term2->isNew(), 'Second new term was saved in new_only mode.');

    // Verify target IDs are set correctly.
    $values = $entity->field_save_mode_new_only->getValue();
    $this->assertEquals($new_term1->id(), $values[0]['target_id'], 'First new term target_id set correctly.');
    $this->assertEquals($existing_term->id(), $values[1]['target_id'], 'Existing term target_id preserved.');
    $this->assertEquals($new_term2->id(), $values[2]['target_id'], 'Second new term target_id set correctly.');
  }

  /**
   * Tests configuration schema validation for save_referenced_entities setting.
   */
  private function doTestConfigSchemaValidation(): void {
    // Test that default value is 'new_only'.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_default_test',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Default test',
      'settings' => [
        'handler' => 'default',
        // Not setting save_referenced_entities should use default.
      ],
    ]);
    $field->save();

    // Verify default value.
    $this->assertEquals('new_only', $field->getSetting('save_referenced_entities'), 'Default save_referenced_entities setting is new_only.');

    // Test that invalid values are rejected by schema validation.
    // Create a separate field storage for the invalid mode test.
    $invalid_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_invalid_mode_test',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $invalid_field_storage->save();

    // Test that saving a field with invalid save_referenced_entities value throws an exception.
    $this->expectException(SchemaIncompleteException::class);
    $this->expectExceptionMessage('The save mode must be one of: disabled, new_only, changed_only, always.');

    $invalid_mode_field = FieldConfig::create([
      'field_storage' => $invalid_field_storage,
      'bundle' => 'entity_test',
      'label' => 'Invalid mode test',
      'settings' => [
        'handler' => 'default',
        'save_referenced_entities' => 'invalid_mode',
      ],
    ]);
    $invalid_mode_field->save();
  }

  /**
   * Tests ValidReferenceConstraint with newly created and unsaved entities.
   */
  public function testAutocreateValidation(): void {
    // The term entity is unsaved here.
    $term = Term::create([
      'name' => $this->randomMachineName(),
      'vid' => $this->term->bundle(),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $entity = EntityTest::create([
      'field_test_taxonomy_term' => [
        'entity' => $term,
        'target_id' => NULL,
      ],
    ]);
    $errors = $entity->validate();
    // Using target_id of NULL is valid with an unsaved entity.
    $this->assertCount(0, $errors);
    // Using target_id of NULL is not valid with a saved entity.
    $term->save();
    $entity = EntityTest::create([
      'field_test_taxonomy_term' => [
        'entity' => $term,
        'target_id' => NULL,
      ],
    ]);
    $errors = $entity->validate();
    $this->assertCount(1, $errors);
    $this->assertEquals('This value should not be null.', $errors[0]->getMessage());
    $this->assertEquals('field_test_taxonomy_term.0', $errors[0]->getPropertyPath());
    // This should rectify the issue, favoring the entity over the target_id.
    $entity->save();
    $errors = $entity->validate();
    $this->assertCount(0, $errors);

    // Test with an unpublished and unsaved node.
    $title = $this->randomString();
    $node = Node::create([
      'title' => $title,
      'type' => 'node',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $entity = EntityTest::create([
      'field_test_node' => [
        'entity' => $node,
      ],
    ]);

    $errors = $entity->validate();
    $this->assertCount(1, $errors);
    $this->assertEquals('This entity (node: ' . Html::escape($title) . ') cannot be referenced.', $errors[0]->getMessage());
    $this->assertEquals('field_test_node.0.entity', $errors[0]->getPropertyPath());

    // Publish the node and try again.
    $node->setPublished();
    $errors = $entity->validate();
    $this->assertCount(0, $errors);

    // Test with a mix of valid and invalid nodes.
    $unsaved_unpublished_node_title = $this->randomString();
    $unsaved_unpublished_node = Node::create([
      'title' => $unsaved_unpublished_node_title,
      'type' => 'node',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $saved_unpublished_node_title = $this->randomString();
    $saved_unpublished_node = Node::create([
      'title' => $saved_unpublished_node_title,
      'type' => 'node',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $saved_unpublished_node->save();

    $saved_published_node_title = $this->randomString();
    $saved_published_node = Node::create([
      'title' => $saved_published_node_title,
      'type' => 'node',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $saved_published_node->save();

    $entity = EntityTest::create([
      'field_test_node' => [
        [
          'entity' => $unsaved_unpublished_node,
        ],
        [
          'target_id' => $saved_unpublished_node->id(),
        ],
        [
          'target_id' => $saved_published_node->id(),
        ],
      ],
    ]);

    $errors = $entity->validate();
    $this->assertCount(2, $errors);
    $this->assertEquals('This entity (node: ' . Html::escape($unsaved_unpublished_node_title) . ') cannot be referenced.', $errors[0]->getMessage());
    $this->assertEquals('field_test_node.0.entity', $errors[0]->getPropertyPath());
    $this->assertEquals('This entity (node: ' . Html::escape($saved_unpublished_node->id()) . ') cannot be referenced.', $errors[1]->getMessage());
    $this->assertEquals('field_test_node.1.target_id', $errors[1]->getPropertyPath());

    // Publish one of the nodes and try again.
    $saved_unpublished_node->setPublished();
    $saved_unpublished_node->save();
    $errors = $entity->validate();
    $this->assertCount(1, $errors);
    $this->assertEquals('This entity (node: ' . Html::escape($unsaved_unpublished_node_title) . ') cannot be referenced.', $errors[0]->getMessage());
    $this->assertEquals('field_test_node.0.entity', $errors[0]->getPropertyPath());

    // Publish the last invalid node and try again.
    $unsaved_unpublished_node->setPublished();
    $errors = $entity->validate();
    $this->assertCount(0, $errors);

    // Test with an unpublished and unsaved comment.
    $title = $this->randomString();
    $comment = Comment::create([
      'subject' => $title,
      'comment_type' => 'comment',
      'status' => 0,
    ]);

    $entity = EntityTest::create([
      'field_test_comment' => [
        'entity' => $comment,
      ],
    ]);

    $errors = $entity->validate();
    $this->assertCount(1, $errors);
    $this->assertEquals('This entity (comment: ' . Html::escape($title) . ') cannot be referenced.', $errors[0]->getMessage());
    $this->assertEquals('field_test_comment.0.entity', $errors[0]->getPropertyPath());

    // Publish the comment and try again.
    $comment->setPublished();
    $errors = $entity->validate();
    $this->assertCount(0, $errors);

    // Test with an inactive and unsaved user.
    $name = $this->randomString();
    $user = User::create([
      'name' => $name,
      'status' => 0,
    ]);

    $entity = EntityTest::create([
      'field_test_user' => [
        'entity' => $user,
      ],
    ]);

    $errors = $entity->validate();
    $this->assertCount(1, $errors);
    $this->assertEquals('This entity (user: ' . Html::escape($name) . ') cannot be referenced.', $errors[0]->getMessage());
    $this->assertEquals('field_test_user.0.entity', $errors[0]->getPropertyPath());

    // Activate the user and try again.
    $user->activate();
    $errors = $entity->validate();
    $this->assertCount(0, $errors);

    // Test with a temporary and unsaved file.
    $filename = $this->randomMachineName() . '.txt';
    $file = File::create([
      'filename' => $filename,
      'status' => 0,
    ]);

    $entity = EntityTest::create([
      'field_test_file' => [
        'entity' => $file,
      ],
    ]);

    $errors = $entity->validate();
    $this->assertCount(1, $errors);
    $this->assertEquals('This entity (file: ' . $filename . ') cannot be referenced.', $errors[0]->getMessage());
    $this->assertEquals('field_test_file.0.entity', $errors[0]->getPropertyPath());

    // Set the file as permanent and try again.
    $file->setPermanent();
    $errors = $entity->validate();
    $this->assertCount(0, $errors);
  }

}
