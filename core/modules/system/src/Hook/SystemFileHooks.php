<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * File hook implementations for system.
 */
class SystemFileHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {}

  /**
   * Implements hook_filetransfer_info().
   */
  #[Hook('filetransfer_info')]
  public function filetransferInfo(): array {
    $backends = [];
    // This is the default, will be available on most systems.
    if (function_exists('ftp_connect')) {
      $backends['ftp'] = [
        'title' => $this->t('FTP'),
        'class' => 'Drupal\Core\FileTransfer\FTP',
        'weight' => 0,
      ];
    }
    // SSH2 lib connection is only available if the proper PHP extension is
    // installed.
    if (function_exists('ssh2_connect')) {
      $backends['ssh'] = [
        'title' => $this->t('SSH'),
        'class' => 'Drupal\Core\FileTransfer\SSH',
        'weight' => 20,
      ];
    }
    return $backends;
  }

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download')]
  public function fileDownload($uri): array|int|null {
    $scheme = $this->streamWrapperManager->getScheme($uri);
    if ($this->streamWrapperManager->isValidScheme($scheme)) {
      $target = $this->streamWrapperManager->getTarget($uri);
      if ($target !== FALSE) {
        if (!in_array($scheme, Settings::get('file_sa_core_2023_005_schemes', []))) {
          if (DIRECTORY_SEPARATOR !== '/') {
            $class = $this->streamWrapperManager->getClass($scheme);
            if (is_subclass_of($class, LocalStream::class)) {
              $target = str_replace(DIRECTORY_SEPARATOR, '/', $target);
            }
          }
          $parts = explode('/', $target);
          if (array_intersect($parts, ['.', '..'])) {
            return -1;
          }
        }
      }
    }
    $core_schemes = ['public', 'private', 'temporary'];
    $additional_public_schemes = array_diff(Settings::get('file_additional_public_schemes', []), $core_schemes);
    if ($additional_public_schemes) {
      $scheme = StreamWrapperManager::getScheme($uri);
      if (in_array($scheme, $additional_public_schemes, TRUE)) {
        return ['Cache-Control' => 'public'];
      }
    }
    return NULL;
  }

  /**
   * Implements hook_archiver_info_alter().
   */
  #[Hook('archiver_info_alter')]
  public function archiverInfoAlter(&$info): void {
    if (!class_exists(\ZipArchive::class)) {
      // PHP Zip extension is missing.
      unset($info['Zip']);
    }
  }

}
