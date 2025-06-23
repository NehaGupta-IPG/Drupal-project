<?php

declare(strict_types=1);

namespace Drupal\media\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Install time requirements for the media module.
 */
class MediaRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $destination = 'public://media-icons/generic';
    \Drupal::service('file_system')->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $is_directory = is_dir($destination);
    $is_writable = is_writable($destination);
    if ($is_directory && $is_writable) {
      return [];
    }
    $error = !$is_directory
      ? new TranslatableMarkup('The directory %directory does not exist or is not writable.', ['%directory' => $destination])
      : new TranslatableMarkup('The directory %directory is not writable.', ['%directory' => $destination]);
    $description = new TranslatableMarkup('An automated attempt to create this directory failed, possibly due to a permissions problem. To proceed with the installation, either create the directory and modify its permissions manually or ensure that the installer has the permissions to create it automatically. For more information, see INSTALL.txt or the <a href=":handbook_url">online handbook</a>.', [':handbook_url' => 'https://www.drupal.org/server-permissions']);
    return [
      'media' => [
        'description' => [
          '#type' => 'inline_template',
          '#template' => '{{ error }} {{ description }}',
          '#context' => [
            'error' => $error,
            'description' => $description,
          ],
        ],
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

}
