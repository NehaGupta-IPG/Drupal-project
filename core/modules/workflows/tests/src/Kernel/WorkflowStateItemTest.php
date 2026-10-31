<?php

declare(strict_types=1);

namespace Drupal\Tests\workflows\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RenderContext;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\Plugin\Field\FieldFormatter\WorkflowStateFormatter;
use Drupal\workflows\Plugin\Field\FieldType\WorkflowStateItem;
use Drupal\workflows\WorkflowStateFieldItemList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the workflow_state field type.
 */
#[CoversClass(WorkflowStateItem::class)]
#[CoversClass(WorkflowStateFieldItemList::class)]
#[CoversClass(WorkflowStateFormatter::class)]
#[Group('workflows')]
#[RunTestsInSeparateProcesses]
class WorkflowStateItemTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'user',
    'workflows',
    'workflow_type_test',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    $this->setUpCurrentUser();

    $workflow = Workflow::create([
      'id' => 'test_workflow',
      'label' => 'Test Workflow',
      'type' => 'workflow_type_entity_test',
    ]);
    $type = $workflow->getTypePlugin();
    $type->addState('draft', 'Draft');
    $type->addState('review', 'Review');
    $type->addState('published', 'Published');
    $type->addTransition('send_to_review', 'Send to review', ['draft'], 'review');
    $type->addTransition('publish', 'Publish', ['review'], 'published');
    $type->addTransition('reject', 'Reject', ['review'], 'draft');
    $workflow->save();

    FieldStorageConfig::create([
      'field_name' => 'field_state',
      'entity_type' => 'entity_test',
      'type' => 'workflow_state',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_state',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'State',
      'settings' => ['workflow' => 'test_workflow'],
    ])->save();
  }

  /**
   * Tests the full field lifecycle: default value, transitions, labels, save.
   */
  public function testFieldLifecycle(): void {
    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);

    // Default value is the initial state.
    $item = $entity->get('field_state')->first();
    $this->assertEquals('draft', $item->getStateId());
    $this->assertEquals('draft', $item->getOriginalStateId());
    $this->assertEquals('Draft', $item->getStateLabel());
    $this->assertEquals('Draft', $item->getOriginalStateLabel());
    $this->assertTrue($item->isValid());

    // Available transitions from draft.
    $transitions = $item->getTransitions();
    $this->assertCount(1, $transitions);
    $this->assertArrayHasKey('send_to_review', $transitions);

    // Apply transition.
    $item->applyTransitionById('send_to_review');
    $this->assertEquals('review', $item->getStateId());
    $this->assertEquals('Review', $item->getStateLabel());
    $this->assertEquals('draft', $item->getOriginalStateId());
    $this->assertEquals('Draft', $item->getOriginalStateLabel());
    $this->assertTrue($item->isValid());

    // Validate before save.
    $violations = $entity->validate();
    $this->assertCount(0, $violations);

    // Save and verify persistence.
    $entity->save();
    $loaded = EntityTest::load($entity->id());
    $this->assertEquals('review', $loaded->get('field_state')->value);

    // After reload, original state is reset.
    $item = $loaded->get('field_state')->first();
    $this->assertEquals('review', $item->getOriginalStateId());

    // Apply another transition using the object API.
    $publish = $item->getWorkflow()->getTypePlugin()->getTransition('publish');
    $item->applyTransition($publish);
    $loaded->save();
    $reloaded = EntityTest::load($entity->id());
    $this->assertEquals('published', $reloaded->get('field_state')->value);

    // Sample value generation.
    $entity = EntityTest::create(['name' => 'Test entity']);
    $entity->get('field_state')->generateSampleItems();
    $this->assertContains($entity->get('field_state')->value, ['draft', 'review', 'published']);

    // getPossibleOptions() returns all states regardless of current state.
    $entity = EntityTest::create(['name' => 'Test entity']);
    $item = $entity->get('field_state')->first();
    $this->assertCount(3, $item->getPossibleOptions());
    $this->assertEquals(['draft', 'review', 'published'], $item->getPossibleValues());
  }

  /**
   * Tests transition access filtering and constraint violation.
   */
  public function testTransitionAccess(): void {
    // Create entity in review state.
    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    $entity->get('field_state')->first()->applyTransitionById('send_to_review');
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $item = $entity->get('field_state')->first();

    // Block the 'publish' transition.
    \Drupal::keyValue('workflow_type_entity_test')->set('blocked_transitions', ['publish']);

    // getTransitions() excludes blocked transition.
    $transitions = $item->getTransitions();
    $this->assertArrayHasKey('reject', $transitions);
    $this->assertArrayNotHasKey('publish', $transitions);

    // getSettableOptions() excludes blocked target state.
    $options = $item->getSettableOptions();
    $this->assertArrayHasKey('review', $options);
    $this->assertArrayHasKey('draft', $options);
    $this->assertArrayNotHasKey('published', $options);

    // isTransitionAllowed() returns FALSE for blocked transition.
    $workflow = Workflow::load('test_workflow');
    $publish = $workflow->getTypePlugin()->getTransition('publish');
    $this->assertFalse($item->isTransitionAllowed($publish));

    // isValid() returns FALSE when the transition is blocked.
    $entity->set('field_state', 'published');
    $this->assertFalse($entity->get('field_state')->first()->isValid());

    // Constraint: direct state change produces access violation.
    $violations = $entity->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('You do not have access to transition from <em class="placeholder">Review</em> to <em class="placeholder">Published</em>.', (string) $violations[0]->getMessage());
  }

  /**
   * Tests invalid states, transitions, and incompatible workflow types.
   */
  public function testInvalidOperations(): void {
    // Invalid state.
    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    $entity->set('field_state', 'nonexistent');
    $this->assertFalse($entity->get('field_state')->first()->isValid());
    $violations = $entity->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The state <em class="placeholder">nonexistent</em> is not valid.', (string) $violations[0]->getMessage());

    // Invalid transition path (no transition from published).
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_state' => 'published',
    ]);
    $entity->save();
    $entity->set('field_state', 'draft');
    $violations = $entity->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The transition from <em class="placeholder">Published</em> to <em class="placeholder">Draft</em> is not allowed.', (string) $violations[0]->getMessage());

    // applyTransition() with wrong from-state.
    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    $workflow = Workflow::load('test_workflow');
    $publish = $workflow->getTypePlugin()->getTransition('publish');
    try {
      $entity->get('field_state')->first()->applyTransition($publish);
      $this->fail('Expected InvalidArgumentException');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('The transition "publish" is not allowed. (Current state: "draft".)', $e->getMessage());
    }

    // applyTransitionById() with unknown transition ID.
    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    try {
      $entity->get('field_state')->first()->applyTransitionById('nonexistent');
      $this->fail('Expected InvalidArgumentException');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('Unknown transition ID "nonexistent".', $e->getMessage());
    }

    // Incompatible workflow type.
    $incompatible = Workflow::create([
      'id' => 'incompatible',
      'label' => 'Incompatible',
      'type' => 'workflow_type_test',
    ]);
    $incompatible->getTypePlugin()->addState('start', 'Start');
    $incompatible->save();

    FieldConfig::loadByName('entity_test', 'entity_test', 'field_state')
      ->setSetting('workflow', 'incompatible')
      ->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('The workflow type plugin "workflow_type_test" must implement EntityWorkflowTypeInterface to be used with the workflow_state field type.');
    $entity->get('field_state')->first()->getWorkflow();
  }

  /**
   * Tests workflow_callback setting.
   */
  public function testWorkflowCallback(): void {
    // Remove the explicit workflow setting and use a callback instead.
    FieldConfig::loadByName('entity_test', 'entity_test', 'field_state')
      ->setSetting('workflow_callback', static::class . '::workflowCallback')
      ->setSetting('workflow', '')
      ->save();

    $entity = EntityTest::create(['name' => 'Test entity']);
    $this->assertEquals('test_workflow', $entity->get('field_state')->first()->getWorkflow()->id());
  }

  /**
   * Tests default value form handling when workflow resolution is not possible.
   */
  public function testDefaultValuesFormWithoutWorkflow(): void {
    $form = [];
    $form_state = new FormState();

    $entity = EntityTest::create(['name' => 'Test entity']);
    $default_values_form = $entity->get('field_state')->defaultValuesForm($form, $form_state);
    $this->assertSame('Draft', $default_values_form['#plain_text']);

    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', 'field_state');
    $this->assertNotNull($field_config);
    $field_config->setSetting('workflow', '')->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $entity = EntityTest::create(['name' => 'Test entity']);
    $item_list = $entity->get('field_state');
    $this->assertSame([], $item_list->defaultValuesForm($form, $form_state));

    $field_config->setSetting('workflow', 'test_workflow')->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_state' => 'missing_state',
    ]);
    $this->assertSame([], $entity->get('field_state')->defaultValuesForm($form, $form_state));
  }

  /**
   * Tests that pre/post transition hooks fire with correct arguments on save.
   */
  public function testTransitionHooks(): void {
    // Create and save in the initial state so the entity has a stable ID before
    // any transition hook is expected to fire.
    $entity = EntityTest::create(['name' => 'Test entity']);
    $entity->save();
    $entity_id = $entity->id();

    // Apply a transition and save: both hooks should fire once.
    $entity = EntityTest::load($entity_id);
    $entity->get('field_state')->first()->applyTransitionById('send_to_review');
    $entity->save();

    $pre_log = \Drupal::state()->get('workflow_type_test.pre_transition_log', []);
    $post_log = \Drupal::state()->get('workflow_type_test.post_transition_log', []);

    $this->assertCount(1, $pre_log, 'hook_workflows_pre_transition fired once.');
    $this->assertEquals('test_workflow', $pre_log[0]['workflow']);
    $this->assertEquals('send_to_review', $pre_log[0]['transition']);
    $this->assertEquals($entity_id, $pre_log[0]['entity_id']);
    $this->assertEquals('field_state', $pre_log[0]['field_name']);

    $this->assertCount(1, $post_log, 'hook_workflows_post_transition fired once.');
    $this->assertEquals('test_workflow', $post_log[0]['workflow']);
    $this->assertEquals('send_to_review', $post_log[0]['transition']);
    $this->assertEquals($entity_id, $post_log[0]['entity_id']);
    $this->assertEquals('field_state', $post_log[0]['field_name']);

    // Re-save without a state change: hooks must NOT fire again.
    $entity = EntityTest::load($entity_id);
    $entity->save();

    $pre_log = \Drupal::state()->get('workflow_type_test.pre_transition_log', []);
    $post_log = \Drupal::state()->get('workflow_type_test.post_transition_log', []);
    $this->assertCount(1, $pre_log, 'hook_workflows_pre_transition does not fire when state is unchanged.');
    $this->assertCount(1, $post_log, 'hook_workflows_post_transition does not fire when state is unchanged.');
  }

  /**
   * Tests the workflow_state formatter outputs the state label (or raw ID).
   */
  public function testFormatter(): void {
    $entity = EntityTest::create(['name' => 'Test entity']);
    $entity->save();

    // The formatter renders the state label.
    $output = $this->container->get('renderer')->executeInRenderContext(new RenderContext(), function () use ($entity) {
      return $entity->get('field_state')->view(['type' => 'workflow_state']);
    });
    $this->assertEquals(['#markup' => 'Draft'], $output[0]);

    // When the stored state no longer exists in the workflow, fall back to the
    // raw state ID.
    \Drupal::database()->update('entity_test__field_state')
      ->fields(['field_state_value' => 'orphaned_state'])
      ->condition('entity_id', $entity->id())
      ->execute();

    $entity = EntityTest::load($entity->id());
    $output = $this->container->get('renderer')->executeInRenderContext(new RenderContext(), function () use ($entity) {
      return $entity->get('field_state')->view(['type' => 'workflow_state']);
    });
    $this->assertEquals(['#markup' => 'orphaned_state'], $output[0]);
  }

  /**
   * Tests fieldSettingsForm() filters to EntityWorkflowTypeInterface workflows.
   */
  public function testFieldSettingsForm(): void {
    // Create an incompatible workflow (workflow_type_test does not implement
    // EntityWorkflowTypeInterface).
    $incompatible = Workflow::create([
      'id' => 'incompatible_workflow',
      'label' => 'Incompatible Workflow',
      'type' => 'workflow_type_test',
    ]);
    $incompatible->getTypePlugin()->addState('start', 'Start');
    $incompatible->save();

    $item = EntityTest::create(['name' => 'Test entity'])
      ->get('field_state')
      ->first();

    $form = [];
    $form_state = new FormState();
    $element = $item->fieldSettingsForm($form, $form_state);

    // Compatible workflow appears in the select options.
    $this->assertArrayHasKey('test_workflow', $element['workflow']['#options']);
    // Incompatible workflow is excluded.
    $this->assertArrayNotHasKey('incompatible_workflow', $element['workflow']['#options']);
  }

  /**
   * Tests calculateDependencies() returns the workflow config dependency.
   */
  public function testCalculateDependencies(): void {
    $field_definition = FieldConfig::loadByName('entity_test', 'entity_test', 'field_state');
    $dependencies = WorkflowStateItem::calculateDependencies($field_definition);
    $this->assertEquals(['workflows.workflow.test_workflow'], $dependencies['config']);
  }

  /**
   * Callback for testing workflow_callback setting.
   */
  public static function workflowCallback(): string {
    return 'test_workflow';
  }

  /**
   * Tests that applyTransition() preserves the exact transition object.
   */
  public function testGetCurrentTransition(): void {
    $entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    $item = $entity->get('field_state')->first();

    // No transition applied yet.
    $this->assertNull($item->getCurrentTransition());

    // Apply a specific transition.
    $item->applyTransitionById('send_to_review');
    $this->assertEquals('send_to_review', $item->getCurrentTransition()->id());

    // Save - transition is cleared afterward.
    $entity->save();
    $item = EntityTest::load($entity->id())->get('field_state')->first();
    $this->assertNull($item->getCurrentTransition());

    // Add a 'fast_track_publish' transition (review → published) alongside the
    // existing 'publish' (review → published). Two transitions can share the
    // same from→to pair via direct configuration.
    $workflow = Workflow::load('test_workflow');
    $type = $workflow->getTypePlugin();
    $configuration = $type->getConfiguration();
    $configuration['transitions']['fast_track_publish'] = [
      'label' => 'Fast-track publish',
      'from' => ['review'],
      'to' => 'published',
      'weight' => 0,
    ];
    $type->setConfiguration($configuration);
    $workflow->save();

    // Both 'publish' and 'fast_track_publish' go from review → published. Apply
    // 'fast_track_publish', then block it. The constraint validator must check
    // access on the stored transition, not the first state-based match
    // ('publish'), which remains allowed.
    $type = Workflow::load('test_workflow')->getTypePlugin();
    $expedited = $type->getTransition('fast_track_publish');
    $item->applyTransition($expedited);
    $this->assertSame('fast_track_publish', $item->getCurrentTransition()->id());

    \Drupal::keyValue('workflow_type_entity_test')->set('blocked_transitions', ['fast_track_publish']);

    // Validation fails: getTransitionFromStateToState() would return
    // 'publish' (allowed), but the validator uses the stored transition
    // 'fast_track_publish' (blocked).
    $violations = $item->getEntity()->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('You do not have access to transition from <em class="placeholder">Review</em> to <em class="placeholder">Published</em>.', (string) $violations[0]->getMessage());
  }

}
