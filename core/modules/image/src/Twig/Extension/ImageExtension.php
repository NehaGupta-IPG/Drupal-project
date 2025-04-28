<?php

namespace Drupal\image\Twig\Extension;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Twig\TwigFilter;

/**
 * Provides twig extensions for images.
 */
class ImageExtension extends AbstractExtension {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * File URI generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs the image_attributes twig filter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('use_image_style', [$this, 'useImageStyle']),
      new TwigFilter('img_attributes', [$this, 'imgAttributes'], ['image_style' => NULL]),
    ];
  }

  /**
   * Twig filter to replace the image style of an image field.
   *
   * @param array $render_array
   *   The render array of an image field.
   * @param string $image_style
   *   The image style to use.
   *
   * @return array
   *   The render array with the image style replaced.
   */
  public function useImageStyle($render_array, $image_style) {
    /** @var Drupal\image\ImageStyleStorageInterface $image_style_storage */
    if (!empty($render_array['#item']) && $render_array['#item'] instanceof ImageItem) {
      $image_style_storage = $this->entityTypeManager->getStorage('image_style');
      if ($image_style_storage->load($image_style)) {
        $render_array['#image_style'] = $image_style;
      }
    }
    return $render_array;
  }

  /**
   * Gets the html img attributes from an image field render array.
   *
   * @param mixed $render_array
   *   The render array for the image field.
   *
   * @return array|null
   *   An array of image attributes or NULL if not an image.
   */
  public function imgAttributes($render_array) {
    if (!empty($render_array['#item']) && $render_array['#item'] instanceof ImageItem) {
      $item = $render_array['#item'];

      // Extend the existing attributes if provided.
      $attributes = !empty($render_array['#item_attributes']) ? $render_array['#item_attributes'] : [];

      // Resolve the image style width and height if provided.
      if (!empty($render_array['#image_style'])) {
        /** @var Drupal\image\ImageStyleStorageInterface $image_style_storage */
        $image_style_storage = $this->entityTypeManager->getStorage('image_style');

        /** @var Drupal\image\ImageStyleInterface $image_style */
        $image_style = $image_style_storage->load($render_array['#image_style']);
        if ($image_style) {
          $uri = $image_style->buildUri($item->entity->getFileUri());
          if (!file_exists($uri)) {
            $image_style->createDerivative($item->entity->getFileUri(), $uri);
          }
          $url = $image_style->buildUrl($item->entity->getFileUri());
          $image_info = getimagesize($url);
          $width = $image_info[0] ?? '';
          $height = $image_info[1] ?? '';
          return array_merge($attributes, [
            'width' => $width,
            'height' => $height,
            'alt' => $item->get('alt')->getValue(),
            'src' => $this->fileUrlGenerator->generateString($uri),
          ]);
        }
      }

      return array_merge($attributes, [
        'width' => $item->get('width')->getValue(),
        'height' => $item->get('height')->getValue(),
        'alt' => $item->get('alt')->getValue(),
        'src' => $this->fileUrlGenerator->generateString($item->entity->getFileUri()),
      ]);
    }
    return NULL;
  }

}
