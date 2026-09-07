<?php

namespace Drupal\Core\Plugin\Context;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Provides a context repository which uses context provider services.
 */
class LazyContextRepository implements ContextRepositoryInterface {

  /**
   * The statically cached contexts.
   *
   * @var \Drupal\Core\Plugin\Context\ContextInterface[]
   */
  protected $contexts = [];

  /**
   * Constructs a LazyContextRepository object.
   *
   * @param \Symfony\Contracts\Service\ServiceProviderInterface $container
   *   A service locator containing the services tagged 'context_provider',
   *   keyed by service ID.
   */
  public function __construct(
    #[AutowireLocator('context_provider')]
    protected ServiceProviderInterface $container,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $context_ids) {
    $contexts = [];

    // Create a map of context providers (service IDs) to unqualified context
    // IDs.
    $context_ids_by_service = [];
    foreach ($context_ids as $id) {
      if (isset($this->contexts[$id])) {
        $contexts[$id] = $this->contexts[$id];
        continue;
      }
      assert($id[0] === '@' && str_contains($id, ':'), 'You must provide the context IDs in the @{service_id}:{unqualified_context_id} format.');
      list($service_id, $unqualified_context_id) = explode(':', $id, 2);
      // Remove the leading '@'.
      $service_id = substr($service_id, 1);
      $context_ids_by_service[$service_id][] = $unqualified_context_id;
    }

    // Iterate over all missing context providers (services), gather the
    // runtime contexts and assign them as requested.
    foreach ($context_ids_by_service as $service_id => $unqualified_context_ids) {
      $contexts_by_service = $this->container->get($service_id)->getRuntimeContexts($unqualified_context_ids);

      $wanted_contexts = array_intersect_key($contexts_by_service, array_flip($unqualified_context_ids));
      foreach ($wanted_contexts as $unqualified_context_id => $context) {
        $context_id = '@' . $service_id . ':' . $unqualified_context_id;
        $this->contexts[$context_id] = $contexts[$context_id] = $context;
      }
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $contexts = [];
    foreach (array_keys($this->container->getProvidedServices()) as $service_id) {
      $contexts_by_service = $this->container->get($service_id)->getAvailableContexts();
      foreach ($contexts_by_service as $unqualified_context_id => $context) {
        $context_id = '@' . $service_id . ':' . $unqualified_context_id;
        $contexts[$context_id] = $context;
      }
    }

    return $contexts;
  }

}
