<?php

namespace Drupal\node\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeStorageInterface;

/**
 * Argument handler to accept a node revision id.
 */
#[ViewsArgument(
  id: 'node_vid',
)]
class Vid extends NumericArgument {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, NodeStorageInterface $node_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('node')
    );
  }

  /**
   * Override the behavior of title(). Get the title of the revision.
   */
  public function titleQuery() {
    $titles = [];

    $results = $this->nodeStorage->getAggregateQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->groupBy('title')
      ->condition('vid', $this->value, 'IN')
      ->execute();

    foreach ($results as $result) {
      $titles[] = $result['title'];
    }

    return $titles;
  }

}
