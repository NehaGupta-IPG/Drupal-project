<?php

namespace Drupal\block_content;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\Importer\MissingContentEvent;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines an event subscriber that listens for missing block content entities.
 */
class MissingBlockContentEntitySubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface
   */
  protected $blockPluginManager;

  /**
   * Static cache of the missing content entities.
   *
   * @var array
   */
  protected $cache;

  /**
   * Constructs a new MissingBlockContentEntitySubscriber object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface $blockPluginManager
   *   Block plugin manager.
   */
  public function __construct(StateInterface $state, CachedDiscoveryInterface $blockPluginManager) {
    $this->state = $state;
    $this->blockPluginManager = $blockPluginManager;
  }

  /**
   * Handles the missing content event.
   *
   * @param \Drupal\Core\Config\Importer\MissingContentEvent $event
   *   The missing content event.
   *
   * @see \Drupal\block_content\Entity\BlockContent::postSave
   * @see \Drupal\block_content\Plugin\Derivative\BlockContent::getDerivativeDefinitions
   * @see \Drupal\block_content\Plugin\Block\BlockContentBlock::build
   */
  public function onMissingContent(MissingContentEvent $event) {
    $missingBlockContentEntities = $this->getAll() + array_filter($event->getMissingContent(), function (array $item) {
      return $item['entity_type'] === 'block_content';
    });
    $this->set($missingBlockContentEntities);
    foreach (array_keys($missingBlockContentEntities) as $uuid) {
      $event->resolveMissingContent($uuid);
    }
    $this->blockPluginManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::IMPORT_MISSING_CONTENT][] = ['onMissingContent'];
    return $events;
  }

  /**
   * Gets missing block content entities.
   *
   * @return array
   *   Array of missing block content information keyed by UUID where each item
   *   in the array has keys 'uuid' and 'bundle'.
   */
  public function getMissing() {
    return $this->getAll();
  }

  /**
   * Removes a missing block content entity.
   *
   * @param string $uuid
   *   UUID of missing entity that has since been recreated and can be removed.
   */
  public function remove($uuid) {
    $missing = $this->getAll();
    unset($missing[$uuid]);
    $this->set($missing);
  }

  /**
   * Checks if UUID belongs to a missing block content entity.
   *
   * @param string $uuid
   *   UUID to check against missing block content entity.
   *
   * @return array|false
   *   An array with keys 'uuid' and 'bundle' if the UUID passed corresponds to
   *   a missing block content entity or FALSE if the UUID does not correspond
   *   to a missing block content entity.
   */
  public function isMissing($uuid) {
    $missing = $this->getAll();
    return isset($missing[$uuid]) ? $missing[$uuid] : FALSE;
  }

  /**
   * Load missing entities.
   *
   * @return array
   *   Missing entities.
   */
  protected function getAll() {
    return $this->state->get('block_content_missing_entities', []);
  }

  /**
   * Sets missing content entities.
   *
   * @param array $missing
   *   Missing content entities.
   *
   * @return $this
   */
  protected function set(array $missing) {
    $this->state->set('block_content_missing_entities', $missing);
    return $this;
  }

}
