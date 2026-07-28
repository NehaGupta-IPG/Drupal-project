<?php

namespace Drupal\menu_ui;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a class to build a listing of menu entities.
 *
 * @see \Drupal\system\Entity\Menu
 * @see menu_entity_info()
 */
class MenuListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected const SORT_KEY = 'label';

  /**
   * The request stack.
   */
  private RequestStack $requestStack;

  /**
   * Administrative menus that should appear on the admin menu tab.
   */
  private const array ADMINISTRATIVE_MENUS = [
    'account',
    'admin',
    'content',
    'navigation-user-links',
    'tools',
    'top-tasks',
  ];

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    $handler = parent::createInstance($container, $entity_type);
    $handler->requestStack = $container->get(RequestStack::class);
    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    $menus = parent::load();

    $admin_menus = array_flip(self::ADMINISTRATIVE_MENUS);
    if ($this->requestStack->getCurrentRequest()->attributes->getBoolean('administrative')) {
      $menus = array_intersect_key($menus, $admin_menus);
    }
    else {
      $menus = array_diff_key($menus, $admin_menus);
    }
    return $menus;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Title');
    $header['description'] = [
      'data' => $this->t('Description'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];
    $row['description']['data'] = ['#markup' => $entity->getDescription()];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity, CacheableMetadata $cacheability) {
    $operations = parent::getDefaultOperations($entity, $cacheability);

    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Edit menu');
      $operations['add'] = [
        'title' => $this->t('Add link'),
        'weight' => 20,
        'url' => $entity->toUrl('add-link-form'),
        'query' => [
          'destination' => $entity->toUrl('edit-form')->toString(),
        ],
      ];
    }
    if (isset($operations['delete'])) {
      $operations['delete']['title'] = $this->t('Delete menu');
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function ensureDestination(Url $url) {
    // We don't want to add the destination URL here, as it means we get
    // redirected back to the list-builder after adding/deleting menu links from
    // a menu.
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#attached']['library'][] = "menu_ui/drupal.menu_ui.adminforms";
    return $build;
  }

}
