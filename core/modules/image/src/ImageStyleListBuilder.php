<?php

namespace Drupal\image;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of image style entities.
 *
 * @see \Drupal\image\Entity\ImageStyle
 */
class ImageStyleListBuilder extends ConfigEntityListBuilder {

  /**
   * The count of image styles needed to display the image styles filter.
   */
  private const MIN_IMAGE_STYLES_COUNT = 1;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Style name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $flush = [
      'title' => $this->t('Flush'),
      'weight' => 200,
      'url' => $entity->toUrl('flush-form'),
    ];

    return parent::getDefaultOperations($entity) + [
      'flush' => $flush,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['#type'] = 'container';
    $build['content'] = parent::render();

    if (!empty($build['content']['table']['#rows'])
      && count($build['content']['table']['#rows']) > self::MIN_IMAGE_STYLES_COUNT
    ) {
      $build['filters'] = [
        'text' => [
          '#type' => 'search',
          '#title' => $this->t('Filter'),
          '#title_display' => 'invisible',
          '#size' => 60,
          '#placeholder' => $this->t('Filter by style name'),
          '#attributes' => [
            'class' => ['image-style-filter-text'],
            'data-table' => '.image-style-listing-table',
            'autocomplete' => 'off',
            'title' => $this->t('Enter a part of the image style name to filter by.'),
          ],
        ],
        '#weight' => 0,
      ];

      $build['content']['#weight'] = 1;
      $build['content']['table']['#attributes']['class'] = ['image-style-listing-table'];
      $build['#attached']['library'][] = 'core/drupal.ajax';
      $build['#attached']['library'][] = 'image/image_style.list';
    }

    $build['content']['table']['#empty'] = $this->t(
      'There are currently no styles. <a href=":url">Add a new one</a>.',
      [
        ':url' => Url::fromRoute('image.style_add')->toString(),
      ]
    );
    return $build;
  }

}
