<?php

namespace Drupal\layout_builder;

/**
 * A trait for building the block render array for layout builder.
 */
trait LayoutBuilderBlockBuildTrait {

  /**
   * Build a block for Layout Builder element.
   *
   * @param array $component
   *   The component being built.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param $region
   *   The region.
   * @param $delta
   *   The section delta.
   * @param $uuid
   *   The block UUID.
   *
   * @return array
   *   The block render array.
   */
  protected function buildAdministrativeBlock(array $component, SectionStorageInterface $section_storage, $region, $delta, $uuid) {
    $component['#attributes']['class'][] = 'js-layout-builder-block';
    $component['#attributes']['class'][] = 'layout-builder-block';
    $component['#attributes']['data-layout-block-uuid'] = $uuid;
    $component['#attributes']['data-layout-builder-highlight-id'] = $this->blockUpdateHighlightId($uuid);
    $component['#contextual_links'] = [
      'layout_builder_block' => [
        'route_parameters' => [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'delta' => $delta,
          'region' => $region,
          'uuid' => $uuid,
        ],
        // Add metadata about the current operations available in
        // contextual links. This will invalidate the client-side cache of
        // links that were cached before the 'move' link was added.
        // @see layout_builder.links.contextual.yml
        'metadata' => [
          'operations' => 'move:update:remove',
        ],
      ],
    ];

    return $component;
  }

}
