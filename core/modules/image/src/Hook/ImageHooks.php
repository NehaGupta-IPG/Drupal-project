<?php

namespace Drupal\image\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image\ImageDerivativeUtilities;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Hook implementations for image.
 */
class ImageHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ImageFactory $imageFactory,
    protected readonly ConfigFactoryInterface $configFactory,
    #[AutowireServiceClosure(ImageDerivativeUtilities::class)]
    protected readonly \Closure $imageDerivativeUtilitiesClosure,
  ) {}

  /**
   * Implements hook_file_download().
   *
   * Control the access to files underneath the styles directory.
   */
  #[Hook('file_download')]
  public function fileDownload($uri): array|int|null {
    $path = StreamWrapperManager::getTarget($uri);
    // Private file access for image style derivatives.
    if (str_starts_with($path, 'styles/')) {
      $args = explode('/', $path);
      // Discard "styles", style name, and scheme from the path.
      $args = array_slice($args, 3);
      // Then the remaining parts are the path to the image.
      $original_uri = StreamWrapperManager::getScheme($uri) . '://' . implode('/', $args);
      // Check that the file exists and is an image.
      $image = $this->imageFactory->get($uri);
      if ($image->isValid()) {
        // If the image style converted the extension, it has been added to the
        // original file, resulting in filenames like image.png.jpeg. So to find
        // the actual source image, we remove the extension and check if that
        // image exists.
        if (!file_exists($original_uri)) {
          $converted_original_uri = ImageStyleDownloadController::getUriWithoutConvertedExtension($original_uri);
          if ($converted_original_uri !== $original_uri && file_exists($converted_original_uri)) {
            // The converted file does exist, use it as the source.
            $original_uri = $converted_original_uri;
          }
        }
        // Check the permissions of the original to grant access to this image.
        $headers = $this->moduleHandler->invokeAll('file_download', [$original_uri]);
        // Confirm there's at least one module granting access and none denying
        // access.
        if (!empty($headers) && !in_array(-1, $headers)) {
          return [
                // Send headers describing the image's size, and MIME-type.
            'Content-Type' => $image->getMimeType(),
            'Content-Length' => $image->getFileSize(),
          ];
        }
      }
      return -1;
    }
    // If it is the sample image we need to grant access.
    $samplePath = $this->configFactory->get('image.settings')->get('preview_image');
    if ($path === $samplePath) {
      $image = $this->imageFactory->get($samplePath);
      return [
            // Send headers describing the image's size, and MIME-type.
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      ];
    }
    return NULL;
  }

  /**
   * Implements hook_file_move().
   */
  #[Hook('file_move')]
  public function fileMove(FileInterface $file, FileInterface $source): void {
    $imageDerivativeUtilities = ($this->imageDerivativeUtilitiesClosure)();
    // Delete any image derivatives at the original image path.
    $imageDerivativeUtilities->pathFlush($source->getFileUri());
  }

}
