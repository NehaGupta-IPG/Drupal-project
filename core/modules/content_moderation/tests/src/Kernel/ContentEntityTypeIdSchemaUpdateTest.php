<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update path for the content_entity_type_id field schema.
 */
#[Group('content_moderation')]
#[RunTestsInSeparateProcesses]
class ContentEntityTypeIdSchemaUpdateTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'content_moderation', 'user', 'system', 'workflows'];

  /**
   * The entity type ID under test.
   */
  private const string ENTITY_TYPE_ID = 'content_moderation_state';

  /**
   * The field name under test.
   */
  private const string FIELD_NAME = 'content_entity_type_id';

  /**
   * The post update function under test.
   */
  private const string UPDATE_FUNCTION = 'content_moderation_post_update_content_entity_type_id_schema';

  /**
   * A moderated node, so the field has data when the update runs.
   */
  private NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema(self::ENTITY_TYPE_ID);
    $this->installConfig('content_moderation');

    NodeType::create(['type' => 'example', 'name' => 'Example'])->save();
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->save();

    // Roll the field back before creating content, so that the entity system
    // can change the columns itself.
    $this->installPreviousFieldSchema();

    $this->node = Node::create(['type' => 'example', 'title' => 'Test', 'moderation_state' => 'draft']);
    $this->node->save();
  }

  /**
   * Tests that the update converts the columns and the installed schema.
   */
  public function testUpdate(): void {
    $this->assertSame(
      ['type' => 'varchar', 'length' => 32, 'binary' => FALSE],
      $this->getInstalledColumnSchema(),
    );

    // Installing a module marks its post updates as already run, so clear them
    // to put the site in the state an existing site would be in.
    /** @var \Drupal\Core\Update\UpdateRegistry $registry */
    $registry = \Drupal::service('update.post_update_registry');
    $registry->filterOutInvokedUpdatesByExtension('content_moderation');
    $this->assertContains(self::UPDATE_FUNCTION, $registry->getPendingUpdateFunctions());

    // Run the update the same way update.php does.
    // @see \Drupal\system\Controller\DbUpdateController::triggerBatch()
    require_once $this->root . '/core/includes/update.inc';
    $context = ['sandbox' => [], 'results' => []];
    update_invoke_post_update(self::UPDATE_FUNCTION, $context);
    $this->assertArrayNotHasKey('#abort', $context['results']);
    $this->assertNotContains(self::UPDATE_FUNCTION, $registry->getPendingUpdateFunctions());

    $this->assertSame(
      ['type' => 'varchar_ascii', 'length' => 32, 'binary' => TRUE],
      $this->getInstalledColumnSchema(),
    );
    // The installed schema now matches what the code defines, so the update is
    // not reported as still pending.
    $this->assertSame($this->getCodeDefinition()->getColumns()['value'], $this->getInstalledColumnSchema());
    $this->assertArrayNotHasKey(self::ENTITY_TYPE_ID, \Drupal::entityDefinitionUpdateManager()->getChangeSummary());

    // Existing data survives the column change.
    $node = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($this->node->id());
    $this->assertSame('draft', $node->moderation_state->value);
  }

  /**
   * Restores the field storage schema the field had before the update.
   */
  private function installPreviousFieldSchema(): void {
    $original = BaseFieldDefinition::createFromFieldStorageDefinition($this->getCodeDefinition())
      ->setSetting('is_ascii', FALSE)
      ->setSetting('case_sensitive', FALSE);
    \Drupal::entityDefinitionUpdateManager()->updateFieldStorageDefinition($original);
  }

  /**
   * Returns the installed schema for the field under test.
   */
  private function getInstalledColumnSchema(): array {
    return \Drupal::entityDefinitionUpdateManager()
      ->getFieldStorageDefinition(self::FIELD_NAME, self::ENTITY_TYPE_ID)
      ->getColumns()['value'];
  }

  /**
   * Returns the field storage definition the code currently defines.
   */
  private function getCodeDefinition(): FieldStorageDefinitionInterface {
    return \Drupal::service('entity_field.manager')
      ->getFieldStorageDefinitions(self::ENTITY_TYPE_ID)[self::FIELD_NAME];
  }

}
