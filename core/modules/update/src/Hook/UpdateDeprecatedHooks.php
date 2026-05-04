<?php

namespace Drupal\update\Hook;

use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Deprecated hook implementations for the update module.
 */
class UpdateDeprecatedHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly FileSystemInterface $fileSystem,
    protected readonly InfoParserInterface $infoParser,
    protected readonly TranslationInterface $translation,
  ) {}

  /**
   * Implements hook_verify_update_archive().
   *
   * First, we ensure that the archive isn't a copy of Drupal core, which the
   * update manager does not yet support. See
   * https://www.drupal.org/node/606592.
   *
   * Then, we make sure that at least one module included in the archive file
   * has an .info.yml file which claims that the code is compatible with the
   * current version of Drupal core.
   *
   * @see \Drupal\Core\Extension\ExtensionDiscovery
   */
  #[Hook('verify_update_archive')]
  public function verifyUpdateArchive($project, $archive_file, $directory): array {
    $errors = [];
    // Make sure this isn't a tarball of Drupal core.
    if (
      file_exists("{$directory}/{$project}/index.php") &&
      file_exists("{$directory}/{$project}/core/install.php") &&
      file_exists("{$directory}/{$project}/core/includes/bootstrap.inc") &&
      file_exists("{$directory}/{$project}/core/modules/node/node.module") &&
      file_exists("{$directory}/{$project}/core/modules/system/system.module")
    ) {
      return [
        'no-core' => $this->t('Automatic updating of Drupal core is not supported. See the <a href=":update-guide">Updating Drupal guide</a> for information on how to update Drupal core manually.', [
          ':update-guide' => 'https://www.drupal.org/docs/updating-drupal',
        ]),
      ];
    }
    // Parse all the .info.yml files and make sure at least one is compatible
    // with this version of Drupal core. If one is compatible, then the project
    // as a whole is considered compatible (since, for example, the project may
    // ship with some out-of-date modules that are not necessary for its overall
    // functionality).
    $compatible_project = FALSE;
    $incompatible = [];
    $files = $this->fileSystem->scanDirectory(
      "{$directory}/{$project}",
      '/.*\.info.yml$/',
      [
        'key' => 'name',
        'min_depth' => 0,
      ],
    );
    foreach ($files as $file) {
      // Get the .info.yml file for the module or theme this file belongs to.
      $info = $this->infoParser->parse($file->uri);
      // If the module or theme is incompatible with Drupal core, set an error.
      if ($info['core_incompatible']) {
        $incompatible[] = !empty($info['name']) ? $info['name'] : $this->t('Unknown');
      }
      else {
        $compatible_project = TRUE;
        break;
      }
    }
    if (empty($files)) {
      $errors[] = $this->t('%archive_file does not contain any .info.yml files.', ['%archive_file' => basename($archive_file)]);
    }
    elseif (!$compatible_project) {
      $errors[] = $this->translation->formatPlural(
        count($incompatible),
        '%archive_file contains a version of %names that is not compatible with Drupal @version.',
        '%archive_file contains versions of modules or themes that are not compatible with Drupal @version: %names',
        [
          '@version' => \Drupal::VERSION,
          '%archive_file' => basename($archive_file),
          '%names' => implode(', ', $incompatible),
        ],
      );
    }
    return $errors;
  }

}
