<?php

namespace Drupal\system\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\HtaccessWriterInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\system\SecurityAdvisories\SecurityAdvisoriesFetcher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cron hook implementations for system.
 */
class SystemCronHooks {

  public function __construct(
    protected readonly FloodInterface $flood,
    #[Autowire(service: 'keyvalue.expirable.database')]
    protected readonly KeyValueExpirableFactoryInterface $keyValueDatabaseExpirableFactory,
    protected readonly QueueWorkerManagerInterface $queueWorkerManager,
    protected readonly QueueFactory $queueFactory,
    protected readonly HtaccessWriterInterface $htaccessWriter,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly SecurityAdvisoriesFetcher $securityAdvisoriesFetcher,
  ) {}

  /**
   * Implements hook_cron().
   *
   * Remove older rows from flood, batch cache and expirable keyvalue tables.
   * Also ensure files directories have .htaccess files.
   */
  #[Hook('cron')]
  public function cron(): void {
    // Clean up the flood.
    $this->flood->garbageCollection();
    foreach (Cache::getBins() as $cache_backend) {
      $cache_backend->garbageCollection();
    }
    // Clean up the expirable key value database store.
    if ($this->keyValueDatabaseExpirableFactory instanceof KeyValueDatabaseExpirableFactory) {
      $this->keyValueDatabaseExpirableFactory->garbageCollection();
    }
    // Clean up any garbage in the queue service.
    foreach (array_keys($this->queueWorkerManager->getDefinitions()) as $queue_name) {
      $queue = $this->queueFactory->get($queue_name);
      if ($queue instanceof QueueGarbageCollectionInterface) {
        $queue->garbageCollection();
      }
    }
    // Ensure that all of Drupal's standard directories (e.g., the public files
    // directory and config directory) have appropriate .htaccess files.
    $this->htaccessWriter->ensure();
    if ($this->configFactory->get('system.advisories')->get('enabled')) {
      // Fetch the security advisories so that they will be pre-fetched during
      // systemAdvisoriesRequirements() and system_page_top().
      $this->securityAdvisoriesFetcher->getSecurityAdvisories();
    }
  }

}
