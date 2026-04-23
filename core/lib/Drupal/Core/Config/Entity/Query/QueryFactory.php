<?php

namespace Drupal\Core\Config\Entity\Query;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a factory for creating entity query objects for the config backend.
 */
class QueryFactory implements QueryFactoryInterface {

  /**
   * The namespace of this class, the parent class etc.
   *
   * @var array
   */
  protected $namespaces;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'config.storage.active')]
    protected StorageInterface|KeyValueFactoryInterface $activeStorage,
    #[Autowire(service: 'database')]
    protected Connection|ConfigManagerInterface $connection,
  ) {
    if (!$activeStorage instanceof StorageInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $activeStorage argument is deprecated in drupal:11.4.0 and will be required in drupal:12.0.0. See https://www.drupal.org/node/3578326', E_USER_DEPRECATED);
      $this->activeStorage = \Drupal::service('config.storage.active');
    }
    if (!$connection instanceof Connection) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $connection argument is deprecated in drupal:11.4.0 and will be required in drupal:12.0.0. See https://www.drupal.org/node/3578326', E_USER_DEPRECATED);
      $this->connection = \Drupal::service('database');
    }

    $this->namespaces = QueryBase::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    if ($this->activeStorage instanceof DatabaseStorage) {
      return new SqlQuery($entity_type, $conjunction, $this->configFactory, $this->connection, $this->namespaces);
    }
    return new Query($entity_type, $conjunction, $this->configFactory, $this->namespaces);
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    throw new QueryException('Aggregation over configuration entities is not supported');
  }

}
