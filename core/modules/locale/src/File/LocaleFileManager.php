<?php

namespace Drupal\locale\File;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\locale\CurrentImportStorage;
use Drupal\locale\LocaleProjectRepository;
use Drupal\locale\LocaleSource;
use Drupal\locale\LocaleLanguages;
use Drupal\locale\StreamWrapper\TranslationsStream;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Provide Locale File helper methods.
 */
class LocaleFileManager {
  use StringTranslationTrait;

  public function __construct(
    protected readonly LocaleProjectRepository $localeProjectRepository,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ClientFactory $clientFactory,
    protected readonly ClientInterface $httpClient,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly MessengerInterface $messenger,
    protected readonly CurrentImportStorage $currentImportStorage,
    protected readonly LocaleLanguages $localeLanguages,
  ) {}

  /**
   * Get interface translation files present in the translations directory.
   *
   * @param array $projects
   *   (optional) Project names from which to get the translation files and
   *   history. Defaults to all projects.
   * @param array $langcodes
   *   (optional) Language codes from which to get the translation files and
   *   history. Defaults to all languages.
   *
   * @return array
   *   An array of interface translation files keyed by their URI.
   */
  public function getInterfaceTranslationFiles(array $projects = [], array $langcodes = []): array {
    $files = [];
    $projects = $projects ?: array_keys($this->localeProjectRepository->getAll());
    $langcodes = $langcodes ?: array_keys($this->localeLanguages->getTranslatableLanguages());

    // Scan the translations directory for files matching a name pattern
    // containing a project name and language code: {project}.{langcode}.po or
    // {project}-{version}.{langcode}.po.
    // Only files of known projects and languages will be returned.
    $directory = TranslationsStream::basePath();
    $result = [];
    if (is_dir($directory)) {
      $result = $this->fileSystem->scanDirectory($directory, '![a-z_]+(\-[0-9a-z\.\-\+]+|)\.[^\./]+\.po$!', ['recurse' => FALSE]);
    }

    foreach ($result as $file) {
      // Recreate file object, detect the project name and version from the file
      // name.
      $file = LocaleFile::createFromPath($file->filename, $file->uri);
      if (in_array($file->project, $projects) && in_array($file->langcode, $langcodes)) {
        $files[$file->uri] = $file;
      }
    }

    return $files;
  }

  /**
   * Deletes interface translation files and translation history records.
   *
   * @param array $projects
   *   (optional) Project names from which to delete the translation files and
   *   history. Defaults to all projects.
   * @param array $langcodes
   *   (optional) Language codes from which to delete the translation files and
   *   history. Defaults to all languages.
   *
   * @return bool
   *   TRUE if files are removed successfully. FALSE if one or more files could
   *   not be deleted.
   */
  public function deleteTranslationFiles(array $projects = [], array $langcodes = []): bool {
    $fail = FALSE;
    $this->currentImportStorage->delete($projects, $langcodes);

    // Delete all translation files from the translations directory.
    if ($files = $this->getInterfaceTranslationFiles($projects, $langcodes)) {
      foreach ($files as $file) {
        try {
          $this->fileSystem->delete($file->uri);
        }
        catch (FileException) {
          $fail = TRUE;
        }
      }
    }
    return !$fail;
  }

  /**
   * Check if remote file exists and when it was last updated.
   *
   * @param string $uri
   *   URI of remote file.
   *
   * @return \Drupal\locale\File\RemoteFileInfo
   *   RemoteFileInfo value object.
   */
  public function checkRemoteFileStatus(string $uri): RemoteFileInfo {
    $remoteFileInfo = new RemoteFileInfo();
    [$status, $response, $effective_uri] = $this->requestRemoteFile('HEAD', $uri);
    $remoteFileInfo->status = $status;
    if ($status === RemoteFileStatus::Success) {
      // Return the effective URL if it differs from the requested.
      $remoteFileInfo->location = $effective_uri;
      $remoteFileInfo->lastModified = $response->hasHeader('Last-Modified') ? strtotime($response->getHeaderLine('Last-Modified')) : 0;
    }
    return $remoteFileInfo;
  }

  /**
   * Downloads a translation file from a remote server.
   *
   * @param LocaleFile $source_file
   *   Source file object with at least:
   *   - "uri": uri to download the file from.
   *   - "project": Project name.
   *   - "langcode": Translation language.
   *   - "version": Project version.
   *   - "filename": File name.
   * @param string $directory
   *   Directory where the downloaded file will be saved. Defaults to the
   *   temporary file path.
   *
   * @return LocaleFile|false
   *   File object if download was successful. FALSE on failure.
   */
  public function downloadTranslationSource(LocaleFile $source_file, string $directory = 'translations://'): LocaleFile|false {
    try {
      $data = (string) $this->httpClient->request('GET', $source_file->uri)->getBody();
      if ($file = $this->saveTranslationFile($data, $source_file->uri, $directory, $source_file)) {
        return $file;
      }
    }
    catch (ClientExceptionInterface $exception) {
      $this->messenger->addError($this->t('Failed to fetch file due to error "%error"', ['%error' => $exception->getMessage()]));
    }
    catch (FileException | InvalidStreamWrapperException $e) {
      $this->messenger->addError($this->t('Failed to save file due to error "%error"', ['%error' => $e->getMessage()]));
    }
    $this->loggerFactory->get('locale')->error('Unable to download translation file @uri.', ['@uri' => $source_file->uri]);
    return FALSE;
  }

  /**
   * Fetches a remote translation file if modified since a given timestamp.
   *
   * Performs a single conditional GET request, providing the same information
   * in one request that checkRemoteFileStatus() followed by
   * downloadTranslationSource() provide in two. Unlike
   * downloadTranslationSource(), a missing remote file is a routine outcome
   * here and is logged as a notice rather than reported to the user as an
   * error.
   *
   * @param \Drupal\locale\File\LocaleFile $source_file
   *   Remote source file object with at least a uri.
   * @param int $since
   *   Timestamp for the If-Modified-Since request header. Use 0 to fetch
   *   unconditionally.
   * @param string $directory
   *   Directory where the downloaded file will be saved.
   *
   * @return \Drupal\locale\File\RemoteFileInfo
   *   RemoteFileInfo value object. Its status is Success when a file was
   *   downloaded (the file property then contains the saved local file),
   *   NotModified when the remote file has not changed since $since, Missing
   *   when the remote file does not exist, and Error otherwise. On success,
   *   lastModified and location are taken from the response.
   */
  public function fetchTranslationSource(LocaleFile $source_file, int $since = 0, string $directory = 'translations://'): RemoteFileInfo {
    $remoteFileInfo = new RemoteFileInfo();
    $headers = [];
    if ($since > 0) {
      $headers['If-Modified-Since'] = gmdate('D, d M Y H:i:s', $since) . ' GMT';
    }
    [$status, $response, $effective_uri] = $this->requestRemoteFile('GET', $source_file->uri, $headers);
    $remoteFileInfo->status = $status;
    if ($status !== RemoteFileStatus::Success) {
      return $remoteFileInfo;
    }

    $remoteFileInfo->location = $effective_uri;
    $remoteFileInfo->lastModified = $response->hasHeader('Last-Modified') ? strtotime($response->getHeaderLine('Last-Modified')) : 0;
    try {
      $file = $this->saveTranslationFile((string) $response->getBody(), $effective_uri ?? $source_file->uri, $directory, $source_file, $remoteFileInfo->lastModified ?: NULL);
      if ($file) {
        $remoteFileInfo->file = $file;
        return $remoteFileInfo;
      }
      $this->loggerFactory->get('locale')->error('Unable to save translation file @uri.', ['@uri' => $source_file->uri]);
    }
    catch (FileException | InvalidStreamWrapperException $e) {
      $this->loggerFactory->get('locale')->error('Unable to save translation file @uri: @message', [
        '@uri' => $source_file->uri,
        '@message' => $e->getMessage(),
      ]);
    }
    $remoteFileInfo->status = RemoteFileStatus::Error;
    return $remoteFileInfo;
  }

  /**
   * Performs an HTTP request to a translation server.
   *
   * Wraps the redirect tracking and error handling shared by the remote file
   * operations: a 404 response maps to RemoteFileStatus::Missing and other
   * request or connection failures map to RemoteFileStatus::Error, both
   * logged as notices. A 304 response maps to RemoteFileStatus::NotModified.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $uri
   *   URI of the remote file.
   * @param array $headers
   *   (optional) Request headers to send.
   *
   * @return array
   *   A list containing:
   *   - The RemoteFileStatus of the request.
   *   - The response, or NULL when the request failed.
   *   - The effective URI when redirects changed it, NULL otherwise.
   */
  protected function requestRemoteFile(string $method, string $uri, array $headers = []): array {
    $logger = $this->loggerFactory->get('locale');
    try {
      $actual_uri = NULL;
      $response = $this->clientFactory->fromOptions([
        // Translation files compress well, so advertise the compression
        // formats the client can decode and decode the response with them.
        'decode_content' => 'gzip, deflate, br',
        'allow_redirects' => [
          'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $request_uri) use (&$actual_uri) {
            $actual_uri = (string) $request_uri;
          },
        ],
      ])->request($method, $uri, ['headers' => $headers]);
      $status = $response->getStatusCode() === 304 ? RemoteFileStatus::NotModified : RemoteFileStatus::Success;
      return [$status, $response, ($actual_uri && $actual_uri !== $uri) ? $actual_uri : NULL];
    }
    catch (RequestException $e) {
      // Handle 4xx and 5xx http responses.
      $response = $e->getResponse();
      if ($response) {
        if ($response->getStatusCode() == 404) {
          // File not found occurs when a translation file is not yet available
          // at the translation server. But also if a custom module or custom
          // theme does not define the location of a translation file. By
          // default the file is checked at the translation server, but it will
          // not be found there.
          $logger->notice('Translation file not found: @uri.', ['@uri' => $uri]);
          return [RemoteFileStatus::Missing, NULL, NULL];
        }
        $logger->notice(
          'HTTP request to @url failed with error: @error.',
          [
            '@url' => $uri,
            '@error' => $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
          ]
        );
      }
    }
    // We need to handle ConnectException separately because in Guzzle 7 it
    // doesn't have a getResponse() method, so the above will fatal.
    catch (ConnectException $e) {
      $logger->notice('HTTP request to @url failed with error: @error.', ['@url' => $uri, '@error' => $e->getMessage()]);
    }
    return [RemoteFileStatus::Error, NULL, NULL];
  }

  /**
   * Saves downloaded translation file data and builds its local file record.
   *
   * @param string $data
   *   The file contents.
   * @param string $source_uri
   *   The URI the file was downloaded from; its basename becomes the local
   *   file name.
   * @param string $directory
   *   Directory where the file is saved.
   * @param \Drupal\locale\File\LocaleFile $source_file
   *   The remote source file object the download was made for.
   * @param int|null $timestamp
   *   (optional) The remote file's modification time. When given, it is
   *   recorded as the local file's timestamp and applied to the file itself,
   *   so later comparisons use the server's time rather than the download
   *   time. Defaults to the saved file's own modification time.
   *
   * @return \Drupal\locale\File\LocaleFile|false
   *   The saved local file record, or FALSE if the data could not be saved.
   */
  protected function saveTranslationFile(string $data, string $source_uri, string $directory, LocaleFile $source_file, ?int $timestamp = NULL): LocaleFile|false {
    $filename = basename($source_uri);
    if ($uri = $this->fileSystem->saveData($data, $directory . $filename, FileExists::Replace)) {
      if ($timestamp) {
        @touch($uri, $timestamp);
      }
      $hash = hash_file(LocaleSource::LOCAL_FILE_HASH_ALGO, $uri);
      $langcode = $source_file->langcode ?? LanguageInterface::LANGCODE_NOT_SPECIFIED;
      $file = new LocaleFile($filename, $uri, $hash, $timestamp ?: filemtime($uri), $langcode, $source_file->project ?? NULL, $source_file->version ?? NULL);
      $file->type = LOCALE_TRANSLATION_LOCAL;
      $file->directory = $directory;
      return $file;
    }
    return FALSE;
  }

}
