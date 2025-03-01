<?php

namespace Drupal\image\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Action to generate an image file derivatives for the given image styles.
 */
#[Action(
  id: 'file_image_styles_generate_action',
  action_label: new TranslatableMarkup('Generate image derivatives for the provided image styles'),
  type: 'file'
)]
 class FileImageStylesGenerateAction extends FileImageStyleActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'image_styles' => [],
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

    $form['image_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Image styles'),
      '#description' => $this->t('Select the image style to apply to the original image.'),
      '#options' => $options,
      '#default_value' => $this->configuration['image_styles'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['image_styles'] = $form_state->getValue('image_styles');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($file = NULL): void {
    $style_ids = array_filter($this->configuration['image_styles']);
    /** @var \Drupal\image\Entity\ImageStyle[] $styles */
    $styles = $this->imageStyleStorage->loadMultiple($style_ids);

    $original_uri = $file->getFileUri();
    $original_size = filesize($original_uri);
    foreach ($styles as $style) {
      // Set up derivative file information.
      $derivative_uri = $style->buildUri($original_uri);
      // Create derivative if necessary.
      if (!file_exists($derivative_uri)) {
        $style->createDerivative($original_uri, $derivative_uri);
        $new_size = filesize($original_uri);
        $this->logger->info('New image derivative %file_uri with style %style was generated. Original size: %old_size, new size: %new_size.', [
          '%file' => $derivative_uri,
          '%style' => $style->id(),
          '%old_size' => ByteSizeMarkup::create($original_size),
          '%new_size' => ByteSizeMarkup::create($new_size),
        ]);

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    // Make sure the image styles are set and they are real.
    $style_ids = array_filter($this->configuration['image_styles']);
    if (empty($style_ids)) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    $styles = $this->imageStyleStorage->loadMultiple($style_ids);
    if (empty($styles)) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    return parent::access($object, $account, $return_as_object);
  }

 }
