<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestConstraintViolation;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests validation constraints for ValidReferenceConstraintValidator.
 */
#[Group('Validation')]
#[RunTestsInSeparateProcesses]
class ValidReferenceConstraintValidatorTest extends EntityKernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * The typed data manager to use.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig('node');
    $this->typedDataManager = $this->container->get('typed_data_manager');

    $this->createContentType(['type' => 'article', 'name' => 'Article']);
    $this->createContentType(['type' => 'page', 'name' => 'Basic page']);
  }

  /**
   * Tests the ValidReferenceConstraintValidator.
   */
  public function testValidation(): void {
    // Create a test entity to be referenced.
    $entity = $this->createUser();
    // By default entity references already have the ValidReference constraint.
    $definition = BaseFieldDefinition::create('entity_reference')
      ->setSettings(['target_type' => 'user']);

    $typed_data = $this->typedDataManager->create($definition, ['target_id' => $entity->id()]);
    $violations = $typed_data->validate();
    $this->assertEquals(0, $violations->count(), 'Validation passed for correct value.');

    // NULL is also considered a valid reference.
    $typed_data = $this->typedDataManager->create($definition, ['target_id' => NULL]);
    $violations = $typed_data->validate();
    $this->assertEquals(0, $violations->count(), 'Validation passed for correct value.');

    $typed_data = $this->typedDataManager->create($definition, ['target_id' => $entity->id()]);
    // Delete the referenced entity.
    $entity->delete();
    $violations = $typed_data->validate();
    $this->assertGreaterThan(0, $violations->count(), 'Validation failed for incorrect value.');

    // Make sure the information provided by a violation is correct.
    $violation = $violations[0];
    $this->assertEquals(sprintf('The referenced entity (user: %s) does not exist.', $entity->id()), $violation->getMessage(), 'The message for invalid value is correct.');
    $this->assertEquals($typed_data, $violation->getRoot(), 'Violation root is correct.');
  }

  /**
   * Tests the validation of pre-existing items in an entity reference field.
   */
  public function testPreExistingItemsValidation(): void {
    // Create two types of users, with and without access to bypass content
    // access.
    /** @var \Drupal\user\RoleInterface $role_with_access */
    $role_with_access = Role::create(['id' => 'role_with_access', 'label' => 'With access']);
    $role_with_access->grantPermission('access content');
    $role_with_access->grantPermission('bypass node access');
    $role_with_access->save();

    /** @var \Drupal\user\RoleInterface $role_without_access */
    $role_without_access = Role::create(['id' => 'role_without_access', 'label' => 'Without access']);
    $role_without_access->grantPermission('access content');
    $role_without_access->save();

    $user_with_access = User::create(['name' => $this->randomString(), 'roles' => ['role_with_access']]);
    $user_with_access->save();
    $user_without_access = User::create(['name' => $this->randomString(), 'roles' => ['role_without_access']]);
    $user_without_access->save();

    // Add an entity reference field.
    $this->createEntityReferenceField(
      'entity_test',
      'entity_test',
      'field_test',
      'Field test',
      'node',
      'default',
      ['target_bundles' => ['article', 'page']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    // Create four test nodes.
    $published_node = Node::create([
      'title' => 'Test published node',
      'type' => 'article',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $published_node->save();

    $unpublished_node = Node::create([
      'title' => 'Test unpublished node',
      'type' => 'article',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $unpublished_node->save();

    $different_bundle_node = Node::create([
      'title' => 'Test page node',
      'type' => 'page',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $different_bundle_node->save();

    $deleted_node = Node::create([
      'title' => 'Test deleted node',
      'type' => 'article',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $deleted_node->save();

    $referencing_entity = EntityTest::create([
      'field_test' => [
        ['entity' => $published_node],
        ['entity' => $unpublished_node],
        ['entity' => $different_bundle_node],
        ['entity' => $deleted_node],
      ],
    ]);

    // Check that users with access are able pass the validation for fields
    // without pre-existing content.
    $this->container->get('account_switcher')->switchTo($user_with_access);

    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(0, $violations);

    // Check that users without access are not able pass the validation for
    // fields without pre-existing content.
    $this->container->get('account_switcher')->switchTo($user_without_access);

    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals(sprintf('This entity (node: %s) cannot be referenced.', $unpublished_node->id()), $violations[0]->getMessage());

    // Now save the referencing entity which will create a pre-existing state
    // for it and repeat the checks. This time, the user without access should
    // be able to pass the validation as well because it's not changing the
    // pre-existing state.
    $referencing_entity->save();

    $this->container->get('account_switcher')->switchTo($user_with_access);

    // We check if the referencing entity was loaded by checking
    // if it was added to the memory cache.
    // This requires an empty memory cache for the test to be reliable.
    $this->container->get('entity.memory_cache')->reset();
    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(0, $violations);

    // No invalid target was found, so the referencing entity was not reloaded.
    $this->assertFalse($this->container->get('entity.memory_cache')->get('values:' . $referencing_entity->getEntityTypeId() . ':' . $referencing_entity->id()));

    // Check that users without access are able pass the validation for fields
    // with pre-existing content.
    $this->container->get('account_switcher')->switchTo($user_without_access);

    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(0, $violations);

    // Re-save the referencing entity and check that the referenced entity is
    // not affected.
    $referencing_entity->name->value = $this->randomString();
    $referencing_entity->save();
    $this->assertEquals($published_node->id(), $referencing_entity->field_test[0]->target_id);
    $this->assertEquals($unpublished_node->id(), $referencing_entity->field_test[1]->target_id);
    $this->assertEquals($different_bundle_node->id(), $referencing_entity->field_test[2]->target_id);
    $this->assertEquals($deleted_node->id(), $referencing_entity->field_test[3]->target_id);

    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(0, $violations);

    // Remove one of the referenceable bundles and check that a pre-existing
    // node of that bundle can not be referenced anymore.
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_test');
    $field->setSetting('handler_settings', ['target_bundles' => ['article']]);
    $field->save();
    $referencing_entity = $this->reloadEntity($referencing_entity);

    $this->container->get('entity.memory_cache')->reset();
    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals(sprintf('This entity (node: %s) cannot be referenced.', $different_bundle_node->id()), $violations[0]->getMessage());

    // An invalid target was found, so the referencing entity had to be reloaded.
    $this->assertNotFalse($this->container->get('entity.memory_cache')->get('values:' . $referencing_entity->getEntityTypeId() . ':' . $referencing_entity->id()));

    // Delete the last node and check that the pre-existing reference is not
    // valid anymore.
    $deleted_node->delete();

    $violations = $referencing_entity->field_test->validate();
    $this->assertCount(2, $violations);
    $this->assertEquals(sprintf('This entity (node: %s) cannot be referenced.', $different_bundle_node->id()), $violations[0]->getMessage());
    $this->assertEquals(sprintf('The referenced entity (node: %s) does not exist.', $deleted_node->id()), $violations[1]->getMessage());
  }

  /**
   * Tests the validation of new entities on an entity reference field.
   *
   * Additionally the test covers the recursive validation. The constraint
   * validator "ValidReferenceConstraintValidator" will be executed for the
   * parent entity and will trigger the validation of the referenced entities,
   * which will lead to executing the same validator for each referenced entity.
   * In order for the validation errors of the referenced entities to propagate
   * it shouldn't happen that the same constraint validator object of the parent
   * is used for the referenced entities. If the constraint validator object is
   * reused during the recursive validation then its execution context will be
   * exchanged when validating the referenced entities, which will not allow for
   * their validation errors to propagate.
   */
  public function testRecursiveValidation(): void {
    $this->installEntitySchema('entity_test_constraint_violation');

    // Add an entity reference field.
    $this->createEntityReferenceField(
      'entity_test',
      'entity_test',
      'field_test',
      'Field test',
      'entity_test_constraint_violation',
      'default',
      ['target_bundles' => ['entity_test_constraint_violation']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    // Create a new entity which will be referenced and should fail the
    // validation.
    $referenced_new_entity = EntityTestConstraintViolation::create([
      'name' => 'Referenced Entity',
      'user_id' => ['target_id' => 0],
    ]);

    $referencing_entity = EntityTest::create([
      'field_test' => [
        ['entity' => $referenced_new_entity],
      ],
      'user_id' => ['target_id' => 1],
    ]);

    $violations = $referencing_entity->validate();
    $this->assertCount(3, $violations);

    $violation = $violations[0];
    $this->assertEquals('user_id.0.target_id', $violation->getPropertyPath());
    $this->assertEquals(new TranslatableMarkup('The referenced entity (%type: %id) does not exist.', [
      '%type' => 'user',
      '%id' => 1,
    ]), $violation->getMessage());

    $violation = $violations[1];
    $this->assertEquals('field_test.0.entity.name', $violation->getPropertyPath());
    $this->assertEquals(new TranslatableMarkup('This entity (%type: %label) cannot be created. Reason(s): "%reason".', [
      '%type' => $referenced_new_entity->getEntityType()->getLabel() . ' ' . $referenced_new_entity->type->value,
      '%label' => $referenced_new_entity->label(),
      '%reason' => 'Widget constraint has failed.',
    ]), $violation->getMessage());

    $violation = $violations[2];
    $this->assertEquals('field_test.0.entity.test_field', $violation->getPropertyPath());
    $this->assertEquals(new TranslatableMarkup('This entity (%type: %label) cannot be created. Reason(s): "%reason".', [
      '%type' => $referenced_new_entity->getEntityType()->getLabel() . ' ' . $referenced_new_entity->type->value,
      '%label' => $referenced_new_entity->label(),
      '%reason' => 'Widget constraint has failed.',
    ]), $violation->getMessage());
  }

  /**
   * Tests self-references and cycles without losing nested violations.
   */
  public function testCircularValidation(): void {
    $this->createEntityReferenceField('entity_test', 'entity_test', 'field_cycle', 'Cycle', 'entity_test');
    $parent = EntityTest::create(['name' => 'Parent', 'user_id' => 0]);
    $parent->field_cycle->entity = $parent;
    $this->assertCount(0, $parent->validate());
    $this->assertFalse($parent->isValidationRunning());

    $child = EntityTest::create(['name' => str_repeat('x', 65), 'user_id' => 0]);
    $child->field_cycle->entity = $parent;
    $parent->field_cycle->entity = $child;
    $violations = $parent->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('field_cycle.0.entity.name.0.value', $violations[0]->getPropertyPath());
    $this->assertFalse($parent->isValidationRunning());
    $this->assertFalse($child->isValidationRunning());

    // Changing the invalid child must allow it to be validated again.
    $child->name = 'Child';
    $this->assertCount(0, $parent->validate());
  }

  /**
   * Tests a cycle between new entities from two different bundles.
   */
  public function testCircularValidationAcrossBundles(): void {
    [$article, $page] = $this->createCircularReferencesAcrossBundles();
    $this->assertSame($page, $article->field_to_page->entity);
    $this->assertSame($article, $page->field_to_article->entity);
    $this->assertCount(0, $article->validate());
    $this->assertFalse($article->isValidationRunning());
    $this->assertFalse($page->isValidationRunning());
  }

  /**
   * Tests that repeated validation preserves errors in a cyclic target.
   */
  public function testRepeatedCircularValidationAcrossBundles(): void {
    [$article, $page] = $this->createCircularReferencesAcrossBundles();
    $page->setTitle(str_repeat('x', 256));
    $violations = $article->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('field_to_page.0.entity.title.0.value', $violations[0]->getPropertyPath());
    $repeated_violations = $article->validate();
    $this->assertCount(1, $repeated_violations);
    $this->assertSame($violations[0]->getPropertyPath(), $repeated_violations[0]->getPropertyPath());
    $this->assertSame((string) $violations[0]->getMessage(), (string) $repeated_violations[0]->getMessage());
    $this->assertFalse($article->isValidationRunning());
    $this->assertFalse($page->isValidationRunning());
    $page->setTitle('Page');
    $this->assertCount(0, $article->validate());
  }

  /**
   * Tests that a previous target validation cannot hide reference errors.
   */
  public function testPreviouslyValidatedTargetAcrossBundles(): void {
    [$article, $page] = $this->createCircularReferencesAcrossBundles();
    $page->field_to_article->entity = NULL;
    $page->setTitle(str_repeat('x', 256));
    $this->assertCount(1, $page->validate());
    $this->assertCount(1, $article->validate());
    $this->assertCount(1, $article->validate());
  }

  /**
   * Creates an article and a page referencing each other without saving them.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The article and the page.
   */
  private function createCircularReferencesAcrossBundles(): array {
    $this->createEntityReferenceField('node', 'article', 'field_to_page', 'Page', 'node', 'default', ['target_bundles' => ['page' => 'page']]);
    $this->createEntityReferenceField('node', 'page', 'field_to_article', 'Article', 'node', 'default', ['target_bundles' => ['article' => 'article']]);
    $article = Node::create(['type' => 'article', 'title' => 'Article', 'uid' => 0]);
    $page = Node::create(['type' => 'page', 'title' => 'Page', 'uid' => 0]);
    $article->field_to_page->entity = $page;
    $page->field_to_article->entity = $article;
    return [$article, $page];
  }

}
