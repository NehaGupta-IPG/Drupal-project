<?php

namespace Drupal\Core\Entity\KeyValueStore\Query;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Defines the entity query for entities stored in a key value backend.
 */
class Query extends QueryBase {

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * Constructs a new Query.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, array $namespaces, KeyValueFactoryInterface $key_value_factory) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?RefinableCacheableDependencyInterface $cacheableMetadata = NULL) {
    // Load the relevant records.
    $records = $this->keyValueFactory->get('entity_storage__' . $this->entityTypeId)->getAll();

    // Apply conditions.
    $result = $this->condition->compile($records);

    // Apply sort settings.
    foreach ($this->sort as $sort) {
      $direction = $sort['direction'] == 'ASC' ? -1 : 1;
      $field = $sort['field'];
      uasort($result, function ($a, $b) use ($field, $direction) {
        return ($a[$field] <= $b[$field]) ? $direction : -$direction;
      });
    }

    // Let the pager do its work.
    $this->initializePager();

    if ($this->range) {
      $result = array_slice($result, $this->range['start'], $this->range['length'], TRUE);
    }

    if ($cacheableMetadata === NULL) {
      if (!empty($this->getCacheContexts()) || !empty($this->getCacheTags()) || $this->getCacheMaxAge() !== Cache::PERMANENT) {
        // @todo Add correct CR number.
        @trigger_error('Calling ' . __METHOD__ . '() without the $cacheableMetadata argument when the query has cacheable metadata is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. See https://www.drupal.org/node/1234567', E_USER_DEPRECATED);
      }
    }
    else {
      $cacheableMetadata->addCacheableDependency($this);
    }

    if ($this->count) {
      return count($result);
    }

    // Create the expected structure of entity_id => entity_id.
    $entity_ids = array_keys($result);
    return array_combine($entity_ids, $entity_ids);
  }

}
