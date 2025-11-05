<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\content_moderation\Event\ContentModerationStateChangedEvent;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests events are properly fired during create/save of moderation states.
 *
 * @group content_moderation
 */
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
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject
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
      'title' => 'Test node',
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
    $callSequence = [
      [[FALSE, 'draft', 1, 'en']],
      [['draft', 'published', 2, 'en']],
      [['published', 'archived', 3, 'en']],
      [['archived', 'published', 4, 'fr']],
      [['published', 'draft', 5, 'fr']],
    ];
    $callSequenceObjects = array_map(function ($element) {
      $entity = $this->createMock(EditorialContentEntityBase::class);
      $language = $this->createMock(LanguageInterface::class);
      $language->expects($this->any())
        ->method('getId')
        ->willReturn($element[0][3]);
      $entity->expects($this->any())
        ->method('getEntityTypeId')
        ->willReturn('node');
      $entity->expects($this->any())
        ->method('getRevisionId')
        ->willReturn($element[0][2]);
      $entity->expects($this->any())
        ->method('language')
        ->willReturn($language);
      return [
        [
          // We need to pass a constraint, as they are not the same object,
          // so assertEquals will fail.
          new ContentModerationStateChangedEventAreEqual(
            new ContentModerationStateChangedEvent($entity, $element[0][1], $element[0][0], 'editorial')
          ),
          'content_moderation.state_changed',
        ],
        $this->callback(function (ContentModerationStateChangedEvent $event, $event_name) use (&$args) {
          /** @var ContentModerationStateChangedEvent $expectedEvent */
          $expectedEvent = &$args[0];
          $expectedEventName = &$args[1];

          $this->assertSame($expectedEventName, $event_name);
          $this->assertSame($expectedEvent->getWorkflow, $event->getWorkflow());
          $this->assertSame($expectedEvent->getOriginalState(), $event->getOriginalState());
          $this->assertSame($expectedEvent->getNewState(), $event->getNewState());
          $this->assertSame($expectedEvent->getModeratedEntity()->getEntityTypeId(), $event->getModeratedEntity()->getEntityTypeId());
          $this->assertSame($expectedEvent->getModeratedEntity()->getRevisionId(), $event->getModeratedEntity()->getRevisionId());
          $this->assertSame($expectedEvent->getModeratedEntity()->language()->getId(), $event->getModeratedEntity()->language()->getId());
          return $event;
        }),
      ];
    }, $callSequence);

    $this->eventDispatcher->expects($this->exactly(5))
      ->method('dispatch')
      ->willReturnCallback(function (mixed ...$args) use (&$callSequenceObjects): object {
        return $this->consecutiveCallsCallback($callSequenceObjects, $args);
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
  }

  /**
   * Callback for consecutive calls to mocked objects.
   *
   * Since the removal of InvocationMocker::withConsecutive() from PHPUnit, we
   * can use InvocationMocker::willReturnCallback() instead, passing a stack
   * of expected calls to mocked methods. This method provides a generic
   * callback for the purpose, checking actual arguments against expected ones
   * and returning a value if needed.
   *
   * @param array $expectedConsecutiveCalls
   *   An array of expected arguments and return values for a stack of calls
   *   to the method. The first element of the array contains the expected
   *   call arguments, either concrete values or Constraint objects (could be
   *   a partial list, in which case the actual arguments in excess of the
   *   expected ones are ignored). The second element, if specified, is
   *   returned by the callback when the arguments match.
   * @param array $actualArgs
   *   The array of arguments actually passed to the mocked object.
   *
   * @return mixed
   *   The value specified by the second array item of
   *   $expectedConsecutiveCalls. NULL if not specified.
   */
  protected function consecutiveCallsCallback(array &$expectedConsecutiveCalls, array $actualArgs): mixed {
    // @todo Use MockTrait when https://www.drupal.org/i/3306554 lands.
    // Return early if there are no expected calls.
    if ($expectedConsecutiveCalls === []) {
      return NULL;
    }

    // Get the next expected call from the stack.
    $key = key($expectedConsecutiveCalls);
    $call = reset($expectedConsecutiveCalls);
    unset($expectedConsecutiveCalls[$key]);
    $expectedArgs = $call[0];

    // Check the arguments.
    for ($i = 0; $i < count($expectedArgs); $i++) {
      if ($expectedArgs[$i] instanceof Constraint) {
        try {
          $expectedArgs[$i]->evaluate($actualArgs[$i]);
        }
        catch (AssertionFailedError $e) {
          throw new AssertionFailedError(sprintf('Failed argument match in consecutive call #%d, argument #%d.', $key + 1, $i + 1), $e->getCode(), $e);
        }
      }
      else {
        $this->assertEquals($expectedArgs[$i], $actualArgs[$i], sprintf('Failed argument match in consecutive call #%d, argument #%d.', $key + 1, $i + 1));
      }
    }

    // Returns with the specified value, if defined.
    if (array_key_exists(1, $call)) {
      return $call[1];
    }

    return NULL;
  }

}

/**
 * Constraint for checking if ContentModerationStateChangedEvent are equal.
 */
final class ContentModerationStateChangedEventAreEqual extends Constraint {

  /**
   * Creates a ContentModerationStateChangedEventAreEqual constraint.
   *
   * @param ContentModerationStateChangedEvent $expected
   *   The expected ContentModerationStateChangedEvent.
   */
  public function __construct(protected readonly ContentModerationStateChangedEvent $expected) {}

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return 'are equal';
  }

  /**
   * {@inheritdoc}
   */
  protected function matches($event): bool {
    if (!$event instanceof ContentModerationStateChangedEvent) {
      return FALSE;
    }
    return $event->getOriginalState() === $this->expected->getOriginalState() &&
      $event->getNewState() === $this->expected->getNewState() &&
      $event->getWorkflow() === $this->expected->getWorkflow() &&
      $event->getModeratedEntity()->getEntityTypeId() === $this->expected->getModeratedEntity()->getEntityTypeId() &&
      // Not strict comparison here, as revision id could be a number or a
      // string.
      $event->getModeratedEntity()->getRevisionId() == $this->expected->getModeratedEntity()->getRevisionId() &&
      $event->getModeratedEntity()->language()->getId() === $this->expected->getModeratedEntity()->language()->getId();
  }

  /**
   * {@inheritdoc}
   */
  protected function failureDescription($event): string {
    return sprintf('the event with workflow %s and transition %s => %s for %s with language %s and revision %s is equal to the expected event with workflow %s and transition %s => %s for %s with language %s and revision %s',
      $event->getWorkflow(),
      $event->getOriginalState(),
      $event->getNewState(),
      $event->getModeratedEntity()->getEntityTypeId(),
      $event->getModeratedEntity()->language()->getId(),
      $event->getModeratedEntity()->getRevisionId(),
      $this->expected->getWorkflow(),
      $this->expected->getOriginalState(),
      $this->expected->getNewState(),
      $this->expected->getModeratedEntity()->getEntityTypeId(),
      $this->expected->getModeratedEntity()->language()->getId(),
      $this->expected->getModeratedEntity()->getRevisionId(),
    );
  }

}