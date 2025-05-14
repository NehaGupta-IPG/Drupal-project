<?php

namespace Drupal\custom_image_normalizer\Normalizer;

use ArrayObject;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Image\ImageFactory;
use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\image\Entity\ImageStyle;
use Drupal\file\FileInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Normalizes image fields using the display-configured image style.
 */
class ImageFieldNormalizer extends FieldItemNormalizer {

  protected $entityTypeManager;
  protected $imageFactory;
  protected $fileUrlGenerator;

  /**
   * Constructs the ImageFieldNormalizer.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ImageFactory $imageFactory, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->imageFactory = $imageFactory;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\\image\\Plugin\\Field\\FieldType\\ImageItem';

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = null, array $context = []): ArrayObject|array|string|int|float|bool|null {
    if (!$object instanceof FieldItemInterface) {
      return parent::normalize($object, $format, $context);
    }

    $data = parent::normalize($object, $format, $context);

    $file = $object->entity;
    if (!$file instanceof FileInterface) {
      return $data;
    }

    $uri = $file->getFileUri();
    $style_name = $this->getConfiguredImageStyle($object, $context);

    $width = $object->width;
    $height = $object->height;

    if ($style_name && $style = ImageStyle::load($style_name)) {
      $url = $style->buildUrl($uri);

      $styled_path = $style->buildUri($uri);
      $style->createDerivative($uri, $styled_path);

      $styled_image = $this->imageFactory->get($styled_path);
      if ($styled_image && $styled_image->isValid()) {
        $width = $styled_image->getWidth();
        $height = $styled_image->getHeight();
      }
    }
    else {
      $url = $this->fileUrlGenerator->generateAbsoluteString($uri);
    }

    return [
      'url' => $url,
      'alt' => $object->alt,
      'title' => $object->title,
      'width' => $width,
      'height' => $height,
    ];
  }

  /**
   * Attempts to get the configured image style from the view display.
   */
  protected function getConfiguredImageStyle(FieldItemInterface $field_item, array $context): ?string {
    $field_name = $field_item->getFieldDefinition()->getName();
    $entity = $context['target_entity'] ?? $field_item->getEntity();

    if (!$entity || !$entity->hasField($field_name)) {
      return NULL;
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $view_mode = $context['view_mode'] ?? 'default';

    $view_display_id = "$entity_type.$bundle.$view_mode";
    $display = EntityViewDisplay::load($view_display_id);

    if (!$display) {
      return NULL;
    }

    $component = $display->getComponent($field_name);
    if (!$component) {
      return NULL;
    }

    return $component['settings']['image_style'] ?? NULL;
  }

}



