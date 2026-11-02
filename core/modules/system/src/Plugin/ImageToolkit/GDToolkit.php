<?php

namespace Drupal\system\Plugin\ImageToolkit;

use Drupal\Component\Utility\Color;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkit;
use Drupal\Core\ImageToolkit\ImageToolkitBase;
use Drupal\Core\ImageToolkit\ImageToolkitOperationManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Image\GdImageWrapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// cspell:ignore imagecreatefrom rrggbb

/**
 * Defines the GD2 toolkit for image manipulation within Drupal.
 */
#[ImageToolkit(
  id: "gd",
  title: new TranslatableMarkup("GD2 image manipulation toolkit"),
  imageClass: GdImageWrapper::class,
)]
class GDToolkit extends ImageToolkitBase {

  // phpcs:ignoreFile

  /**
   * A GD image.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access
   *   GdImageWrapper::$gdImage through its getter/setter instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  protected ?\GdImage $image {
    get {
      @trigger_error('Getting ' . __CLASS__ . '::$image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access GdImageWrapper::$gdImage through its getter/setter instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      return $this->backfillImage->getGdImageObject();
    }
    set(?\GdImage $image) {
      @trigger_error('Setting ' . __CLASS__ . '::$image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access GdImageWrapper::$gdImage through its getter/setter instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $this->backfillImage->setGdImageObject($image);
    }
  }

  /**
   * Image type represented by a PHP IMAGETYPE_* constant (e.g. IMAGETYPE_JPEG).
   *
   * @var int
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access
   *   GdImageWrapper::$type through its getter/setter instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  protected $type {
    get {
      @trigger_error('Getting ' . __CLASS__ . '::$type is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access GdImageWrapper::$type through its getter/setter instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      return $this->backfillImage->getType();
    }
    set($type) {
      @trigger_error('Setting ' . __CLASS__ . '::$type is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access GdImageWrapper::$type through its getter/setter instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $this->backfillImage->setType($type);
    }
  }

  /**
   * Image information from a file, available prior to loading the GD object.
   *
   * This contains a copy of the array returned by executing getimagesize()
   * on the image file when the image object is instantiated. It gets reset
   * to NULL as soon as the GD object is loaded.
   *
   * @var array|null
   *
   * @see \Drupal\system\Plugin\ImageToolkit\GDToolkit::parseFile()
   * @see \Drupal\system\Plugin\ImageToolkit\GDToolkit::setImage()
   * @see http://php.net/manual/function.getimagesize.php
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access
   *   GdImageWrapper::$preLoadInfo through its getter/setter instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  protected $preLoadInfo = NULL;

  /**
   * Constructs a GDToolkit object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\ImageToolkit\ImageToolkitOperationManagerInterface $operation_manager
   *   The toolkit operation manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The StreamWrapper manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    ImageToolkitOperationManagerInterface $operation_manager,
    #[Autowire('logger.channel.image')]
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory,
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
    protected readonly FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $operation_manager, $logger, $config_factory);
  }

  /**
   * Sets an image or resets existing one.
   *
   * @param \GdImage|null $image
   *   The GD image object or NULL.
   *
   * @return $this
   *   An instance of the current toolkit object.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   GdImageWrapper::setGdImageObject() instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public function setImage(?\GdImage $image): static {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use GdImageWrapper::setGdImageObject() instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
    $this->image = $image;
    return $this;
  }

  /**
   * Retrieves the image.
   *
   * @return \GdImage|null
   *   The GD image object, or NULL if not available.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   GdImageWrapper::getGdImageObject() instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public function getImage(): ?\GdImage {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use GdImageWrapper::getGdImageObject() instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
    return $this->image;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['image_jpeg_quality'] = [
      '#type' => 'number',
      '#title' => $this->t('JPEG quality'),
      '#description' => $this->t('Define the image quality for JPEG manipulations. Ranges from 0 to 100. Higher values mean better image quality but bigger files.'),
      '#min' => 0,
      '#max' => 100,
      '#default_value' => $this->configFactory->getEditable('system.image.gd')->get('jpeg_quality', FALSE),
      '#field_suffix' => $this->t('%'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('system.image.gd')
      ->set('jpeg_quality', $form_state->getValue(['gd', 'image_jpeg_quality']))
      ->save();
  }

  /**
   * Loads an image from a file.
   *
   * @return bool
   *   TRUE or FALSE, based on success.
   */
  public function load($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    // Return immediately if the image file is not valid.
    if (!$image->isValid()) {
      return FALSE;
    }

    // Invalidate the image object and return if there's no function to load the
    // image file.
    $function = 'imagecreatefrom' . image_type_to_extension($image->getType(), FALSE);
    if (!function_exists($function)) {
      $this->logger->error("The image toolkit '@toolkit' can not process image '@image'.", [
        '@toolkit' => $this->getPluginId(),
        '@image' => $image->getSource(),
      ]);
      $image->setPreLoadInfo(NULL);
      return FALSE;
    }

    // Invalidate the image object and return if the load fails.
    try {
      // Suppress warnings from a library action. Some functions can trigger
      // warnings that are not actionable like loading a PNG content with
      // certain color profiles. Actual issues with image processing will
      // trigger exceptions that are logged later on.
      $gdImage = @$function($image->getSource());
    }
    catch (\Throwable $t) {
      $this->logger->error("The image toolkit '@toolkit' failed loading image '@image'. Reported error: @class - @message", [
        '@toolkit' => $this->getPluginId(),
        '@image' => $image->getSource(),
        '@class' => get_class($t),
        '@message' => $t->getMessage(),
      ]);
      $image->setPreLoadInfo(NULL);
      return FALSE;
    }

    $image->setGdImageObject($gdImage);
    if (imageistruecolor($gdImage)) {
      return TRUE;
    }
    else {
      // Convert indexed images to truecolor, copying the image to a new
      // truecolor image, so that filters work correctly and don't result
      // in unnecessary dither.
      $data = [
        'width' => imagesx($gdImage),
        'height' => imagesy($gdImage),
        'extension' => image_type_to_extension($image->getType(), FALSE),
        'transparent_color' => $this->doGetTransparentColor($gdImage),
        'is_temp' => TRUE,
      ];
      if ($this->apply('create_new', $data, $image)) {
        imagecopy($image->getGdImageObject(), $gdImage, 0, 0, 0, 0, imagesx($gdImage), imagesy($gdImage));
      }
    }
    return (bool) $image->getGdImageObject();
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    return ($image->hasPreLoadInfo() || $image->hasGdImageObject());
  }

  /**
   * {@inheritdoc}
   */
  public function save($destination = NULL, $image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);
    assert(is_string($destination));

    $scheme = StreamWrapperManager::getScheme($destination);
    // Work around lack of stream wrapper support in imagejpeg() and imagepng().
    if ($scheme && $this->streamWrapperManager->isValidScheme($scheme)) {
      // If destination is not local, save image to temporary local file.
      $local_wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::LOCAL);
      if (!isset($local_wrappers[$scheme])) {
        $permanent_destination = $destination;
        $destination = $this->fileSystem->tempnam('temporary://', 'gd_');
      }
      // Convert stream wrapper URI to normal path.
      $destination = $this->fileSystem->realpath($destination);
    }

    $function = 'image' . image_type_to_extension($image->getType(), FALSE);
    if (!function_exists($function)) {
      return FALSE;
    }
    if ($image->getType() == IMAGETYPE_JPEG) {
      try {
        $success = $function($image->getGdImageObject(), $destination, $this->configFactory->get('system.image.gd')->get('jpeg_quality'));
      }
      catch (\Throwable $t) {
        $this->logger->error("The image toolkit '@toolkit' failed saving image '@image'. Reported error: @class - @message", [
          '@toolkit' => $this->getPluginId(),
          '@image' => $destination,
          '@class' => get_class($t),
          '@message' => $t->getMessage(),
        ]);
        $success = FALSE;
      }
    }
    else {
      // Image types that support alpha need to be saved accordingly.
      if (in_array($image->getType(), [
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
        IMAGETYPE_AVIF,
      ], TRUE)) {
        imagealphablending($image->getGdImageObject(), FALSE);
        imagesavealpha($image->getGdImageObject(), TRUE);
      }
      try {
        $success = $function($image->getGdImageObject(), $destination);
      }
      catch (\Throwable $t) {
        $this->logger->error("The image toolkit '@toolkit' failed saving image '@image'. Reported error: @class - @message", [
          '@toolkit' => $this->getPluginId(),
          '@image' => $destination,
          '@class' => get_class($t),
          '@message' => $t->getMessage(),
        ]);
        $success = FALSE;
      }
    }
    // Move temporary local file to remote destination.
    if (isset($permanent_destination) && $success) {
      try {
        $this->fileSystem->move($destination, $permanent_destination, FileExists::Replace);
        return TRUE;
      }
      catch (FileException) {
        return FALSE;
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function parseFile($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    $data = @getimagesize($image->getSource());
    if ($data && in_array($data[2], static::supportedTypes())) {
      $image->setType($data[2]);
      $image->setPreLoadInfo($data);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the color set for transparency in GIF images.
   *
   * @return string|null
   *   A color string like '#rrggbb', or NULL if not set or not relevant.
   */
  public function getTransparentColor($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    if ($image->getGdImageObject() === NULL || $image->getType() != IMAGETYPE_GIF) {
      return NULL;
    }
    return $this->doGetTransparentColor($image->getGdImageObject());
  }

  /**
   * Gets the color set for alpha channel from a \GdImage object.
   *
   * @var \GdImage $gdImage
   *   A GdImage object.
   *
   * @return string|null
   *   A color string like '#rrggbb', or NULL if not set or not relevant.
   */
  protected function doGetTransparentColor(\GdImage $gdImage): ?string {
    // Find out if a transparent color is set, will return -1 if no
    // transparent color has been defined in the image.
    $transparent = imagecolortransparent($gdImage);
    if ($transparent >= 0) {
      // Find out the number of colors in the image palette. It will be 0 for
      // truecolor images.
      $palette_size = imagecolorstotal($gdImage);
      if ($palette_size == 0 || $transparent < $palette_size) {
        // Return the transparent color, either if it is a truecolor image
        // or if the transparent color is part of the palette.
        // Since the index of the transparent color is a property of the
        // image rather than of the palette, it is possible that an image
        // could be created with this index set outside the palette size.
        // (see http://stackoverflow.com/a/3898007).
        $rgb = imagecolorsforindex($gdImage, $transparent);
        unset($rgb['alpha']);
        return Color::rgbToHex($rgb);
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    if ($image->hasPreLoadInfo()) {
      return $image->getPreLoadInfo()[0];
    }
    elseif ($res = $image->getGdImageObject()) {
      return imagesx($res);
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    if ($image->hasPreLoadInfo()) {
      return $image->getPreLoadInfo()[1];
    }
    elseif ($res = $image->getGdImageObject()) {
      return imagesy($res);
    }
    else {
      return NULL;
    }
  }

  /**
   * Gets the PHP type of the image.
   *
   * @return int
   *   The image type represented by a PHP IMAGETYPE_* constant (e.g.
   *   IMAGETYPE_JPEG).
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   GdImageWrapper::getType() instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public function getType() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use GdImageWrapper::getType() instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
    return $this->type;
  }

  /**
   * Sets the PHP type of the image.
   *
   * @param int $type
   *   The image type represented by a PHP IMAGETYPE_* constant (e.g.
   *   IMAGETYPE_JPEG).
   *
   * @return $this
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use
   *   GdImageWrapper::setType() instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public function setType($type) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Use GdImageWrapper::setType() instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
    $this->type = $type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType($image = NULL) {
    // @phpstan-ignore property.deprecated
    if (!$image && $this->backfillImage) {
      @trigger_error(__METHOD__ . '() not receiving the Image is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Always pass an ImageInterface object. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $image = $this->backfillImage;
    }
    assert($image instanceof GdImageWrapper);

    return $image->getType() ? image_type_to_mime_type($image->getType()) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirements() {
    $requirements = [];

    $info = gd_info();
    $requirements['version'] = [
      'title' => $this->t('GD library'),
      'value' => $info['GD Version'],
    ];

    // Check if toolkit supported image formats can be actually processed by the
    // GD library installed with PHP.
    $check_formats = [
      IMG_GIF => 'GIF',
      IMG_JPG => 'JPEG',
      IMG_PNG => 'PNG',
      IMG_WEBP => 'WEBP',
      IMG_AVIF => 'AVIF',
    ];
    $supported_formats = array_filter($check_formats, fn($type) => imagetypes() & $type, ARRAY_FILTER_USE_KEY);
    $unsupported_formats = array_diff_key($check_formats, $supported_formats);

    $descriptions = [];
    if ($supported_formats) {
      $descriptions[] = $this->formatPlural(
        count($supported_formats),
        'Supported image file format: %formats.',
        'Supported image file formats: %formats.',
        ['%formats' => implode(', ', $supported_formats)]
      );
    }
    if ($unsupported_formats) {
      $unsupported = $this->formatPlural(
        count($unsupported_formats),
        'Unsupported image file format: %formats.',
        'Unsupported image file formats: %formats.',
        ['%formats' => implode(', ', $unsupported_formats)]
      );
      $fix_info = $this->t('Check the <a href="https://www.php.net/manual/en/image.installation.php">PHP GD installation documentation</a> if you want to add support.');
      $descriptions[] = $this->t('@unsupported<br>@ref', [
        '@unsupported' => $unsupported,
        '@ref' => $fix_info,
      ]);
      if (isset($unsupported_formats[IMG_AVIF])) {
        $descriptions[] = $this->t('AVIF is not supported, likely because of PHP missing a codec for encoding images. See <a href=":cr_url">the change record</a> for more information.', [
          ':cr_url' => 'https://www.drupal.org/node/3348348',
        ]);
      }
    }

    // Check for filter and rotate support.
    if (!function_exists('imagefilter') || !function_exists('imagerotate')) {
      $requirements['version']['severity'] = RequirementSeverity::Warning;
      $descriptions[] = $this->t('The GD Library for PHP is enabled, but was compiled without support for functions used by the rotate and desaturate effects. It was probably compiled using the official GD libraries from the <a href="https://libgd.github.io/">gdLibrary site</a> instead of the GD library bundled with PHP. You should recompile PHP --with-gd using the bundled GD library. See <a href="https://www.php.net/manual/book.image.php">the PHP manual</a>.');
    }

    if (count($descriptions) > 1) {
      $requirements['version']['description'] = [
        '#theme' => 'item_list',
        '#items' => $descriptions,
      ];
    }
    else {
      $requirements['version']['description'] = $descriptions[0];
    }

    return $requirements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    // GD2 support is available.
    return function_exists('imagegd2');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSupportedExtensions() {
    $extensions = [];
    foreach (static::supportedTypes() as $image_type) {
      // @todo Automatically fetch possible extensions for each mime type.
      // @see https://www.drupal.org/node/2311679
      $extension = mb_strtolower(image_type_to_extension($image_type, FALSE));
      $extensions[] = $extension;
      // Add some known similar extensions.
      if ($extension === 'jpeg') {
        $extensions[] = 'jpg';
        $extensions[] = 'jpe';
      }
    }
    return $extensions;
  }

  /**
   * Returns the IMAGETYPE_xxx constant for the given extension.
   *
   * This is the reverse of the image_type_to_extension() function.
   *
   * @param string $extension
   *   The extension to get the IMAGETYPE_xxx constant for.
   *
   * @return int
   *   The IMAGETYPE_xxx constant for the given extension, or IMAGETYPE_UNKNOWN
   *   for unsupported extensions.
   *
   * @see image_type_to_extension()
   */
  public function extensionToImageType($extension) {
    if (in_array($extension, ['jpe', 'jpg'])) {
      $extension = 'jpeg';
    }
    foreach ($this->supportedTypes() as $type) {
      if (image_type_to_extension($type, FALSE) === $extension) {
        return $type;
      }
    }
    return IMAGETYPE_UNKNOWN;
  }

  /**
   * Checks if AVIF can encode image.
   *
   * This method tries to create an AVIF image and save it to disk via
   * imageavif(). If that fails, it's likely a codec missing, or the function
   * was disabled. This is an expensive operation to run, so we cache its
   * result.
   *
   * @return bool
   *   TRUE if AVIF is fully supported, FALSE otherwise.
   */
  protected static function checkAvifSupport(): bool {
    static $supported = NULL;

    if ($supported !== NULL) {
      return $supported;
    }

    $tempFile = fopen('php://memory', 'r+');
    // We suppress any errors when running the imageavif command because of this
    // upstream php bug: https://bugs.php.net/bug.php?id=81217.
    $supported = function_exists('imageavif') && @imageavif(imagecreatetruecolor(1, 1), $tempFile, 0, 10) && fstat($tempFile)['size'] > 0;
    fclose($tempFile);

    return $supported;
  }

  /**
   * Returns a list of image types supported by the toolkit.
   *
   * @return array
   *   An array of available image types. An image type is represented by a PHP
   *   IMAGETYPE_* constant (e.g. IMAGETYPE_JPEG, IMAGETYPE_PNG, etc.).
   */
  public static function supportedTypes() {
    $types = [
      IMAGETYPE_PNG,
      IMAGETYPE_JPEG,
      IMAGETYPE_GIF,
      IMAGETYPE_WEBP,
    ];
    if (static::checkAvifSupport()) {
      $types[] = IMAGETYPE_AVIF;
    }
    return $types;
  }

}
