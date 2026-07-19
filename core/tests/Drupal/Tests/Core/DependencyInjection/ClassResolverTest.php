<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\Core\DependencyInjection\Fixture\BazClass;
use Drupal\Tests\Core\DependencyInjection\Fixture\StubHookClass;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests \Drupal\Core\DependencyInjection\ClassResolver.
 */
#[CoversClass(ClassResolver::class)]
#[Group('DependencyInjection')]
class ClassResolverTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Tests that a service ID is resolved from the container.
   */
  public function testGetInstanceFromDefinitionService(): void {
    $service = new BazClass();
    $container = new ContainerBuilder();
    $container->set('test.service', $service);

    $resolver = new ClassResolver($container);
    $this->assertSame($service, $resolver->getInstanceFromDefinition('test.service'));
  }

  /**
   * Tests that a class name not in the container is instantiated directly.
   */
  public function testGetInstanceFromDefinitionClass(): void {
    $resolver = new ClassResolver(new ContainerBuilder());
    $this->assertInstanceOf(BazClass::class, $resolver->getInstanceFromDefinition(BazClass::class));
  }

  /**
   * Tests that an unknown definition which is not a class throws an exception.
   */
  public function testGetInstanceFromDefinitionMissingClass(): void {
    $resolver = new ClassResolver(new ContainerBuilder());

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Class "not_a_service_or_class" does not exist.');
    $resolver->getInstanceFromDefinition('not_a_service_or_class');
  }

  /**
   * Tests resolving a service depending on an unset synthetic kernel service.
   *
   * Installing a module or a theme rebuilds the container mid-request. The
   * resolver can be left holding a container builder on which the synthetic
   * 'kernel' service has never been set, so resolving any service that
   * references the kernel - such as an object-oriented hook implementation of
   * a theme - fails with "You have requested a synthetic service".
   *
   * @see https://www.drupal.org/project/drupal/issues/3606822
   */
  public function testGetInstanceFromDefinitionWithUnsetSyntheticKernel(): void {
    // The live container has the kernel set, as it always does at runtime.
    $kernel = new BazClass();
    $live_container = new ContainerBuilder();
    $live_container->set('kernel', $kernel);
    \Drupal::setContainer($live_container);

    // The resolver's container defines the service and the synthetic kernel it
    // depends on, but the kernel has never been set on it.
    $stale_container = new ContainerBuilder();
    $stale_container->register('kernel')->setSynthetic(TRUE);
    $stale_container->register('test.hook', StubHookClass::class)
      ->addArgument(new Reference('kernel'));
    $this->assertFalse($stale_container->initialized('kernel'));

    $resolver = new ClassResolver($stale_container);
    $instance = $resolver->getInstanceFromDefinition('test.hook');

    $this->assertInstanceOf(StubHookClass::class, $instance);
    $this->assertSame($kernel, $instance->getDependency());
  }

  /**
   * Tests resolving a class name service when the resolver's container is stale.
   *
   * When a container rebuild happens mid-request the resolver can keep a
   * reference to the container that predates the rebuild. A service defined
   * only in the new container - such as an object-oriented hook implementation
   * class of a just-installed module, which is registered under its own class
   * name - is then not found, and the resolver falls through to instantiating
   * the class directly. That is fatal for any class with required constructor
   * arguments.
   *
   * @see https://www.drupal.org/project/drupal/issues/3606822
   */
  public function testGetInstanceFromDefinitionFallsBackToLiveContainer(): void {
    // The live container knows about the newly installed module's hook class.
    $dependency = new BazClass();
    $live_container = new ContainerBuilder();
    $live_container->set('test.dependency', $dependency);
    $live_container->register(StubHookClass::class, StubHookClass::class)
      ->addArgument(new Reference('test.dependency'));
    \Drupal::setContainer($live_container);

    // The resolver still holds the container from before the rebuild, which
    // does not know about the hook class at all.
    $stale_container = new ContainerBuilder();
    $this->assertFalse($stale_container->has(StubHookClass::class));

    $resolver = new ClassResolver($stale_container);
    $instance = $resolver->getInstanceFromDefinition(StubHookClass::class);

    $this->assertInstanceOf(StubHookClass::class, $instance);
    $this->assertSame($dependency, $instance->getDependency());
  }

}
