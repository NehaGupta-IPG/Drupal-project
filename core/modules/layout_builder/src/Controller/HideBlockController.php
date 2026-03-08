<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for hiding/showing a component.
 */
class HideBlockController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * LayoutController constructor.
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
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('layout_builder.tempstore_repository')
    );
  }

  /**
   * Shows or hides a component.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The section delta.
   * @param string $region
   *   The component region.
   * @param string $uuid
   *   The component uuid.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function build(SectionStorageInterface $section_storage, int $delta, $region, $uuid) {
    $section = $section_storage->getSection($delta);
    $component = $section->getComponent($uuid);

    $hidden = $component->get('hidden') ?: FALSE;
    $component->set('hidden', !$hidden);

    $this->layoutTempstoreRepository->set($section_storage);
    return $this->rebuildLayout($section_storage);
  }

}
