<?php

namespace Drupal\image\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Converts an image resource.
 */
#[ImageEffect(
  id: "image_convert",
  label: new TranslatableMarkup("Convert"),
  description: new TranslatableMarkup("Converts an image to a format (such as JPEG)."),
)]
class ConvertImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $source_extension = strtolower(pathinfo($image->getSource(), PATHINFO_EXTENSION));
    if ($this->isExcludedExtension($source_extension)) {
      return FALSE;
    }

    if (!$image->convert($this->configuration['extension'])) {
      $this->logger->error('Image convert failed using the %toolkit toolkit on %path (%mimetype)', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $image->getSource(),
        '%mimetype' => $image->getMimeType(),
      ]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeExtension($extension) {
    if ($this->isExcludedExtension($extension)) {
      return $extension;
    }

    return $this->configuration['extension'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary_text = mb_strtoupper($this->configuration['extension']);
    $excluded_extension = strtolower($this->configuration['excluded_extension'] ?? 'none');
    if ($excluded_extension !== 'none') {
      $summary_text .= ' ' . $this->t('(excluding @extension)', [
        '@extension' => mb_strtoupper($excluded_extension),
      ]);
    }

    $summary = [
      '#markup' => $summary_text,
    ];
    $summary += parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'extension' => NULL,
      'excluded_extension' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $extensions = \Drupal::service('image.toolkit.manager')->getDefaultToolkit()->getSupportedExtensions();
    $options = array_combine(
      $extensions,
      array_map('mb_strtoupper', $extensions)
    );
    $form['extension'] = [
      '#type' => 'select',
      '#title' => $this->t('Convert to'),
      '#default_value' => $this->configuration['extension'],
      '#required' => TRUE,
      '#options' => $options,
    ];
    $form['excluded_extension'] = [
      '#type' => 'select',
      '#title' => $this->t('Exclude extension'),
      '#default_value' => $this->configuration['excluded_extension'] ?? 'none',
      '#options' => ['none' => $this->t('- None -')] + $options,
      '#description' => $this->t('Skip conversion when the source image uses this extension.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['extension'] = $form_state->getValue('extension');
    $this->configuration['excluded_extension'] = $form_state->getValue('excluded_extension');
  }

  /**
   * Checks whether the provided extension should bypass conversion.
   *
   * @param string $extension
   *   The source extension.
   *
   * @return bool
   *   TRUE when conversion should be skipped.
   */
  protected function isExcludedExtension(string $extension): bool {
    $excluded_extension = strtolower($this->configuration['excluded_extension'] ?? 'none');
    return $excluded_extension !== 'none' && strtolower($extension) === $excluded_extension;
  }

}
