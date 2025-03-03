<?php

namespace Drupal\image\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Action to replace an image file with one processed by an image style.
 */
#[Action(
  id: 'file_original_image_style_action',
  label: new TranslatableMarkup('Replace image file with the provided image style'),
  type: 'file'
)]
class FileOriginalImageStyleAction extends FileImageStyleActionBase implements ContainerFactoryPluginInterface {

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
      '#title' => $this->t('Original image style'),
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

    // Check if style extension is different from the original file extension,
    // and if so, change the file name and uri.
    $original_uri = $file->getFileUri();
    $file_name = $file->getFilename();
    $original_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $derivative_extension = $style->getDerivativeExtension($original_extension);
    $derivative_uri = $original_uri;
    $extension_changed = FALSE;
    if ($derivative_extension !== $original_extension) {
      $file_name = str_replace('.' . $original_extension, '.' . $derivative_extension, $file_name);
      $derivative_uri = str_replace('.' . $original_extension, '.' . $derivative_extension, $original_uri);
      $extension_changed = TRUE;
    }

    try {
      // Generate the styled image.
      $style->createDerivative($original_uri, $derivative_uri);

      // Get the file stats before replacement.
      $original_size = filesize($original_uri);

      // Update the file metadata.
      $new_size = filesize($derivative_uri);
      if ($extension_changed) {
        $file->setFileUri($derivative_uri);
        $file->setFilename($file_name);
        $this->fileSystem->delete($original_uri);
      }
      $file->setSize($new_size);
      $file->save();

      $this->logger->info('Replaced image %file with style %style. Original size: %old_size, new size: %new_size.', [
        '%file' => $file->getFilename(),
        '%style' => $style_id,
        '%old_size' => ByteSizeMarkup::create($original_size),
        '%new_size' => ByteSizeMarkup::create($new_size),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to replace image %file with style %style: @error', [
        '%file' => $file->getFilename(),
        '%style' => $style_id,
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    // Make sure the action image style is set and real.
    if (empty($this->configuration['image_style'])) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    $style = $this->imageStyleStorage->load($this->configuration['image_style']);
    if (!$style) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    return parent::access($object, $account, $return_as_object);
  }

}
