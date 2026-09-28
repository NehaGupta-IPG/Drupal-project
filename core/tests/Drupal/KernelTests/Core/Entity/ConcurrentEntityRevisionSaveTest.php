<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests concurrent entity saves and revisions.
 *
 * @group Entity
 *
 * @coversDefaultClass \Drupal\Core\Entity\Plugin\Validation\Constraint\SequentialEntityRevisionCreationConstraintValidator
 */
class ConcurrentEntityRevisionSaveTest extends EntityKernelTestBase {

  const SAVE_NO_REVISION = 0;
  const SAVE_NEW_REVISION = 1;
  const SAVE_PENDING_REVISION = 3;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'system', 'field', 'text', 'filter', 'entity_test', 'language'];

  /**
   * The EntityTestMulRevChanged entity type storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $storage;

  /**
   * Counter used to generate meaningful entity labels.
   *
   * @var int
   */
  protected $saveCounter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');

    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('it')->save();
  }

  /**
   * Data provider for testConcurrentSave().
   */
  public function dataTestConcurrentSave() {
    return [
      'pending revision - pending revision' => [static::SAVE_PENDING_REVISION, static::SAVE_PENDING_REVISION],
      'no revision - pending revision' => [static::SAVE_NO_REVISION, static::SAVE_PENDING_REVISION],
      'new revision - pending revision' => [static::SAVE_NEW_REVISION, static::SAVE_PENDING_REVISION],
      'pending revision - no revision' => [static::SAVE_PENDING_REVISION, static::SAVE_NO_REVISION],
      'no revision - no revision' => [static::SAVE_NO_REVISION, static::SAVE_NO_REVISION],
      'new revision - no revision' => [static::SAVE_NEW_REVISION, static::SAVE_NO_REVISION],
      'pending revision - new revision' => [static::SAVE_PENDING_REVISION, static::SAVE_NEW_REVISION],
      'no revision - new revision' => [static::SAVE_NO_REVISION, static::SAVE_NEW_REVISION],
      'new revision - new revision' => [static::SAVE_NEW_REVISION, static::SAVE_NEW_REVISION],
    ];
  }

  /**
   * Tests concurrent entity saves with various save types.
   *
   * @param int $first_save_type
   *   The type of the first of the two "concurrent" saves as defined by the
   *   related ::SAVE_* constants.
   * @param int $second_save_type
   *   The type of the second of the two "concurrent" saves as defined by the
   *   related ::SAVE_* constants.
   *
   * @covers ::validate
   *
   * @dataProvider dataTestConcurrentSave
   */
  public function testConcurrentSave($first_save_type, $second_save_type) {
    $this->saveCounter = 1;

    // When we do not create a new revision in the first of the two concurrent
    // saves, only the changed timestamp can be used to detect concurrent edits.
    // Validate this logic by applying only the related validation.
    $changed_revision_validation = [TRUE];
    if ($first_save_type === static::SAVE_NO_REVISION) {
      $changed_revision_validation[] = FALSE;
    }

    foreach ($changed_revision_validation as $enabled) {
      $this->enableSequentialEntityRevisionCreationValidation($enabled);

      // Create a test entity.
      $user = $this->createUser();
      $entity = EntityTestMulRevChanged::create([
        'not_translatable' => $this->randomString(),
        'user_id' => $user->id(),
        'language' => 'en',
      ]);
      $this->doEntitySave($entity, static::SAVE_NO_REVISION, 0, FALSE);

      // Perform two "concurrent" saves and verify the second one triggers a
      // validation error.
      // @todo Revisit this once https://www.drupal.org/node/2784201 lands.
      $entity->original_latest_revision_id = $this->storage->getLatestRevisionId($entity->id());
      $entity_other_instance = clone $entity;
      $this->doEntitySave($entity, $first_save_type, 0, FALSE);
      // When the first revision is saved as a new default revision, we expect
      // also the changed timestamp validation to fail.
      $expected_violation_count = $first_save_type === static::SAVE_NEW_REVISION ? 2 : 1;
      $expect_sequential_entity_revision_creation_violation = ($first_save_type !== static::SAVE_NO_REVISION) ? TRUE : FALSE;
      $this->doEntitySave($entity_other_instance, $second_save_type, $expected_violation_count, $expect_sequential_entity_revision_creation_violation);
    }
  }

  /**
   * Performs an entity save of the specified "type".
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be saved.
   * @param int $type
   *   The save type, as defined by the ::SAVE_* constants.
   * @param int $expected_violation_count
   *   The number of validation expected violations.
   * @param bool $expect_sequential_entity_revision_creation_violation
   *   Whether or not to expect sequential entity revision creation.
   */
  protected function doEntitySave(ContentEntityInterface $entity, $type, $expected_violation_count, $expect_sequential_entity_revision_creation_violation) {
    // Set a meaningful entity label, useful for debugging.
    $entity->set('name', 'Title ' . ($this->saveCounter++));
    if ($type === static::SAVE_NEW_REVISION || $type === static::SAVE_PENDING_REVISION) {
      $entity->setNewRevision();
    }
    if ($type === static::SAVE_PENDING_REVISION) {
      $entity->isDefaultRevision(FALSE);
    }

    $violations = $entity->validate();
    $this->assertEquals($expected_violation_count, $violations->count(), 'Wrong violations count with constraints');
    $expected_violation_message = 'A new revision of this content has been created by another user, or you have already submitted modifications. As a result, your changes cannot be saved.';
    if ($expect_sequential_entity_revision_creation_violation) {
      $index = $expected_violation_count > 1 ? 1 : 0;
      $this->assertEquals($violations[$index]->getMessage(), $expected_violation_message);
    }
    elseif ($violations->count() > 0) {
      foreach ($violations as $violation) {
        $this->assertNotEquals($violation->getMessage(), $expected_violation_message);
      }
    }
    $entity->save();
  }

  /**
   * Tests that multilingual edits do not trigger validation errors.
   *
   * @covers ::validate
   */
  public function testMultilingualRevisionEdit() {
    $this->enableSequentialEntityRevisionCreationValidation();
    $this->enableEntityTestTranslation();

    // Create a new entity.
    /** @var \Drupal\entity_test\Entity\EntityTestMulRevChanged $entity */
    $entity = EntityTestMulRevChanged::create([
      'user_id' => $this->createUser()->id(),
      'name' => 'Test 1.1 EN',
    ]);
    $violations = $entity->validate();
    $this->assertEmpty($violations->count());
    $entity->save();

    // Create a new Italian translation in a new pending revision.
    $entity = $this->loadLatestTranslationAffectingRevision($entity->id(), 'en');
    /** @var \Drupal\entity_test\Entity\EntityTestMulRevChanged $translation */
    $translation = $this->storage->createRevision($entity->addTranslation('it'), FALSE);
    $translation->setName('Test 1.2 IT');
    $violations = $translation->validate();
    $this->assertEmpty($violations->count());
    $translation->save();

    // Start from the latest translation-affecting revision and create an new
    // default revision for the default language.
    $entity = $this->loadLatestTranslationAffectingRevision($entity->id(), 'en');
    $entity = $this->storage->createRevision($entity, TRUE);
    $entity->setName('Test 1.3 EN');
    $violations = $entity->validate();
    $this->assertEmpty($violations->count());
    $entity->save();

    // Start from the latest translation-affecting revision and create an new
    // pending revision for the Italian translation.
    $entity = $this->loadLatestTranslationAffectingRevision($entity->id(), 'it');
    /** @var \Drupal\entity_test\Entity\EntityTestMulRevChanged $translation */
    $translation = $this->storage->createRevision($entity->getTranslation('it'), FALSE);
    $translation->setName('Test 1.4 IT');
    $violations = $translation->validate();
    $this->assertEmpty($violations->count());
    $translation->save();
  }

  /**
   * Loads the latest translation-affecting revision for the specified language.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\entity_test\Entity\EntityTestMulRevChanged
   *   A revision entity object.
   */
  protected function loadLatestTranslationAffectingRevision($entity_id, $langcode) {
    $revision_id = $this->storage->getLatestTranslationAffectedRevisionId($entity_id, $langcode);
    /** @var \Drupal\entity_test\Entity\EntityTestMulRevChanged $entity */
    $entity = $this->storage->loadRevision($revision_id);
    // @todo Revisit this once https://www.drupal.org/node/2784201 lands.
    $entity->original_latest_revision_id = $this->storage->getLatestRevisionId($entity_id);
    return $entity;
  }

  /**
   * Enables the "SequentialEntityRevisionCreation" constraint.
   *
   * @param bool $enabled
   *   (optional) TRUE if validation should be enabled, FALSE otherwise.
   *   Defaults to TRUE.
   */
  protected function enableSequentialEntityRevisionCreationValidation($enabled = TRUE) {
    $data_type_info = [
      'entity:entity_test_mulrev_changed' => [
        'constraints' => $enabled ? ['SequentialEntityRevisionCreation' => NULL] : [],
      ],
    ];
    $this->state->set('entity_test.data_type_info', $data_type_info);
    \Drupal::typedDataManager()->clearCachedDefinitions();
  }

}
