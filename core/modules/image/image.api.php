<?php

/**
 * @file
 * Hooks related to image styles and effects.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the information provided in \Drupal\image\Annotation\ImageEffect.
 *
 * @param array $effects
 *   The array of image effects, keyed on the machine-readable effect name.
 */
function hook_image_effect_info_alter(&$effects) {
  // Override the Image module's 'Scale and Crop' effect label.
  $effects['image_scale_and_crop']['label'] = t('Bangers and Mash');
}

/**
 * Respond to image style flushing.
 *
 * This hook enables modules to take effect when a style is being flushed (all
 * images are being deleted from the server and regenerated). Any
 * module-specific caches that contain information related to the style should
 * be cleared using this hook. This hook is called whenever a style is updated,
 * deleted, or any effect associated with the style is update or deleted.
 *
 * @param \Drupal\image\ImageStyleInterface $style
 *   The image style object that is being flushed.
 * @param string|null $path
 *   (optional) The original image path or URI. If it's supplied, only this
 *   image derivative will be flushed.
 */
function hook_image_style_flush($style, $path = NULL): void {
  // Empty cached data that contains information about the style.
  \Drupal::cache('my_module')->deleteAll();
}

/**
 * Alters the derivative URI.
 *
 * This hook allows modules to alter the URI of the derivative. By implementing
 * this hook, a module can decide, for example, that a specific image style will
 * be available in a different stream wrapper. This hook is called when the
 * image module builds the URI of an image derivative.
 *
 * Note: If you use a special URI alteration that stores the image derivatives
 * outside of public://styles/{image_style}, private://styles/{image_style} or
 * even outside Drupal (for example, on a public image service like Flickr,
 * Instagram, etc.), you need to implement your own flush policy. Implementing
 * hook_image_style_flush() is a good point where you can define your custom
 * flush actions.
 *
 * @param string $uri
 *   The image style URI to be altered.
 * @param array $context
 *   A context associative array with next keys and values:
 *   - scheme: The image scheme.
 *   - path: The image path. If this image style changes the extension of the
 *     derivative, the value of 'path' item reflects this.
 *   - image_style: The \Drupal\image\ImageStyleInterface image style object.
 */
function hook_image_style_uri_alter(&$uri, array $context) {
  if (($name = $context['image_style']->id()) === 'watermarked_low_resolution') {
    $path = $context['path'];
    // Low resolution, watermarked images are available in the public readable
    // stream wrapper.
    $uri = "public://styles/$name/public/$path";
  }
}

/**
 * Alters the original file URI.
 *
 * This hook is the opposite of hook_image_style_uri_alter(). It helps to
 * restore the original path to file in case the URI of the image derivative
 * was altered.
 *
 * @param string $uri
 *   The original file URI to be altered.
 * @param array $context
 *   A context associative array with next keys and values:
 *   - scheme: The image scheme.
 *   - path: The image path. If this image style changes the extension of the
 *     derivative, the value of 'path' item reflects this.
 *   - image_style: The \Drupal\image\ImageStyleInterface image style object.
 */
function hook_image_style_source_alter(&$uri, array $context) {
  if ($context['image_style']->id() === 'watermarked_low_resolution') {
    // The original file that is used in `watermarked_low_resolution` derivatives
    // is actually in private file system, so use it here.
    $uri = "private://" . $context['path'];
  }
}

/**
 * @} End of "addtogroup hooks".
 */
