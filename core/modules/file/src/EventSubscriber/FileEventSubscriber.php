<?php

namespace Drupal\file\EventSubscriber;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\file\FilenameSanitizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sanitizes uploaded filenames.
 *
 * @package Drupal\file\EventSubscriber
 */
class FileEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new file event listener.
   *
   * @param \Drupal\file\FilenameSanitizer $fileSanitizeName
   *   The sanitize filename service.
   */
  public function __construct(
    protected FilenameSanitizer $fileSanitizeName,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FileUploadSanitizeNameEvent::class => 'sanitizeFilename',
    ];
  }

  /**
   * Sanitizes the filename of a file being uploaded.
   *
   * @param \Drupal\Core\File\Event\FileUploadSanitizeNameEvent $event
   *   File upload sanitize name event.
   *
   * @see file_form_system_file_system_settings_alter()
   */
  public function sanitizeFilename(FileUploadSanitizeNameEvent $event) {
    $filename = $event->getFilename();
    $filename = $this->fileSanitizeName->sanitizeFilename($filename);
    $event->setFilename($filename);
  }

}
