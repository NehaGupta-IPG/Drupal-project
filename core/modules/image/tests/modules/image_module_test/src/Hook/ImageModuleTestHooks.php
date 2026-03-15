<?php

declare(strict_types=1);

namespace Drupal\image_module_test\Hook;

use Drupal\image\ImageStyleInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for image_module_test.
 */
class ImageModuleTestHooks {

  /**
   * Implements hook_image_effect_info_alter().
   */
  #[Hook('image_effect_info_alter')]
  public function imageEffectInfoAlter(&$effects): void {
    $state = \Drupal::state();
    // The 'image_module_test.counter' state variable value is set and accessed
    // from the ImageEffectsTest::testImageEffectsCaching() test and used to
    // signal if the image effect plugin definitions were computed or were
    // retrieved from the cache.
    // @see \Drupal\Tests\image\Kernel\ImageEffectsTest::testImageEffectsCaching()
    $counter = $state->get('image_module_test.counter');
    // Increase the test counter, signaling that image effects were processed,
    // rather than being served from the cache.
    $state->set('image_module_test.counter', ++$counter);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   *
   * Used to save test third party settings in the image style entity.
   */
  #[Hook('image_style_presave')]
  public function imageStylePresave(ImageStyleInterface $style): void {
    $style->setThirdPartySetting('image_module_test', 'foo', 'bar');
  }

  /**
   * Implements hook_image_style_flush().
   */
  #[Hook('image_style_flush')]
  public function imageStyleFlush($style, $path = NULL): void {
    $state = \Drupal::state();
    $state->set('image_module_test_image_style_flush.called', $path);
  }

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download')]
  public function fileDownload($uri): array {
    $default_uri = \Drupal::keyValue('image')->get('test_file_download', FALSE);
    if ($default_uri == $uri) {
      return ['X-Image-Owned-By' => 'image_module_test'];
    }
    return [];
  }

  /**
   * Implements hook_image_style_uri_alter().
   */
  #[Hook('image_style_uri_alter')]
  public function imageStyleUriAlter(&$uri, array $context): void {
    if (($name = $context['image_style']->id()) === 'watermarked_low_resolution') {
      $path = $context['path'];
      // Low resolution, watermarked images are available publicly, in the
      // public:// stream wrapper.
      $uri = "public://styles/$name/public/$path";
    }
  }

}
