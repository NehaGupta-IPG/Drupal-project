<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @coversDefaultClass \Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency
 * @group layout_builder
 */
class SetInlineBlockDependencyTest extends KernelTestBase {

  use UserCreationTrait;
  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_discovery',
    'workflows',
    'content_moderation',
    'entity_test',
    'field',
    'block_content',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpCurrentUser();
    $this->installSchema('layout_builder', ['inline_block_usage']);

    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('content_moderation_state');

    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ])->save();

    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test_mulrevpub',
      'bundle' => 'entity_test_mulrevpub',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->enableLayoutBuilder();
    $display->setOverridable();
    $display->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_mulrevpub', 'entity_test_mulrevpub');
    $workflow->save();
  }

  /**
   * Test inline block dependencies with no route object.
   */
  public function testInlineBlockDependencyWithNoRouteObject() {
    // Create a mock route match service to return a NULL route object.
    $current_route_match = $this->prophesize(CurrentRouteMatch::class);
    $current_route_match->getRouteObject()->willReturn(NULL);

    $container = \Drupal::getContainer();
    $container->set('current_route_match', $current_route_match->reveal());
    \Drupal::setContainer($container);

    // Create a test entity, block, & account for running access checks.
    $entity = EntityTestMulRevPub::create();
    $entity->save();
    $block = $this->addInlineBlockToOverrideLayout($entity);
    $account = $this->createUser([
      'create and edit custom blocks',
      'view test entity',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);

    // The access check that is ran here doesn't really matter; we're just
    // looking to confirm that no adverse effects result from a NULL route
    // object when checking block access.
    //
    // When confirming this, we want to ensure that the NULL route object is
    // retrieved and a failure doesn't occur as a result of running the check.
    $current_route_match->getRouteObject()->shouldNotHaveBeenCalled();
    $block->access('view', $account);
    $current_route_match->getRouteObject()->shouldHaveBeenCalled();
  }

  /**
   * Test inline block dependencies with a default revision entity host.
   */
  public function testInlineBlockDependencyDefaultRevision() {
    $entity = EntityTestMulRevPub::create();
    $entity->save();
    $block = $this->addInlineBlockToOverrideLayout($entity);
    $account = $this->createUser([
      'create and edit custom blocks',
      'view test entity',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);
    $this->assertTrue($block->access('view', $account));
    $this->assertTrue($block->access('update', $account));
    $this->assertTrue($block->access('delete', $account));
  }

  /**
   * Test inline block dependencies with a non-default revision entity host.
   */
  public function testInlineBlockDependencyNonDefaultActiveRevision() {
    // Create the canonical revision.
    $entity = EntityTestMulRevPub::create(['moderation_state' => 'published']);
    $entity->save();

    // Create and add a custom block to a new active revision.
    $entity->moderation_state = 'draft';
    $block = $this->addInlineBlockToOverrideLayout($entity);

    $account = $this->createUser([
      'create and edit custom blocks',
      'view test entity',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);
    // The block does not exist on the canonical revision, so access will not be
    // granted since the custom block will not have a resolved dependency via
    // the canonical revision. Some components may choose to manually set a
    // different revision as the block dependent when displaying a non-canonical
    // revision of the entity, such as the content moderation latest-version
    // route. @see
    // \Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray::onBuildRender.
    $this->assertFalse($block->access('view', $account));
    // Access to update the block is resolved and granted via the 'active'
    // revision of the entity. Update access on the content block itself must be
    // granted so that access checks outside of the layout builder routes are
    // correctly granted.
    $this->assertTrue($block->access('update', $account));
    $this->assertTrue($block->access('delete', $account));
  }

  /**
   * Test the inline block dependency when removed from the active revision.
   */
  public function testInlineBlockDependencyRemovedInActiveRevision() {
    // Create the canonical revision with an inline block.
    $entity = EntityTestMulRevPub::create(['moderation_state' => 'published']);
    $entity->save();
    $block = $this->addInlineBlockToOverrideLayout($entity);

    // Create an active revision that removes the inline block.
    $entity->{OverridesSectionStorage::FIELD_NAME} = [];
    $entity->moderation_state = 'draft';
    $entity->save();

    $account = $this->createUser([
      'create and edit custom blocks',
      'view test entity',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);
    // Access to update the block will be resolved through the active revision
    // and denied, since the block has been removed from the layout.
    $this->assertFalse($block->access('update', $account));
    $this->assertFalse($block->access('delete', $account));
    // Access to view the block will be resolved through the canonical revision
    // and granted, since the block still exists on the canonical revision.
    $this->assertTrue($block->access('view', $account));
  }

  /**
   * Add an inline block to an override layout of an entity.
   *
   * @param \Drupal\entity_test\Entity\EntityTestMulRevPub $entity
   *   The entity to add an inline block to.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   The loaded block content revision attached to the layout.
   */
  protected function addInlineBlockToOverrideLayout(EntityTestMulRevPub $entity) {
    $block = BlockContent::create([
      'type' => 'basic',
      'reusable' => FALSE,
    ]);
    $section_data = new Section('layout_onecol', [], [
      'first-uuid' => new SectionComponent('first-uuid', 'content', [
        'id' => sprintf('inline_block:basic'),
        'block_serialized' => serialize($block),
      ]),
    ]);
    $entity->{OverridesSectionStorage::FIELD_NAME} = $section_data;
    $entity->save();
    $inline_block_revision_id = $entity->{OverridesSectionStorage::FIELD_NAME}->getSections()[0]->getComponent('first-uuid')->getPlugin()->getConfiguration()['block_revision_id'];
    return $this->container->get('entity_type.manager')->getStorage('block_content')->loadRevision($inline_block_revision_id);
  }

}
