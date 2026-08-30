<?php

namespace Drupal\locale;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\locale\File\LocaleFileManager;
use Drupal\locale\File\RemoteFileStatus;

/**
 * Provides the locale fetch services.
 */
class LocaleFetch {

  use StringTranslationTrait;

  public function __construct(
    protected readonly LocaleProjectRepository $localeProjectRepository,
    protected readonly ModuleExtensionList $moduleExtensionList,
    protected readonly LocaleFileManager $localeFileManager,
    protected readonly LocaleSource $localeSource,
    protected readonly StateInterface $state,
    protected readonly TimeInterface $time,
    protected readonly LocaleImportBatch $localeImportBatch,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LocaleLanguages $localeLanguages,
  ) {}

  /**
   * Builds a batch to check, download and import project translations.
   *
   * @param array $projects
   *   Array of project names for which to update the translations. Defaults to
   *   all translatable projects.
   * @param array $langcodes
   *   Array of language codes. Defaults to all translatable languages.
   * @param array $options
   *   Array of import options. See
   *   \Drupal\locale\LocaleImportBatch::buildBatch().
   *
   * @return array
   *   Batch definition array.
   */
  public function buildUpdateBatch(array $projects = [], array $langcodes = [], array $options = []): array {
    $projects = $projects ?: array_keys($this->localeProjectRepository->getAll());
    $langcodes = $langcodes ?: array_keys($this->localeLanguages->getTranslatableLanguages());

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Updating translations'))
      ->setErrorMessage($this->t('Error importing translation files'))
      ->setFinishCallback(self::class . ':batchFinished');
    // Check the status of local and remote translation files and download
    // newer remote files in a single operation per project and language,
    // using a conditional GET request, then import the result. The version
    // check must run first so that stale files of an old project version are
    // purged before the fetch decides what is current.
    foreach ($projects as $project) {
      foreach ($langcodes as $langcode) {
        $batch_builder->addOperation(self::class . ':batchVersionCheck', [$project, $langcode]);
        $batch_builder->addOperation(self::class . ':batchConditionalFetch', [$project, $langcode]);
        $batch_builder->addOperation(self::class . ':batchImport', [$project, $langcode, $options]);
      }
    }

    return $batch_builder->toArray();
  }

  /**
   * Builds a batch to download and import project translations.
   *
   * @param array $projects
   *   Array of project names for which to check the state of translation files.
   *   Defaults to all translatable projects.
   * @param array $langcodes
   *   Array of language codes. Defaults to all translatable languages.
   * @param array $options
   *   Array of import options. See
   *   \Drupal\locale\LocaleImportBatch::buildBatch().
   *
   * @return array
   *   Batch definition array.
   */
  public function buildFetchBatch(array $projects = [], array $langcodes = [], array $options = []): array {
    $projects = $projects ?: array_keys($this->localeProjectRepository->getAll());
    $langcodes = $langcodes ?: array_keys($this->localeLanguages->getTranslatableLanguages());

    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Updating translations.'))
      ->setErrorMessage($this->t('Error importing translation files'))
      ->setFinishCallback(self::class . ':batchFinished');
    $operations = $this->getFetchOperations($projects, $langcodes, $options);

    foreach ($operations as $operation) {
      $batch_builder->addOperation(... $operation);
    }
    return $batch_builder->toArray();
  }

  /**
   * Helper function to construct the batch operations to fetch translations.
   *
   * @param array $projects
   *   Array of project names for which to check the state of translation files.
   *   Defaults to all translatable projects.
   * @param array $langcodes
   *   Array of language codes. Defaults to all translatable languages.
   * @param array $options
   *   Array of import options.
   *
   * @return array
   *   Array of batch operations.
   */
  protected function getFetchOperations(array $projects, array $langcodes, array $options): array {
    $operations = [];
    $useRemote = $this->configFactory->get('locale.settings')->get('translation.use_source') == LOCALE_TRANSLATION_USE_SOURCE_REMOTE_AND_LOCAL;

    foreach ($projects as $project) {
      foreach ($langcodes as $langcode) {
        if ($useRemote) {
          $operations[] = [self::class . ':batchDownload', [$project, $langcode]];
        }
        $operations[] = [self::class . ':batchImport', [$project, $langcode, $options]];
      }
    }

    return $operations;
  }

  /**
   * Constructs batch operations for checking remote translation status.
   *
   * @param array $projects
   *   Array of project names to be processed.
   * @param array $langcodes
   *   Array of language codes.
   * @param array $options
   *   Batch processing options.
   *
   * @return array
   *   Array of batch operations.
   */
  public function getStatusOperations(array $projects, array $langcodes, array $options = []): array {
    $operations = [];

    foreach ($projects as $project) {
      foreach ($langcodes as $langcode) {
        // Check version and status translation sources.
        $operations[] = [self::class . ':batchVersionCheck', [$project, $langcode]];
        $operations[] = [self::class . ':batchStatusCheck', [$project, $langcode, $options]];
      }
    }

    return $operations;
  }

  /**
   * Implements callback_batch_operation().
   *
   * Downloads a remote gettext file into the translations directory. When
   * successfully the translation status is updated.
   *
   * @param string $project
   *   The name of the project to import translations.
   * @param string $langcode
   *   Language code.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\locale\LocaleFetch::batchImport()
   */
  public function batchDownload(string $project, string $langcode, array|\ArrayAccess &$context): void {
    $source = $this->localeSource->loadSource($project, $langcode);
    if (isset($source->type) && $source->type == LOCALE_TRANSLATION_REMOTE) {
      if ($file = $this->localeFileManager->downloadTranslationSource($source->files[LOCALE_TRANSLATION_REMOTE], 'translations://')) {
        $context['message'] = $this->t('Downloaded %langcode translation for %project.', [
          '%langcode' => $langcode,
          '%project' => $source->project,
        ]);
        $this->localeSource->saveSource($source->name, $source->langcode, LOCALE_TRANSLATION_LOCAL, $file);
      }
      else {
        $context['results']['failed_files'][] = $source->files[LOCALE_TRANSLATION_REMOTE];
      }
    }
  }

  /**
   * Implements callback_batch_operation().
   *
   * Imports a gettext file from the translation directory. When successful the
   * translation status is updated.
   *
   * @param string $project
   *   The name of the project to import translations.
   * @param string $langcode
   *   Language code.
   * @param array $options
   *   Array of import options.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\locale\LocaleImportBatch::buildBatch()
   * @see \Drupal\locale\LocaleFetch::batchDownload()
   */
  public function batchImport(string $project, string $langcode, array $options, array|\ArrayAccess &$context): void {
    $source = $this->localeSource->loadSource($project, $langcode);
    if ($source->type == LOCALE_TRANSLATION_REMOTE || $source->type == LOCALE_TRANSLATION_LOCAL) {
      $file = $source->files[LOCALE_TRANSLATION_LOCAL];
      $options += [
        'message' => $this->t('Importing %langcode translation for %project.', [
          '%langcode' => $langcode,
          '%project' => $source->project,
        ]),
      ];
      // Import the translation file. For large files the batch operations
      // is progressive and will be called repeatedly until finished.
      $this->localeImportBatch->batchImport($file, $options, $context);

      // The import is finished.
      if (isset($context['finished']) && $context['finished'] == 1) {
        // The import is successful.
        if (isset($context['results']['files'][$file->uri])) {
          $context['message'] = $this->t('Imported %langcode translation for %project.', [
            '%langcode' => $langcode,
            '%project' => $source->project,
          ]);

          // Save the data of imported source into the {locale_file} table
          // and update the current translation status.
          $this->localeSource->saveSource($project, $langcode, LOCALE_TRANSLATION_CURRENT, $source->files[LOCALE_TRANSLATION_LOCAL]);
        }
      }
    }
    elseif ($source->type == LOCALE_TRANSLATION_CURRENT) {
      /*
       * This can happen if the \Drupal\locale\LocaleFetch::batchImport()
       * batch was interrupted
       * and the translation was imported by another batch.
       */
      $context['message'] = $this->t('Ignoring already imported translation for %project.', ['%project' => $source->project]);
      $context['finished'] = 1;
    }
  }

  /**
   * Implements callback_batch_finished().
   *
   * Set result message.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   */
  public function batchFinished(bool $success, array $results): void {
    if ($success) {
      $this->localeSource->updateLastChecked();
    }
    $this->localeImportBatch->batchFinished($success, $results);
  }

  /**
   * Implements callback_batch_operation().
   *
   * Checks for changed project versions, and cleans-up data from the old
   * version. For example when a module is updated. This will make the
   * translation import system use translations that match the current version.
   *
   * @param string $project
   *   Machine name of the project for which to check the translation status.
   * @param string $langcode
   *   Language code of the language for which to check the translation.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public function batchVersionCheck(string $project, string $langcode, array|\ArrayAccess &$context): void {
    $locale_project = $this->localeProjectRepository->getMultiple([$project])[$project] ?? NULL;

    if (!($locale_project instanceof LocaleTranslatableProject)) {
      return;
    }

    $source = $this->localeSource->loadSource($project, $langcode);
    if ($locale_project->version == $source->version) {
      return;
    }

    $this->localeSource->deleteSources([$project]);
    $this->localeFileManager->deleteTranslationFiles([$project]);

    $context['message'] = $this->t('Checked version of %project.', ['%project' => $project]);
  }

  /**
   * Implements callback_batch_operation().
   *
   * Checks the status of a project's translation and fetches it if newer.
   *
   * Combines batchStatusCheck() and batchDownload() into a single operation
   * for flows that check and fetch in direct succession, such as the
   * installer and cron. A single conditional GET request replaces the HEAD
   * request followed by a GET request that the separate operations perform.
   *
   * @param string $project
   *   The name of the project to check and fetch translations for.
   * @param string $langcode
   *   Language code.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\locale\LocaleFetch::batchImport()
   */
  public function batchConditionalFetch(string $project, string $langcode, array|\ArrayAccess &$context): void {
    $source = $this->localeSource->loadSource($project, $langcode);

    // Refresh the status of a local translation file, if any.
    if (isset($source->files[LOCALE_TRANSLATION_LOCAL])) {
      if ($file = $this->localeSource->sourceCheckFile($source)) {
        $this->localeSource->saveSource($source->name, $source->langcode, LOCALE_TRANSLATION_LOCAL, $file);
      }
    }

    $context['message'] = $this->t('Checked %langcode translation for %project.', [
      '%langcode' => $langcode,
      '%project' => $source->project,
    ]);

    $use_remote = $this->configFactory->get('locale.settings')->get('translation.use_source') == LOCALE_TRANSLATION_USE_SOURCE_REMOTE_AND_LOCAL;
    if (!$use_remote || !isset($source->files[LOCALE_TRANSLATION_REMOTE])) {
      $context['results']['files'][] = $source->name;
      return;
    }

    $remote_file = $source->files[LOCALE_TRANSLATION_REMOTE];
    // drupal.org does not serve English translations, so do not make a
    // request at all for the default server pattern. A custom server pattern
    // may support English and is still checked.
    if ($langcode === 'en') {
      $uri = $this->localeSource->buildServerPattern($source, strtr(\Drupal::TRANSLATION_DEFAULT_SERVER_PATTERN, ['%language' => $langcode]));
      if ($uri === $remote_file->uri) {
        $context['results']['failed_files'][] = $source->name;
        return;
      }
    }

    // The stored remote timestamp is the server's modification time from a
    // previous check or download. Local file times are deliberately not used
    // as a fallback: they record when a file was written locally, not when
    // its content was generated on the server (a file shipped by a
    // distribution can carry a recent time for old content), so they cannot
    // be trusted for an If-Modified-Since comparison. Without a stored
    // remote timestamp the file is fetched unconditionally.
    $since = (int) ($remote_file->timestamp ?? 0);

    $info = $this->localeFileManager->fetchTranslationSource($remote_file, $since);
    switch ($info->status) {
      case RemoteFileStatus::Success:
        // Record the remote file's data. In case of a redirect we store the
        // resulting uri.
        $remote_file->uri = $info->location ?? $remote_file->uri;
        $remote_file->timestamp = $info->lastModified;
        $this->localeSource->saveSource($source->name, $source->langcode, LOCALE_TRANSLATION_REMOTE, $remote_file);
        $this->localeSource->saveSource($source->name, $source->langcode, LOCALE_TRANSLATION_LOCAL, $info->file);
        $context['results']['files'][] = $source->name;
        $context['message'] = $this->t('Downloaded %langcode translation for %project.', [
          '%langcode' => $langcode,
          '%project' => $source->project,
        ]);
        break;

      case RemoteFileStatus::NotModified:
      case RemoteFileStatus::Missing:
        // Nothing newer to fetch; a missing file is a routine outcome for
        // projects without a translation on the server.
        $context['results']['files'][] = $source->name;
        break;

      default:
        $context['results']['failed_files'][] = $source->name;
    }
  }

  /**
   * Implements callback_batch_operation().
   *
   * Checks the presence and creation time po translation files in located at
   * remote server location and local file system.
   *
   * @param string $project
   *   Machine name of the project for which to check the translation status.
   * @param string $langcode
   *   Language code of the language for which to check the translation.
   * @param array $options
   *   An array with options that can have the following elements:
   *   - 'finish_feedback': Whether or not to give feedback to the user when the
   *     batch is finished. Optional, defaults to TRUE.
   *   - 'use_remote': Whether or not to check the remote translation file.
   *     Optional, defaults to TRUE.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public function batchStatusCheck(string $project, string $langcode, array $options, array|\ArrayAccess &$context): void {
    $failure = $checked = FALSE;
    $options += [
      'finish_feedback' => TRUE,
      'use_remote' => $this->configFactory->get('locale.settings')->get('translation.use_source') == LOCALE_TRANSLATION_USE_SOURCE_REMOTE_AND_LOCAL,
    ];
    $source = $this->localeSource->loadSource($project, $langcode);

    // Check the status of local translation files.
    if (isset($source->files[LOCALE_TRANSLATION_LOCAL])) {
      if ($file = $this->localeSource->sourceCheckFile($source)) {
        $this->localeSource->saveSource($source->name, $source->langcode, LOCALE_TRANSLATION_LOCAL, $file);
      }
      $checked = TRUE;
    }

    // Check the status of remote translation files.
    if ($options['use_remote'] && isset($source->files[LOCALE_TRANSLATION_REMOTE])) {
      $remote_file = $source->files[LOCALE_TRANSLATION_REMOTE];
      if ($langcode === 'en') {
        // drupal.org does not support english as translation.
        $uri = $this->localeSource->buildServerPattern($source, strtr(\Drupal::TRANSLATION_DEFAULT_SERVER_PATTERN, ['%language' => $langcode]));
        if ($uri === $remote_file->uri) {
          $failure = TRUE;
        }
      }

      // Do not make a request at all when the source is already known to be
      // unusable, such as English on the default server pattern.
      $remoteFileInfo = $failure ? NULL : $this->localeFileManager->checkRemoteFileStatus($remote_file->uri);
      if ($remoteFileInfo && $remoteFileInfo->status !== RemoteFileStatus::Error) {
        // Update the file object with the result data. In case of a redirect we
        // store the resulting uri.
        if ($remoteFileInfo->lastModified) {
          $remote_file->uri = $remoteFileInfo->location ?? $remote_file->uri;
          $remote_file->timestamp = $remoteFileInfo->lastModified;
          $this->localeSource->saveSource($source->name, $source->langcode, LOCALE_TRANSLATION_REMOTE, $remote_file);
        }
        // @todo What to do with when the file is not found (404)? To prevent
        //   re-checking within the TTL (1day, 1week) we can set a last_checked
        //   timestamp or cache the result.
        $checked = TRUE;
      }
      else {
        $failure = TRUE;
      }
    }

    // Provide user feedback and record success or failure for reporting at the
    // end of the batch.
    if ($options['finish_feedback'] && $checked) {
      $context['results']['files'][] = $source->name;
    }
    if ($failure && !$checked) {
      $context['results']['failed_files'][] = $source->name;
    }
    $context['message'] = $this->t('Checked %langcode translation for %project.', [
      '%langcode' => $langcode,
      '%project' => $source->project,
    ]);
  }

}
