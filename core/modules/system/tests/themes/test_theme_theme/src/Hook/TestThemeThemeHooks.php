<?php

declare(strict_types=1);

namespace Drupal\test_theme_theme\Hook;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;

/**
 * Hook implementations for test_theme_theme.
 */
class TestThemeThemeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_system_theme_settings_alter().
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(&$form, FormStateInterface $form_state): void {
    $form['custom_logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Secondary logo.'),
      '#default_value' => \Drupal::service(ThemeSettingsProvider::class)->getSetting('custom_logo'),
      '#progress_indicator' => 'bar',
      '#progress_message' => $this->t('Processing...'),
      '#upload_location' => 'public://test',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'gif png jpg jpeg',
        ],
      ],
    ];
    $form['#submit'][] = [TestThemeThemeHooks::class, 'themeSettingsSubmit'];
  }

  /**
   * Test theme form settings submission handler.
   */
  public function themeSettingsSubmit(&$form, FormStateInterface $form_state): void {
    if ($file_id = $form_state->getValue(['custom_logo', '0'])) {
      $file = File::load($file_id);
      $file->setPermanent();
      $file->save();
    }
  }

}
