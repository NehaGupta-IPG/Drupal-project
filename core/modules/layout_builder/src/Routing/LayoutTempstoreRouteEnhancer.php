<?php

namespace Drupal\layout_builder\Routing;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads the section storage from the layout tempstore.
 */
class LayoutTempstoreRouteEnhancer implements EnhancerInterface {

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * Constructs a new LayoutTempstoreRouteEnhancer.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $parameters = $defaults[RouteObjectInterface::ROUTE_OBJECT]->getOption('parameters');
    if (isset($parameters['section_storage']['layout_builder_tempstore']) && isset($defaults['section_storage']) && $defaults['section_storage'] instanceof SectionStorageInterface) {
      $temp_store_section_storage = $this->layoutTempstoreRepository->get($defaults['section_storage']);
      $use_temp_store_section_storage = TRUE;

      if ($defaults['section_storage'] instanceof OverridesSectionStorage) {
        $entity = $defaults['section_storage']->getContextValue('entity');
        $temp_store_entity = $temp_store_section_storage->getContextValue('entity');

        // Don't use the section storage from the temp store if it has been
        // created on a different revision OR of the current revision does not
        // have a layout yet.
        $use_temp_store_section_storage =
          (($entity instanceof RevisionableInterface) && ($entity->getRevisionId() === $temp_store_entity->getRevisionId()))
          || ($entity->hasField('layout_builder__layout') && $entity->get('layout_builder__layout')->isEmpty());
      }

      if ($use_temp_store_section_storage) {
        $defaults['section_storage'] = $temp_store_section_storage;
      }
    }
    return $defaults;
  }

}
