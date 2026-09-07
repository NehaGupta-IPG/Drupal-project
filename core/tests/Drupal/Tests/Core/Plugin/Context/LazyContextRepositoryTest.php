<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Plugin\Context;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\LazyContextRepository;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Tests Drupal\Core\Plugin\Context\LazyContextRepository.
 */
#[CoversClass(LazyContextRepository::class)]
#[Group('context')]
class LazyContextRepositoryTest extends UnitTestCase {

  /**
   * The context provider services, keyed by service ID.
   *
   * @var \Drupal\Core\Plugin\Context\ContextProviderInterface[]
   */
  protected array $contextProviders = [];

  /**
   * Tests get runtime contexts single.
   */
  public function testGetRuntimeContextsSingle(): void {
    $contexts = $this->setupContextAndProvider('test_provider', ['test_context']);

    $lazy_context_repository = $this->getContextRepository();
    $run_time_contexts = $lazy_context_repository->getRuntimeContexts(['@test_provider:test_context']);
    $this->assertEquals(['@test_provider:test_context' => $contexts[0]], $run_time_contexts);
  }

  /**
   * Tests get runtime multiple contexts per service.
   *
   * @legacy-covers ::getRuntimeContexts
   */
  public function testGetRuntimeMultipleContextsPerService(): void {
    $contexts = $this->setupContextAndProvider('test_provider', ['test_context0', 'test_context1']);

    $lazy_context_repository = $this->getContextRepository();
    $run_time_contexts = $lazy_context_repository->getRuntimeContexts([
      '@test_provider:test_context0',
      '@test_provider:test_context1',
    ]);
    $this->assertEquals(['@test_provider:test_context0' => $contexts[0], '@test_provider:test_context1' => $contexts[1]], $run_time_contexts);
  }

  /**
   * Tests get runtime multiple context providers.
   *
   * @legacy-covers ::getRuntimeContexts
   */
  public function testGetRuntimeMultipleContextProviders(): void {
    $contexts0 = $this->setupContextAndProvider('test_provider', ['test_context0', 'test_context1'], ['test_context0']);
    $contexts1 = $this->setupContextAndProvider('test_provider2', ['test1_context0', 'test1_context1'], ['test1_context0']);

    $lazy_context_repository = $this->getContextRepository();
    $run_time_contexts = $lazy_context_repository->getRuntimeContexts([
      '@test_provider:test_context0',
      '@test_provider2:test1_context0',
    ]);
    $this->assertEquals([
      '@test_provider:test_context0' => $contexts0[0],
      '@test_provider2:test1_context0' => $contexts1[1],
    ], $run_time_contexts);
  }

  /**
   * Tests invalid context id.
   *
   * @legacy-covers ::getRuntimeContexts
   */
  public function testInvalidContextId(): void {
    $lazy_context_repository = $this->getContextRepository();
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessageIs('You must provide the context IDs in the @{service_id}:{unqualified_context_id} format.');
    $lazy_context_repository->getRuntimeContexts(['test_context', '@test_provider:test_context1']);
  }

  /**
   * Tests get runtime static cache.
   *
   * @legacy-covers ::getRuntimeContexts
   */
  public function testGetRuntimeStaticCache(): void {
    $context0 = new Context(new ContextDefinition('example'));
    $context1 = new Context(new ContextDefinition('example'));

    $context_provider = $this->prophesize(ContextProviderInterface::class);
    $context_provider->getRuntimeContexts(['test_context0', 'test_context1'])
      ->shouldBeCalledTimes(1)
      ->willReturn(['test_context0' => $context0, 'test_context1' => $context1]);
    $this->contextProviders['test_provider'] = $context_provider->reveal();

    $lazy_context_repository = $this->getContextRepository();
    $lazy_context_repository->getRuntimeContexts(['@test_provider:test_context0', '@test_provider:test_context1']);
    $lazy_context_repository->getRuntimeContexts(['@test_provider:test_context0', '@test_provider:test_context1']);
  }

  /**
   * Tests get available contexts.
   */
  public function testGetAvailableContexts(): void {
    $contexts0 = $this->setupContextAndProvider('test_provider0', ['test0_context0', 'test0_context1']);
    $contexts1 = $this->setupContextAndProvider('test_provider1', ['test1_context0', 'test1_context1']);

    $lazy_context_repository = $this->getContextRepository();
    $contexts = $lazy_context_repository->getAvailableContexts();

    $this->assertEquals([
      '@test_provider0:test0_context0' => $contexts0[0],
      '@test_provider0:test0_context1' => $contexts0[1],
      '@test_provider1:test1_context0' => $contexts1[0],
      '@test_provider1:test1_context1' => $contexts1[1],
    ], $contexts);

  }

  /**
   * Returns a context repository for the configured context providers.
   *
   * @return \Drupal\Core\Plugin\Context\LazyContextRepository
   *   The context repository.
   */
  protected function getContextRepository(): LazyContextRepository {
    $factories = [];
    foreach ($this->contextProviders as $service_id => $context_provider) {
      $factories[$service_id] = fn() => $context_provider;
    }
    return new LazyContextRepository(new ServiceLocator($factories));
  }

  /**
   * Sets up contexts and context providers.
   *
   * @param string $service_id
   *   The service ID of the service provider.
   * @param string[] $unqualified_context_ids
   *   An array of context slot names.
   * @param string[] $expected_unqualified_context_ids
   *   The expected unqualified context IDs passed to getRuntimeContexts.
   *
   * @return array
   *   An array of set up contexts.
   */
  protected function setupContextAndProvider(string $service_id, array $unqualified_context_ids, array $expected_unqualified_context_ids = []): array {
    $contexts = [];
    for ($i = 0; $i < count($unqualified_context_ids); $i++) {
      $contexts[] = new Context(new ContextDefinition('example'));
    }

    $expected_unqualified_context_ids = $expected_unqualified_context_ids ?: $unqualified_context_ids;

    $context_provider = $this->prophesize(ContextProviderInterface::class);
    $context_provider->getRuntimeContexts($expected_unqualified_context_ids)
      ->willReturn(array_combine($unqualified_context_ids, $contexts));
    $context_provider->getAvailableContexts()
      ->willReturn(array_combine($unqualified_context_ids, $contexts));
    $this->contextProviders[$service_id] = $context_provider->reveal();

    return $contexts;
  }

}
