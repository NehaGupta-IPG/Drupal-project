<?php

namespace Drupal\file\FileUsage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Utility\Error;
use Drupal\file\FileInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Defines the base class for database file usage backend.
 */
abstract class FileUsageBase implements FileUsageInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  public function __construct(
    protected ConfigFactoryInterface $config_factory,
    ?FileSystemInterface $file_system = NULL,
  ) {
    if ($file_system === NULL) {
      @trigger_error('The $fileSystem argument to ' . __METHOD__ . '() is required in drupal:11.3.0.', E_USER_DEPRECATED);
      $file_system = \Drupal::service(FileSystemInterface::class);
    }
    $this->fileSystem = $file_system;
    $this->setLogger(new NullLogger());
  }

  /**
   * {@inheritdoc}
   */
  public function add(FileInterface $file, $module, $type, $id, $count = 1) {
    // Make sure that a used file is permanent.
    if (!$file->isPermanent()) {
      $file->setPermanent();
      $file->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(FileInterface $file, $module, $type = NULL, $id = NULL, $count = 1) {
    $set_temporary = $this->configFactory->get('file.settings')
      ->get('make_unused_managed_files_temporary');

    $usage = \Drupal::service('file.usage')->listUsage($file);
    if ($usage) {
      return;
    }

    if ($set_temporary) {
      // Mark the file as temporary, which will result in it being deleted by
      // system_cron().
      $file->setTemporary();
      $file->save();
    }
    else {
      try {
        $this->fileSystem->delete($file->getFileUri());
      }
      catch (FileException $e) {
        Error::logException($this->logger, $e);
      }
      finally {
        $file->delete();
      }
    }
  }

}
