<?php

namespace Drupal\taxonomy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View builder handler for taxonomy terms.
 */
class TaxonomyTermViewBuilder extends EntityViewBuilder {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->database = $container->get('database');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    // Check if the 'views' module is installed or not.
    if ($this->moduleHandler()->moduleExists('views')) {
      return parent::view($entity, $view_mode, $langcode);
    }

    // If the 'views' module is not installed, render the term and nodes that
    // use the term.
    $build = [];
    $build[] = parent::view($entity, $view_mode, $langcode);
    // Get all the nodes that use this term.
    $tid = $entity->id();
    $query = $this->database->select('taxonomy_index', 't')->fields('t', ['nid'])->condition('tid', $tid)->orderBy('sticky', 'desc')->orderBy('created', 'desc');
    $nodes = $query->execute()->fetchCol();

    // Render all the nodes in teaser view mode.
    $view_builder = $this->entityTypeManager->getViewBuilder('node');
    foreach ($nodes as $node) {
      $node_entity = $this->entityTypeManager->getStorage('node')->load($node);
      $build[] = $view_builder->view($node_entity, 'teaser');
    }
    return $build;
  }

}
