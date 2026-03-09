<?php

declare(strict_types=1);

namespace Drupal\navigation;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Build the menu links for the Content menu.
 *
 * The content menu contains a "Create" section, along with links to other
 * overview pages for different entity types.
 *
 * @internal
 */
final class NavigationContentLinks implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private RouteProviderInterface $routeProvider,
    private EntityTypeManagerInterface $entityTypeManager,
    private MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(RouteProviderInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(MenuLinkManagerInterface::class),
    );
  }

  /**
   * Add links to the Content menu, based on enabled modules.
   *
   * @param array $links
   *   The array of links being altered.
   */
  public function addMenuLinks(array &$links): void {
    // First, add the top-level menu items.
    // @todo Consider turning this into a data object so we can avoid typos in
    // array keys.
    $content_links = [
      'navigation.create' => [
        'route_name' => 'entity.node.add_page',
        'title' => $this->t('Create'),
        'weight' => -10,
      ],
      'navigation.content' => [
        'route_name' => 'system.admin_content',
        'title' => $this->t('Content'),
      ],
      'navigation.files' => [
        'route_name' => 'view.files.page_1',
        'title' => $this->t('Files'),
      ],
      'navigation.media' => [
        'route_name' => 'entity.media.collection',
        'title' => $this->t('Media'),
      ],
      'navigation.blocks' => [
        'route_name' => 'entity.block_content.collection',
        'title' => $this->t('Blocks'),
      ],
    ];

    foreach ($content_links as $link_name => $link) {
      $this->addLink($link_name, $link, $links);
    }

    // Add supported add links under the Create button.
    foreach ($this->getBundleEntityTypes() as $entity_type => $config) {
      $this->addCreateEntityLinks($entity_type, $config['add_route'], $links, $config['bundle_allow_list']);
    }

    // Finally, add the bundleless User link and pin it to the bottom.
    $this->addLink('navigation.create.user', [
      'route_name' => 'user.admin_create',
      'title' => $this->t('User'),
      'parent' => 'navigation.create',
      'weight' => 100,
    ], $links);
  }

  /**
   * Update the menu link title when a bundle entity is renamed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was updated.
   */
  public function updateMenuLinks(EntityInterface $entity): void {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle_types = $this->getBundleEntityTypes();
    if (!isset($bundle_types[$entity_type_id])) {
      return;
    }
    $allow_list = $bundle_types[$entity_type_id]['bundle_allow_list'];
    if (!empty($allow_list) && !in_array($entity->id(), $allow_list, TRUE)) {
      return;
    }
    $link_id = 'navigation.content.' . $entity_type_id . '.' . $entity->id();
    if (!$this->menuLinkManager->hasDefinition($link_id)) {
      return;
    }

    $this->menuLinkManager->rebuild();
  }

  /**
   * Returns the bundle entity types and their configuration.
   *
   * @return array
   *   A map of entity type IDs to their add route and bundle allow list.
   */
  protected function getBundleEntityTypes(): array {
    return [
      'node_type' => [
        'add_route' => 'entity.node.add_form',
        'bundle_allow_list' => [],
      ],
      'media_type' => [
        'add_route' => 'entity.media.add_form',
        'bundle_allow_list' => ['document', 'image'],
      ],
    ];
  }

  /**
   * Add create links for an entity type.
   *
   * This function preserves the order of entity types as it is called.
   *
   * @param string $entity_type
   *   The entity type to add links for, such as node_type.
   * @param string $add_route_id
   *   The ID of the route for the entity type add form.
   * @param array $links
   *   The existing array of links to add to.
   * @param array $bundle_allow_list
   *   A list of allowed bundles to include. Can be used to limit the list of
   *   bundles that are included for noisy entity types like media.
   */
  private function addCreateEntityLinks(string $entity_type, string $add_route_id, array &$links, array $bundle_allow_list = []): void {
    // Ensure subsequent calls always get added to the bottom, and not in
    // alphabetical order.
    static $weight = 0;

    // The module providing the entity type is either not installed, or in the
    // process of being uninstalled.
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      return;
    }

    // Sort all types within an entity type alphabetically.
    $definition = $this->entityTypeManager->getDefinition($entity_type);
    $types = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
    if (method_exists($definition->getClass(), 'sort')) {
      uasort($types, [$definition->getClass(), 'sort']);
    }

    $add_content_links = [];
    foreach ($types as $type) {
      // Skip if the bundle is not in the allow list.
      if (!empty($bundle_allow_list) && !in_array($type->id(), $bundle_allow_list)) {
        continue;
      }
      $add_content_links['navigation.content.' . $type->getEntityTypeId() . '.' . $type->id()] = [
        'title' => $type->label(),
        'route_name' => $add_route_id,
        'route_parameters' => [
          $entity_type => $type->id(),
        ],
        'parent' => 'navigation.create',
        'weight' => $weight,
      ];
    }

    foreach ($add_content_links as $link_name => $link) {
      $this->addLink($link_name, $link, $links);
    }

    $weight++;
  }

  /**
   * Ensure a route exists and add the link.
   *
   * @param string $link_name
   *   The name of the link being added.
   * @param array $link
   *   The link array, as defined in hook_menu_links_discovered_alter().
   * @param array $links
   *   The existing array of links.
   */
  private function addLink(string $link_name, array $link, array &$links): void {
    try {
      // Ensure the route exists (there is no separate "exists" method).
      $this->routeProvider->getRouteByName($link['route_name']);
      $links[$link_name] = $link + ['menu_name' => 'content', 'provider' => 'navigation'];
    }
    catch (RouteNotFoundException) {
      // The module isn't installed, or the route (such as provided by a view)
      // has been deleted.
    }
  }

}
