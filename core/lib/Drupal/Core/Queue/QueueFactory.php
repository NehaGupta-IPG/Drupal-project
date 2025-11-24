<?php

namespace Drupal\Core\Queue;

use Drupal\Core\Site\Settings;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Defines the queue factory.
 */
class QueueFactory {

  /**
   * Instantiated queues, keyed by name.
   *
   * @var array
   */
  protected $queues = [];

  /**
   * The settings object.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructs QueueFactory object.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   * @param \Psr\Container\ContainerInterface $container
   *   A service locator that contains the queue services.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface|null $queueManager
   *   The Queue Worker Manager service.
   */
  public function __construct(
    Settings $settings,
    #[AutowireLocator('queue_factory')]
    protected ContainerInterface $container,
    protected ?QueueWorkerManagerInterface $queueManager = NULL,
  ) {
    $this->settings = $settings;
    if ($this->queueManager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $queueManager argument is deprecated in drupal:10.1.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3338022', E_USER_DEPRECATED);
      $this->queueManager = \Drupal::service('plugin.manager.queue_worker');
    }
  }

  /**
   * Constructs a new queue.
   *
   * @param string $name
   *   The name of the queue to work with.
   * @param bool $reliable
   *   (optional) TRUE if the ordering of items and guaranteeing every item
   *   executes at least once is important, FALSE if scalability is the main
   *   concern. Defaults to FALSE.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   A queue implementation for the given name.
   */
  public function get($name, $reliable = FALSE) {
    if (!isset($this->queues[$name])) {
      $service_name = $this->getServiceName($name, $reliable);
      $factory = $this->container->get($service_name);
      $this->queues[$name] = $factory->get($name);
    }
    return $this->queues[$name];
  }

  /**
   * Get the service name which responsible to handle the queue.
   *
   * @param string $queue_id
   *   Queue Worker ID. The ID of the queue.
   * @param bool $reliable
   *   (optional) TRUE if the ordering of items and guaranteeing every item
   *   executes at least once is important, FALSE if scalability is the main
   *   concern. Defaults to FALSE.
   *
   * @return string
   *   Service name. The 'queue.database' is used by the default.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getServiceName(string $queue_id, bool $reliable = FALSE): string {
    $definition = $this->queueManager->getDefinition($queue_id, FALSE);
    $service_name = NULL;
    if ($reliable && isset($definition['queue_reliable_service'])) {
      $service_name = $definition['queue_reliable_service'];
    }
    elseif (isset($definition['queue_service'])) {
      $service_name = $definition['queue_service'];
    }
    /* @todo: remove $service_name detection legacy logic. See https://www.drupal.org/node/2821989 */
    // If it is a reliable queue, check the specific settings first.
    if ($reliable) {
      $legacy_service_name = $this->settings::get('queue_reliable_service_' . $queue_id);
    }
    // If no reliable queue was defined, check the service and global
    // settings, fall back to queue.database.
    else {
      $legacy_service_name = $this->settings::get('queue_service_' . $queue_id);
    }
    if ($legacy_service_name) {
      $service_name = $legacy_service_name;
    }

    if ($service_name === NULL) {
      $service_name = $this->settings::get('queue_default', 'queue.database');
    }

    return $service_name;
  }

}
