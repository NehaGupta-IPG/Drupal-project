<?php

namespace Drupal\menu_ui;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

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
      // The primary action lands on the menu's link list (the common task);
      // the menu's own settings sit on the "Edit" tab from there. The
      // menu_tree_ui module repoints this to its scalable tree when enabled.
      $operations['edit']['title'] = $this->t('Edit links');
      $operations['edit']['url'] = $entity->toUrl('links');
      $operations['add'] = [
        'title' => $this->t('Add link'),
        'weight' => 20,
        'url' => $entity->toUrl('add-link-form'),
        'query' => [
          'destination' => $entity->toUrl('links')->toString(),
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
