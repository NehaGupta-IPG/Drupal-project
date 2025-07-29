<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\EventDispatcher;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Unit tests for the ContainerAwareEventDispatcher.
 *
 * NOTE: Most of this code is a literal copy of Symfony 7.3's
 * Symfony\Component\EventDispatcher\Tests\AbstractEventDispatcherTest.
 *
 * This file does NOT follow Drupal coding standards, so as to simplify future
 * synchronizations.
 */
#[Group('EventDispatcher')]
class ContainerAwareEventDispatcherTest extends TestCase {
  /* Some pseudo events */
  private const PRE_FOO = 'pre.foo';
  private const POST_FOO = 'post.foo';
  private const PRE_BAR = 'pre.bar';

  /**
   * Dispatcher.
   *
   * @var Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  private ContainerAwareEventDispatcher $dispatcher;
  /**
   * Listener.
   *
   * @var TestEventListener
   */
  private TestEventListener $listener;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->dispatcher = $this->createEventDispatcher();
    $this->listener = new TestEventListener();
  }

  /**
   * Create event dispatcher.
   *
   * @return Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   *   Container aware event dispatcher.
   */
  protected function createEventDispatcher(): ContainerAwareEventDispatcher {
    $container = new Container();

    return new ContainerAwareEventDispatcher($container);
  }

  public function testInitialState(): void {
    $this->assertEquals([], $this->dispatcher->getListeners());
    $this->assertFALSE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertFALSE($this->dispatcher->hasListeners(self::POST_FOO));
  }

  public function testAddListener(): void {
    $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
    $this->dispatcher->addListener('post.foo', $this->listener->postFoo(...));
    $this->assertTRUE($this->dispatcher->hasListeners());
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertTRUE($this->dispatcher->hasListeners(self::POST_FOO));
    $this->assertCount(1, $this->dispatcher->getListeners(self::PRE_FOO));
    $this->assertCount(1, $this->dispatcher->getListeners(self::POST_FOO));
    $this->assertCount(2, $this->dispatcher->getListeners());
  }

  public function testGetListenersSortsByPriority(): void {
    $listener1 = new TestEventListener();
    $listener2 = new TestEventListener();
    $listener3 = new TestEventListener();
    $listener4 = new TestEventListener();
    $listener1->name = '1';
    $listener2->name = '2';
    $listener3->name = '3';
    $listener4->name = '4';

    $this->dispatcher->addListener('pre.foo', [$listener1, 'preFoo'], -10);
    $this->dispatcher->addListener('pre.foo', [$listener2, 'preFoo'], 10);
    $this->dispatcher->addListener('pre.foo', [$listener3, 'preFoo']);
    $this->dispatcher->addListener('pre.foo', $listener4->preFoo(...), 20);

    $expected = [
      $listener4->preFoo(...),
      [$listener2, 'preFoo'],
      [$listener3, 'preFoo'],
      [$listener1, 'preFoo'],
    ];

    $this->assertEquals($expected, $this->dispatcher->getListeners('pre.foo'));
  }

  public function testGetAllListenersSortsByPriority(): void {
    $listener1 = new TestEventListener();
    $listener2 = new TestEventListener();
    $listener3 = new TestEventListener();
    $listener4 = new TestEventListener();
    $listener5 = new TestEventListener();
    $listener6 = new TestEventListener();

    $this->dispatcher->addListener('pre.foo', $listener1, -10);
    $this->dispatcher->addListener('pre.foo', $listener2);
    $this->dispatcher->addListener('pre.foo', $listener3, 10);
    $this->dispatcher->addListener('post.foo', $listener4, -10);
    $this->dispatcher->addListener('post.foo', $listener5);
    $this->dispatcher->addListener('post.foo', $listener6, 10);

    $expected = [
      'pre.foo' => [$listener3, $listener2, $listener1],
      'post.foo' => [$listener6, $listener5, $listener4],
    ];

    $this->assertSame($expected, $this->dispatcher->getListeners());
  }

  public function testGetListenerPriority(): void {
    $listener1 = new TestEventListener();
    $listener2 = new TestEventListener();

    $this->dispatcher->addListener('pre.foo', $listener1, -10);
    $this->dispatcher->addListener('pre.foo', $listener2);

    $this->assertSame(-10, $this->dispatcher->getListenerPriority('pre.foo', $listener1));
    $this->assertSame(0, $this->dispatcher->getListenerPriority('pre.foo', $listener2));
    $this->assertNULL($this->dispatcher->getListenerPriority('pre.bar', $listener2));
    $this->assertNULL($this->dispatcher->getListenerPriority('pre.foo', function () {}));
  }

  public function testDispatch(): void {
    $this->dispatcher->addListener('pre.foo', [$this->listener, 'preFoo']);
    $this->dispatcher->addListener('post.foo', $this->listener->postFoo(...));
    $this->dispatcher->dispatch(new Event(), self::PRE_FOO);
    $this->assertTRUE($this->listener->preFooInvoked);
    $this->assertFALSE($this->listener->postFooInvoked);
    $this->assertInstanceOf(Event::class, $this->dispatcher->dispatch(new Event(), 'noEvent'));
    $this->assertInstanceOf(Event::class, $this->dispatcher->dispatch(new Event(), self::PRE_FOO));
    $event = new Event();
    $return = $this->dispatcher->dispatch($event, self::PRE_FOO);
    $this->assertSame($event, $return);
  }

  public function testDispatchForClosure(): void {
    $invoked = 0;
    $listener = function () use (&$invoked) {
      ++$invoked;
    };
    $this->dispatcher->addListener('pre.foo', $listener);
    $this->dispatcher->addListener('post.foo', $listener);
    $this->dispatcher->dispatch(new Event(), self::PRE_FOO);
    $this->assertEquals(1, $invoked);
  }

  public function testStopEventPropagation(): void {
    $otherListener = new TestEventListener();

    // postFoo() stops the propagation, so only one listener should
    // be executed
    // Manually set priority to enforce $this->listener to be called first
    $this->dispatcher->addListener('post.foo', [$this->listener, 'postFoo'], 10);
    $this->dispatcher->addListener('post.foo', $otherListener->postFoo(...));
    $this->dispatcher->dispatch(new Event(), self::POST_FOO);
    $this->assertTRUE($this->listener->postFooInvoked);
    $this->assertFALSE($otherListener->postFooInvoked);
  }

  public function testDispatchByPriority(): void {
    $invoked = [];
    $listener1 = function () use (&$invoked) {
      $invoked[] = '1';
    };
    $listener2 = function () use (&$invoked) {
      $invoked[] = '2';
    };
    $listener3 = function () use (&$invoked) {
      $invoked[] = '3';
    };
    $this->dispatcher->addListener('pre.foo', $listener1, -10);
    $this->dispatcher->addListener('pre.foo', $listener2);
    $this->dispatcher->addListener('pre.foo', $listener3, 10);
    $this->dispatcher->dispatch(new Event(), self::PRE_FOO);
    $this->assertEquals(['3', '2', '1'], $invoked);
  }

  public function testRemoveListener(): void {
    $this->dispatcher->addListener('pre.bar', $this->listener);
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_BAR));
    $this->dispatcher->removeListener('pre.bar', $this->listener);
    $this->assertFALSE($this->dispatcher->hasListeners(self::PRE_BAR));
    $this->dispatcher->removeListener('notExists', $this->listener);
  }

  public function testAddSubscriber(): void {
    $eventSubscriber = new TestEventSubscriber();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertTRUE($this->dispatcher->hasListeners(self::POST_FOO));
  }

  public function testAddSubscriberWithPriorities(): void {
    $eventSubscriber = new TestEventSubscriber();
    $this->dispatcher->addSubscriber($eventSubscriber);

    $eventSubscriber = new TestEventSubscriberWithPriorities();
    $this->dispatcher->addSubscriber($eventSubscriber);

    $listeners = $this->dispatcher->getListeners('pre.foo');
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertCount(2, $listeners);
    $this->assertInstanceOf(TestEventSubscriberWithPriorities::class, $listeners[0][0]);
  }

  public function testAddSubscriberWithMultipleListeners(): void {
    $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
    $this->dispatcher->addSubscriber($eventSubscriber);

    $listeners = $this->dispatcher->getListeners('pre.foo');
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertCount(2, $listeners);
    $this->assertEquals('preFoo2', $listeners[0][1]);
  }

  public function testRemoveSubscriber(): void {
    $eventSubscriber = new TestEventSubscriber();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertTRUE($this->dispatcher->hasListeners(self::POST_FOO));
    $this->dispatcher->removeSubscriber($eventSubscriber);
    $this->assertFALSE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertFALSE($this->dispatcher->hasListeners(self::POST_FOO));
  }

  public function testRemoveSubscriberWithPriorities(): void {
    $eventSubscriber = new TestEventSubscriberWithPriorities();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->dispatcher->removeSubscriber($eventSubscriber);
    $this->assertFALSE($this->dispatcher->hasListeners(self::PRE_FOO));
  }

  public function testRemoveSubscriberWithMultipleListeners(): void {
    $eventSubscriber = new TestEventSubscriberWithMultipleListeners();
    $this->dispatcher->addSubscriber($eventSubscriber);
    $this->assertTRUE($this->dispatcher->hasListeners(self::PRE_FOO));
    $this->assertCount(2, $this->dispatcher->getListeners(self::PRE_FOO));
    $this->dispatcher->removeSubscriber($eventSubscriber);
    $this->assertFALSE($this->dispatcher->hasListeners(self::PRE_FOO));
  }

  public function testEventReceivesTheDispatcherInstanceAsArgument(): void {
    $listener = new TestWithDispatcher();
    $this->dispatcher->addListener('test', [$listener, 'foo']);
    $this->assertNULL($listener->name);
    $this->assertNULL($listener->dispatcher);
    $this->dispatcher->dispatch(new Event(), 'test');
    $this->assertEquals('test', $listener->name);
    $this->assertSame($this->dispatcher, $listener->dispatcher);
  }

  /**
   * @see https://bugs.php.net/62976
   *
   * This bug affects:
   *  - The PHP 5.3 branch for versions < 5.3.18
   *  - The PHP 5.4 branch for versions < 5.4.8
   *  - The PHP 5.5 branch is not affected
   */
  public function testWorkaroundForPhpBug62976(): void {
    $dispatcher = $this->createEventDispatcher();
    $dispatcher->addListener('bug.62976', new CallableClass());
    $dispatcher->removeListener('bug.62976', function () {});
    $this->assertTRUE($dispatcher->hasListeners('bug.62976'));
  }

  public function testHasListenersWhenAddedCallbackListenerIsRemoved(): void {
    $listener = function () {};
    $this->dispatcher->addListener('foo', $listener);
    $this->dispatcher->removeListener('foo', $listener);
    $this->assertFALSE($this->dispatcher->hasListeners());
  }

  public function testGetListenersWhenAddedCallbackListenerIsRemoved(): void {
    $listener = function () {};
    $this->dispatcher->addListener('foo', $listener);
    $this->dispatcher->removeListener('foo', $listener);
    $this->assertSame([], $this->dispatcher->getListeners());
  }

  public function testHasListenersWithoutEventsReturnsFALSEAfterHasListenersWithEventHasBeenCalled(): void {
    $this->assertFALSE($this->dispatcher->hasListeners('foo'));
    $this->assertFALSE($this->dispatcher->hasListeners());
  }

  public function testHasListenersIsLazy(): void {
    $called = 0;
    $listener = [
      function () use (&$called) {
        ++$called;
      },
      'onFoo',
    ];
    $this->dispatcher->addListener('foo', $listener);
    $this->assertTRUE($this->dispatcher->hasListeners());
    $this->assertTRUE($this->dispatcher->hasListeners('foo'));
    $this->assertSame(0, $called);
  }

  public function testDispatchLazyListener(): void {
    $dispatcher = new TestWithDispatcher();
    $called = 0;
    $factory = function () use (&$called, $dispatcher) {
      ++$called;

      return $dispatcher;
    };
    $this->dispatcher->addListener('foo', [$factory, 'foo']);
    $this->assertSame(0, $called);
    $this->dispatcher->dispatch(new Event(), 'foo');
    $this->assertFALSE($dispatcher->invoked);
    $this->dispatcher->dispatch(new Event(), 'foo');
    $this->assertSame(1, $called);

    $this->dispatcher->addListener('bar', [$factory]);
    $this->assertSame(1, $called);
    $this->dispatcher->dispatch(new Event(), 'bar');
    $this->assertTRUE($dispatcher->invoked);
    $this->dispatcher->dispatch(new Event(), 'bar');
    $this->assertSame(2, $called);
  }

  public function testRemoveFindsLazyListeners(): void {
    $test = new TestWithDispatcher();
    $factory = fn () => $test;

    $this->dispatcher->addListener('foo', [$factory, 'foo']);
    $this->assertTRUE($this->dispatcher->hasListeners('foo'));
    $this->dispatcher->removeListener('foo', [$test, 'foo']);
    $this->assertFALSE($this->dispatcher->hasListeners('foo'));

    $this->dispatcher->addListener('foo', [$test, 'foo']);
    $this->assertTRUE($this->dispatcher->hasListeners('foo'));
    $this->dispatcher->removeListener('foo', [$factory, 'foo']);
    $this->assertFALSE($this->dispatcher->hasListeners('foo'));
  }

  public function testPriorityFindsLazyListeners(): void {
    $test = new TestWithDispatcher();
    $factory = fn () => $test;

    $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
    $this->assertSame(3, $this->dispatcher->getListenerPriority('foo', [$test, 'foo']));
    $this->dispatcher->removeListener('foo', [$factory, 'foo']);

    $this->dispatcher->addListener('foo', [$test, 'foo'], 5);
    $this->assertSame(5, $this->dispatcher->getListenerPriority('foo', [$factory, 'foo']));
  }

  public function testGetLazyListeners(): void {
    $test = new TestWithDispatcher();
    $factory = fn () => $test;

    $this->dispatcher->addListener('foo', [$factory, 'foo'], 3);
    $this->assertSame([[$test, 'foo']], $this->dispatcher->getListeners('foo'));

    $this->dispatcher->removeListener('foo', [$test, 'foo']);
    $this->dispatcher->addListener('bar', [$factory, 'foo'], 3);
    $this->assertSame(['bar' => [[$test, 'foo']]], $this->dispatcher->getListeners());
  }

  public function testMutatingWhilePropagationIsStopped(): void {
    $testLoaded = FALSE;
    $test = new TestEventListener();
    $this->dispatcher->addListener('foo', $test->postFoo(...));
    $this->dispatcher->addListener('foo', [
      function () use ($test, &$testLoaded) {
        $testLoaded = TRUE;

        return $test;
      },
      'preFoo',
    ]);

    $this->dispatcher->dispatch(new Event(), 'foo');

    $this->assertTRUE($test->postFooInvoked);
    $this->assertFALSE($test->preFooInvoked);

    $this->assertEquals(0, $this->dispatcher->getListenerPriority('foo', $test->postFoo(...)));

    $test->preFoo(new Event());
    $this->dispatcher->dispatch(new Event(), 'foo');

    $this->assertTRUE($testLoaded);
  }

  /**
   * Test named closures.
   */
  public function testNamedClosures(): void {
    $listener = new TestEventListener();

    $callback1 = $listener(...);
    $callback2 = $listener(...);
    $callback3 = (new TestEventListener())(...);

    $this->assertNotSame($callback1, $callback2);
    $this->assertNotSame($callback1, $callback3);
    $this->assertNotSame($callback2, $callback3);
    $this->assertEquals($callback1, $callback2);
    $this->assertEquals($callback1, $callback3);

    $this->dispatcher->addListener('foo', $callback1, 3);
    $this->dispatcher->addListener('foo', $callback2, 2);
    $this->dispatcher->addListener('foo', $callback3, 1);

    $this->assertSame(3, $this->dispatcher->getListenerPriority('foo', $callback1));
    $this->assertSame(3, $this->dispatcher->getListenerPriority('foo', $callback2));

    $this->dispatcher->removeListener('foo', $callback1);

    $this->assertSame(['foo' => [$callback3]], $this->dispatcher->getListeners());
  }

}

/**
 * Callable.
 */
class CallableClass {

  public function __invoke(): void {
  }

}

/**
 * Test event listeners.
 */
class TestEventListener {
  /**
   * Name.
   *
   * @var string
   */
  public string $name;
  /**
   * PreInvoked.
   *
   * @var bool
   */
  public bool $preFooInvoked = FALSE;
  /**
   * PostInvoked.
   *
   * @var bool
   */
  public bool $postFooInvoked = FALSE;

  /**
   * Listener methods.
   */
  public function preFoo($e): void {
    $this->preFooInvoked = TRUE;
  }

  /**
   * Listener methods.
   */
  public function postFoo($e): void {
    $this->postFooInvoked = TRUE;

    if (!$this->preFooInvoked) {
      $e->stopPropagation();
    }
  }

  /**
   * Listener methods.
   */
  public function __invoke(): void {
  }

}

/**
 * Test event subscriber with dispatcher.
 */
class TestWithDispatcher {
  /**
   * Name.
   *
   * @var string
   */
  public ?string $name = NULL;
  /**
   * Dispatcher.
   *
   * @var Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  public ?ContainerAwareEventDispatcher $dispatcher = NULL;
  /**
   * Invoked.
   *
   * @var bool
   */
  public bool $invoked = FALSE;

  public function foo($e, $name, $dispatcher): void {
    $this->name = $name;
    $this->dispatcher = $dispatcher;
  }

  public function __invoke($e, $name, $dispatcher): void {
    $this->name = $name;
    $this->dispatcher = $dispatcher;
    $this->invoked = TRUE;
  }

}

/**
 * Test event subscriber.
 */
class TestEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return ['pre.foo' => 'preFoo', 'post.foo' => 'postFoo'];
  }

}

/**
 * Test event subscriber with priorities.
 */
class TestEventSubscriberWithPriorities implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'pre.foo' => ['preFoo', 10],
      'post.foo' => ['postFoo'],
    ];
  }

}

/**
 * Test event subscriber with multiple listeners.
 */
class TestEventSubscriberWithMultipleListeners implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'pre.foo' => [
        ['preFoo1'],
        ['preFoo2', 10],
      ],
    ];
  }

}
