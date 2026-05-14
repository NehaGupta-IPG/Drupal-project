<?php

namespace Drupal\node\Routing;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // As nodes are the primary type of content, the node listing should be
    // easily available. In order to do that, override admin/content to show
    // a node listing instead of the path's child links.
    $route = $collection->get('system.admin_content');
    if ($route) {
      $route->setDefaults([
        '_title' => 'Content',
        '_entity_list' => 'node',
      ]);
      $route->setRequirements([
        '_permission' => 'access content overview',
      ]);
    }

    // @todo Move this to a subscriber in the Drupal\Core\Routing namespace
    // https://www.drupal.org/project/drupal/issues/3484255
    // Opt out of display for node-types without a page display.
    $route = $collection->get('entity.node.canonical');
    $hidden_displays = $this->entityTypeManager->getStorage('entity_view_display')->getQuery()
      ->accessCheck(FALSE)
      ->condition('targetEntityType', 'node')
      ->condition('mode', 'full')
      ->condition('pageDisplay', FALSE)
      ->execute();

    if (\count($hidden_displays) === 0) {
      return;
    }

    $node_types = $this->bundleInfo->getBundleInfo('node');
    $valid_node_types = \array_diff(\array_keys($node_types), \array_map(static fn (string $id) => \explode('.', $id)[1], $hidden_displays));
    $parameters = $route->getOption('parameters');
    if (\array_key_exists('node', $parameters)) {
      $parameters['node']['bundle'] = $valid_node_types;
    }
    $route->setOption('parameters', $parameters);
  }

}
