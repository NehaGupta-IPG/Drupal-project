<?php

declare(strict_types=1);

namespace Drupal\file\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for file forms.
 */
class FileFormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Injects the file sanitization options into /admin/config/media/file-system.
   *
   * These settings are enforced during upload by the FileEventSubscriber that
   * listens to the FileUploadSanitizeNameEvent event.
   *
   * @see \Drupal\system\Form\FileSystemForm
   * @see \Drupal\Core\File\Event\FileUploadSanitizeNameEvent
   * @see \Drupal\file\EventSubscriber\FileEventSubscriber
   */
  #[Hook('form_system_file_system_settings_alter')]
  public function formSystemFileSystemSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $form['filename_sanitization'] = [
      '#type' => 'details',
      '#title' => $this->t('Sanitize filenames'),
      '#description' => $this->t('These settings only apply to new files as they are uploaded. Changes here do not affect existing file names.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['filename_sanitization']['replacement_character'] = [
      '#type' => 'select',
      '#title' => $this->t('Replacement character'),
      '#config_target' => 'file.settings:filename_sanitization.replacement_character',
      '#options' => [
        '-' => $this->t('Dash (-)'),
        '_' => $this->t('Underscore (_)'),
      ],
      '#description' => $this->t('Used when replacing whitespace, replacing non-alphanumeric characters or transliterating unknown characters.'),
    ];
    $form['filename_sanitization']['transliterate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Transliterate'),
      '#config_target' => 'file.settings:filename_sanitization.transliterate',
      '#description' => $this->t('Transliteration replaces any characters that are not alphanumeric, underscores, periods or hyphens with the replacement character. It ensures filenames only contain ASCII characters. It is recommended to keep transliteration enabled.'),
    ];
    $form['filename_sanitization']['replace_whitespace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace whitespace with the replacement character'),
      '#config_target' => 'file.settings:filename_sanitization.replace_whitespace',
    ];
    $form['filename_sanitization']['replace_non_alphanumeric'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace non-alphanumeric characters with the replacement character'),
      '#config_target' => 'file.settings:filename_sanitization.replace_non_alphanumeric',
      '#description' => $this->t('Alphanumeric characters, dots <span aria-hidden="true">(.)</span>, underscores <span aria-hidden="true">(_)</span> and dashes <span aria-hidden="true">(-)</span> are preserved.'),
    ];
    $form['filename_sanitization']['deduplicate_separators'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace sequences of dots, underscores and/or dashes with the replacement character'),
      '#config_target' => 'file.settings:filename_sanitization.deduplicate_separators',
    ];
    $form['filename_sanitization']['lowercase'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Convert to lowercase'),
      '#config_target' => 'file.settings:filename_sanitization.lowercase',
    ];
  }

}
