<?php

namespace Drupal\image;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of image style entities.
 *
 * @see \Drupal\image\Entity\ImageStyle
 */
class ImageStyleListBuilder extends ConfigEntityListBuilder {

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
  protected function getDefaultOperations(EntityInterface $entity, CacheableMetadata $cacheability) {
    $flush = [
      'title' => $this->t('Flush'),
      'weight' => 200,
      'url' => $entity->toUrl('flush-form'),
    ];

    $validate_attributes = [
      'data-image-style-validate-public' => Url::fromRoute(
        'image.style_public',
        [
          'image_style' => $entity->id(),
          'scheme' => 'public',
        ],
        ['query' => ['file' => 'validate.png']],
      )->toString(),
      'data-image-style-name' => $entity->label(),
    ];

    // Add private scheme validation path if private file system is configured.
    if (Settings::get('file_private_path')) {
      $validate_attributes['data-image-style-validate-private'] = Url::fromRoute(
        'image.style_private',
        [
          'image_style' => $entity->id(),
          'scheme' => 'private',
        ],
        ['query' => ['file' => 'validate.png']],
      )->toString();
    }

    $validate = [
      'title' => $this->t('Validate'),
      'weight' => 210,
      'url' => Url::fromRoute('<none>'),
      'attributes' => $validate_attributes,
    ];

    return parent::getDefaultOperations($entity, $cacheability) + [
      'flush' => $flush,
      'validate' => $validate,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('There are currently no styles. <a href=":url">Add a new one</a>.', [
      ':url' => Url::fromRoute('image.style_add')->toString(),
    ]);
    $build['validate_messages'] = [
      '#markup' => '<div data-image-style-validate-messages></div>',
      '#weight' => -10,
    ];
    $build['#attached']['library'][] = 'image/validate';
    return $build;
  }

}
