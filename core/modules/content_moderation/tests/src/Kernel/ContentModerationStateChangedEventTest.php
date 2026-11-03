<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\content_moderation\Event\ContentModerationStateChangedEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests events are properly fired during create/save of moderation states.
 */
#[Group('content_moderation')]
#[RunTestsInSeparateProcesses]
class ContentModerationStateChangedEventTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
    'content_translation',
    'content_moderation',
    'user',
    'workflows',
    'node',
  ];

  /**
   * A mock event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig('workflows');
    $this->installSchema('node', ['node_access']);
    $this->installConfig('content_moderation');

    NodeType::create([
      'name' => 'Test node',
      'type' => 'example',
    ])->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->save();

    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->container->set('event_dispatcher', $this->eventDispatcher);

    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests events for adding and updating moderation state entities.
   */
  public function testCreateUpdateStates(): void {
    // Record the details of each event at the time it is dispatched.
    $dispatched = [];
    $this->eventDispatcher->expects($this->exactly(5))
      ->method('dispatch')
      ->willReturnCallback(function (object $event, ?string $event_name = NULL) use (&$dispatched): object {
        $this->assertInstanceOf(ContentModerationStateChangedEvent::class, $event);
        $entity = $event->getModeratedEntity();
        $dispatched[] = [
          $event_name,
          $event->getWorkflow(),
          $event->getOriginalState(),
          $event->getNewState(),
          $entity->getEntityTypeId(),
          // The revision ID can be a string or an integer.
          (int) $entity->getRevisionId(),
          $entity->language()->getId(),
        ];
        return $event;
      });

    $node = Node::create([
      'type' => 'example',
      'title' => 'Foo',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node->moderation_state = 'published';
    $node->save();

    $node->moderation_state = 'archived';
    $node->save();

    $french_node = $node->addTranslation('fr');
    $french_node->title = 'French node';
    $french_node->moderation_state = 'published';
    $french_node->save();

    $french_node->moderation_state = 'draft';
    $french_node->save();

    $this->assertSame([
      // Event name, workflow, original state, new state, entity type,
      // revision ID, langcode.
      ['content_moderation.state_changed', 'editorial', NULL, 'draft', 'node', 1, 'en'],
      ['content_moderation.state_changed', 'editorial', 'draft', 'published', 'node', 2, 'en'],
      ['content_moderation.state_changed', 'editorial', 'published', 'archived', 'node', 3, 'en'],
      ['content_moderation.state_changed', 'editorial', NULL, 'published', 'node', 4, 'fr'],
      ['content_moderation.state_changed', 'editorial', 'published', 'draft', 'node', 5, 'fr'],
    ], $dispatched);
  }

}
