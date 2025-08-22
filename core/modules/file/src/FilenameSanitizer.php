<?php

namespace Drupal\file;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class of FilenameSanitizer.
 */
class FilenameSanitizer {

  /**
   * FilenameSanitizer constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected TransliterationInterface $transliteration,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Sanitize the file name.
   *
   * @param string $filename
   *   The file name that will be sanitized.
   *
   * @return string
   *   Sanitized file name.
   */
  public function sanitizeFilename($filename) {
    $fileSettings = $this->configFactory->get('file.settings');
    $transliterate = $fileSettings->get('filename_sanitization.transliterate');
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension !== '') {
      $extension = '.' . $extension;
      $filename = pathinfo($filename, PATHINFO_FILENAME);
    }

    // Sanitize the filename according to configuration.
    $alphanumeric = $fileSettings->get('filename_sanitization.replace_non_alphanumeric');
    $replacement = $fileSettings->get('filename_sanitization.replacement_character');
    if ($transliterate) {
      $transliterated_filename = $this->transliteration->transliterate(
        $filename,
        $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
        $replacement
      );
      if (mb_strlen($transliterated_filename) > 0) {
        $filename = $transliterated_filename;
      }
      else {
        // If transliteration has resulted in a zero length string enable the
        // 'replace_non_alphanumeric' option and ignore the result of
        // transliteration.
        $alphanumeric = TRUE;
      }
    }
    if ($fileSettings->get('filename_sanitization.replace_whitespace')) {
      $filename = preg_replace('/\s/u', $replacement, trim($filename));
    }
    // Only honor replace_non_alphanumeric if transliterate is enabled.
    if ($transliterate && $alphanumeric) {
      $filename = preg_replace('/[^0-9A-Za-z_.-]/u', $replacement, $filename);
    }
    if ($fileSettings->get('filename_sanitization.deduplicate_separators')) {
      $filename = preg_replace('/(_)_+|(\.)\.+|(-)-+/u', $replacement, $filename);
      // Replace multiple separators with single one.
      $filename = preg_replace('/(_|\.|\-)[(_|\.|\-)]+/u', $replacement, $filename);
      $filename = preg_replace('/' . preg_quote($replacement, '/') . '[' . preg_quote($replacement, '/') . ']*/u', $replacement, $filename);
      // Remove replacement character from the end of the filename.
      $filename = rtrim($filename, $replacement);

      // If there is an extension remove dots from the end of the filename to
      // prevent duplicate dots.
      if (!empty($extension)) {
        $filename = rtrim($filename, '.');
      }
    }
    if ($fileSettings->get('filename_sanitization.lowercase')) {
      // Force lowercase to prevent issues on case-insensitive file systems.
      $filename = mb_strtolower($filename);
    }

    return $filename . $extension;
  }

}
