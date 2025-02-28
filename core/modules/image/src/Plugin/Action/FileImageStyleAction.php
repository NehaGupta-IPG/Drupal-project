<?php

namespace Drupal\image\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\ImageStyleStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * Action to replace an image file with one processed by an image style.
 */
#[Action(
  id: 'file_image_style_action',
  action_label: new TranslatableMarkup('Replace image file with the provided image style'),
  type: 'file'
)]
 class FileImageStyleAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FileSystemInterface $fileSystem,
    protected ImageStyleStorageInterface $imageStyleStorage,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('logger.factory')->get('image'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'image_style' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $styles = $this->imageStyleStorage->loadMultiple();
    $options = [];
    foreach ($styles as $style) {
      $options[$style->id()] = $style->label();
    }

    $form['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style'),
      '#description' => $this->t('Select the image style to apply to the original image.'),
      '#options' => $options,
      '#default_value' => $this->configuration['image_style'],
      '#required' => TRUE,
    ];

    $form['warning'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--warning">' . $this->t('Warning: This action will permanently replace the original image files with the styled versions. This cannot be undone.') . '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['image_style'] = $form_state->getValue('image_style');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($file = NULL): void {

    // Get the image style.
    $style_id = $this->configuration['image_style'];
    /** @var \Drupal\image\Entity\ImageStyle $style */
    $style = $this->imageStyleStorage->load($style_id);
    if (!$style) {
      $this->logger->error('Image style %style not found.', ['%style' => $style_id]);
      return;
    }

    $source_uri = $file->getFileUri();

    // Check if style extension is different from the original file extension,
    // and if so, change the file name and uri.
    $file_name = $file->getFilename();
    $original_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $styled_extenstion = $style->getDerivativeExtension($original_extension);
    $styled_image_uri = $source_uri;
    $extension_changed = FALSE;
    if ($styled_extenstion !== $original_extension) {
      $file_name = str_replace('.' . $original_extension, '.' . $styled_extenstion, $file_name);
      $styled_image_uri = str_replace('.' . $original_extension, '.' . $styled_extenstion, $source_uri);
      $extension_changed = TRUE;
    }

    // Create a temporary file to store the styled image.
    $directory = $this->fileSystem->dirname($source_uri);
    $destination = $this->fileSystem->createFilename('temp_' . $file_name, $directory);

    try {
      // Generate the styled image.
      $style->createDerivative($source_uri, $destination);

      // Get the file stats before replacement.
      $original_size = filesize($source_uri);

      // Replace the original file with the styled version.
      $this->fileSystem->copy($destination, $styled_image_uri, FileExists::Replace);

      // Update the file metadata.
      $new_size = filesize($source_uri);
      if ($extension_changed) {
        $file->setFileUri($styled_image_uri);
        $file->setFilename($file_name);
        $this->fileSystem->delete($source_uri);
      }
      $file->setSize($new_size);
      $file->save();

      // Clean up the temporary file.
      $this->fileSystem->delete($destination);

      $this->logger->info('Replaced image %file with style %style. Original size: %old_size, new size: %new_size.', [
        '%file' => $file->getFilename(),
        '%style' => $style_id,
        '%old_size' => ByteSizeMarkup::create($original_size),
        '%new_size' => ByteSizeMarkup::create($new_size),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->get('image_style_action')->error('Failed to replace image %file with style %style: @error', [
        '%file' => $file->getFilename(),
        '%style' => $style_id,
        '@error' => $e->getMessage(),
      ]);

      // Clean up the temporary file if it exists.
      if (file_exists($destination)) {
        $this->fileSystem->delete($destination);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    if (!($object instanceof FileInterface)) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    // Only process image files.
    $mime_type = $object->getMimeType();
    if (strpos($mime_type, 'image/') !== 0) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    $access = $object->access('create', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));
    return $return_as_object ? $access : $access->isAllowed();
  }

 }
