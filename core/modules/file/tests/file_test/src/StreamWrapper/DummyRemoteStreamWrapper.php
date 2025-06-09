<?php

declare(strict_types=1);

namespace Drupal\file_test\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;

/**
 * Helper class for testing the stream wrapper registry.
 *
 * Dummy remote stream wrapper implementation (dummy-remote://).
 *
 * This class contains substantial boilerplate from LocalStream, and indeed
 * uses local file storage under the hood (for testing expediency), but this
 * stream wrapper should not _actually_ extend LocalStream, as PublicStream
 * does. There is little utility in splitting those methods into a trait for
 * only for a single use, here.
 */
class DummyRemoteStreamWrapper implements StreamWrapperInterface {

  /**
   * Stream context resource.
   *
   * @var resource
   */
  public $context;

  /**
   * A generic resource handle.
   *
   * @var resource
   */
  protected $handle = NULL;

  /**
   * Instance URI (stream).
   *
   * A stream is referenced as "scheme://target".
   *
   * @var string
   */
  protected $uri;

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'Dummy files (remote)';
  }

  public static function getType() {
    // Notably does not include LOCAL.
    return StreamWrapperInterface::WRITE_VISIBLE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return 'Dummy wrapper for testing (remote).';
  }

  public function realpath() {
    // Even though this mock uses local storage, emulate being remote.
    return FALSE;
  }

  public function dir_closedir() {
    closedir($this->handle);
    // We do not really have a way to signal a failure as closedir() does not
    // have a return value.
    return TRUE;
  }

  public function dir_opendir($path, $options) {
    $this->uri = $path;
    $this->handle = opendir($this->getLocalPath());

    return (bool) $this->handle;
  }

  public function dir_readdir() {
    return readdir($this->handle);
  }

  public function dir_rewinddir() {
    rewinddir($this->handle);
    // We do not really have a way to signal a failure as rewinddir() does not
    // have a return value and there is no way to read a directory handler
    // without advancing to the next file.
    return TRUE;
  }

  public function mkdir($path, $mode, $options) {
    $this->uri = $path;
    $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
    if ($recursive) {
      // $this->getLocalPath() fails if $path has multiple levels of directories
      // that do not yet exist.
      $local_path = $this->getDirectoryPath() . '/' . $this->getTarget($path);
    }
    else {
      $local_path = $this->getLocalPath($path);
    }
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    if ($options & STREAM_REPORT_ERRORS) {
      return $file_system->mkdir($local_path, $mode, $recursive);
    }
    else {
      return @$file_system->mkdir($local_path, $mode, $recursive);
    }
  }

  public function rename($path_from, $path_to) {
    return rename($this->getLocalPath($path_from), $this->getLocalPath($path_to));
  }

  public function rmdir($path, $options) {
    $this->uri = $path;
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    if ($options & STREAM_REPORT_ERRORS) {
      return $file_system->rmdir($this->getLocalPath());
    }
    else {
      return @$file_system->rmdir($this->getLocalPath());
    }
  }

  public function stream_cast($cast_as) {
    return $this->handle ?: FALSE;
  }

  public function stream_close() {
    return fclose($this->handle);
  }

  public function stream_eof() {
    return feof($this->handle);
  }

  public function stream_flush() {
    return fflush($this->handle);
  }

  public function stream_lock($operation) {
    if (in_array($operation, [LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB])) {
      return flock($this->handle, $operation);
    }

    return TRUE;
  }

  public function stream_metadata($path, $option, $value) {
    $target = $this->getLocalPath($path);
    $return = FALSE;
    switch ($option) {
      case STREAM_META_TOUCH:
        if (!empty($value)) {
          $return = touch($target, $value[0], $value[1]);
        }
        else {
          $return = touch($target);
        }
        break;

      case STREAM_META_OWNER_NAME:
      case STREAM_META_OWNER:
        $return = chown($target, $value);
        break;

      case STREAM_META_GROUP_NAME:
      case STREAM_META_GROUP:
        $return = chgrp($target, $value);
        break;

      case STREAM_META_ACCESS:
        $return = chmod($target, $value);
        break;
    }
    if ($return) {
      // For convenience clear the file status cache of the underlying file,
      // since metadata operations are often followed by file status checks.
      clearstatcache(TRUE, $target);
    }
    return $return;  }

  public function stream_open($path, $mode, $options, &$opened_path) {
    $this->uri = $path;
    $path = $this->getLocalPath();
    if ($path === FALSE) {
      if ($options & STREAM_REPORT_ERRORS) {
        trigger_error('stream_open() filename cannot be empty', E_USER_WARNING);
      }
      return FALSE;
    }
    $this->handle = ($options & STREAM_REPORT_ERRORS) ? fopen($path, $mode) : @fopen($path, $mode);

    if ($this->handle && $options & STREAM_USE_PATH) {
      $opened_path = $path;
    }

    return (bool) $this->handle;
  }

  public function stream_read($count) {
    return fread($this->handle, $count);
  }

  public function stream_seek($offset, $whence = SEEK_SET) {
    // fseek() returns 0 on success and -1 on a failure.
    // stream_seek()   1 on success and  0 on a failure.
    return !fseek($this->handle, $offset, $whence);
  }

  public function stream_set_option($option, $arg1, $arg2): false {
    return FALSE;
  }

  public function stream_stat() {
    return fstat($this->handle);
  }

  public function stream_tell() {
    return ftell($this->handle);
  }

  public function stream_truncate($new_size) {
    return ftruncate($this->handle, $new_size);
  }

  public function stream_write($data) {
    return fwrite($this->handle, $data);
  }

  public function unlink($path) {
    $this->uri = $path;
    return $this->getFileSystem()->unlink($this->getLocalPath());  }

  protected function getTarget($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    [, $target] = explode('://', $uri, 2);

    // Remove erroneous leading or trailing, forward-slashes and backslashes.
    return trim($target, '\/');
  }

  protected function getDirectoryPath(): string {
    return \Drupal::getContainer()->getParameter('site.path') . '/dummy-remote-stream-wrapper';
  }

  protected function getLocalPath($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }
    $path = $this->getDirectoryPath() . '/' . $this->getTarget($uri);

    // In PHPUnit tests, the base path for local streams may be a virtual
    // filesystem stream wrapper URI, in which case this local stream acts like
    // a proxy. realpath() is not supported by vfsStream, because a virtual
    // file system does not have a real filepath.
    if (str_starts_with($path, 'vfs://')) {
      return $path;
    }

    $realpath = realpath($path);
    if (!$realpath) {
      // This file does not yet exist.
      $realpath = realpath(dirname($path)) . '/' . \Drupal::service('file_system')->basename($path);
    }
    $directory = realpath($this->getDirectoryPath());
    if (!$realpath || !$directory || !str_starts_with($realpath, $directory)) {
      return FALSE;
    }
    return $realpath;
  }

  public function url_stat($path, $flags) {
    $this->uri = $path;
    $path = $this->getLocalPath();
    // Suppress warnings if requested or if the file or directory does not
    // exist. This is consistent with PHP's plain filesystem stream wrapper.
    if ($flags & STREAM_URL_STAT_QUIET || !file_exists($path)) {
      return @stat($path);
    }
    else {
      return stat($path);
    }
  }

  public function setUri($uri) {
    $this->uri = $uri;
  }

  public function getUri() {
    return $this->uri;
  }

  public function getExternalUrl() {
    // This is an example of the special-casing a stream wrapper may need to
    // do in order to keep Drupal "in line" with the request, so assets may be
    // generated on first request.
    if (StreamWrapperManager::getScheme($this->uri) === 'assets') {
      $target = $this->getTarget($this->uri);
      return Url::fromRoute(
        sprintf('system.%s_asset', current(explode(DIRECTORY_SEPARATOR, $target, 2))),
        ['file_name' => basename($target)],
      )
        ->toString(TRUE)
        ->getGeneratedUrl();
    }
    return 'https://dummy-remote.example.com/' . $this->getTarget($this->uri);
  }

  public function dirname($uri = NULL): false {
    return FALSE;
  }

}
