<?php

namespace Drupal\Component\FileSystem;

/**
 * Versioned file handler.
 */
class VersionedFile {

  /**
   * Directory and prefix to the files.
   *
   * @var string
   */
  protected string $path;

  /**
   * The current version.
   *
   * @var string
   */
  protected string $currentVersion;

  public function __construct(protected string $prefix) {
    $this->path = FileSystem::getOsTemporaryDirectory(TRUE) . DIRECTORY_SEPARATOR . $this->prefix;
  }

  /**
   * Read the current version.
   *
   * @param callable|null $reader
   *   The reader callable. It receives two arguments, the filename to read
   *   and a path. It is not required to actually read a file, it is an
   *   identifier, nothing more.
   *
   * @return array|null
   *   The container definition if the read is successful.
   */
  public function readCurrentVersion(?callable $reader = NULL): ?array {
    $version = @file_get_contents($this->getVersionFilename());
    if ($version === FALSE) {
      $this->currentVersion = '';
      return NULL;
    }
    $this->currentVersion = $version;
    return $reader ? $reader($this->prefix . $version, $this->path) : NULL;
  }

  /**
   * Reset if the current version is lower than the version passed in.
   *
   * @param int $version
   *   The version to compare to.
   */
  public function resetIfNecessary(int $version): void {
    if (!isset($this->currentVersion)) {
      $this->readCurrentVersion();
    }
    if ((int) $this->currentVersion < $version) {
      $this->reset();
    }
  }

  /**
   * Write new version.
   *
   * @param callable $writer
   *   The writer callable. It receives three arguments, the filename to write,
   *   the data to write and a path. The path points to a writable directory
   *   and ends with a prefix, do not use a slash. It is not required to
   *   actually write a file, it is an identifier, nothing more. The reader
   *   will receive the same filename and path and it should be able to read
   *   from it based on this.
   * @param callable $remove
   *   The remove callable. It receives a filename and a path and removes the
   *   file the writer wrote based on that.
   * @param string $data
   *   The data to be stored.
   * @param int $version
   *   The new version.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function writeNewVersion(callable $writer, callable $remove, string $data, int $version): bool {
    $dataFilename = $this->prefix . $version;
    $writer($dataFilename, $data, $this->path);
    try {
      if (!isset($this->currentVersion)) {
        $this->readCurrentVersion();
      }
      $claimPath = "$this->path.claim.$this->currentVersion";
      if (!$claim = @fopen($claimPath, 'x')) {
        throw new \Exception('Another process is currently writing the new version.');
      }
      fclose($claim);
      if ((string) @file_get_contents($this->getVersionFilename()) !== $this->currentVersion) {
        throw new \Exception('Another process has already written a new version.');
      }
      $tmp = tempnam(dirname($this->path), '.version');
      if (!$handle = @fopen($tmp, 'w')) {
        throw new \Exception('Failed to open temporary file.');
      }
      fwrite($handle, (string) $version);
      fsync($handle);
      fclose($handle);
      return @rename($tmp, $this->getVersionFilename());
    }
    catch (\Throwable) {
      $remove($dataFilename, $this->path);
      return FALSE;
    }
    finally {
      if (isset($claimPath)) {
        @unlink($claimPath);
      }
      if (isset($tmp)) {
        @unlink($tmp);
      }
    }
  }

  /**
   * Reset.
   */
  public function reset(): void {
    @unlink($this->getVersionFilename());
  }

  /**
   * Get the name with full path to the version file.
   */
  protected function getVersionFilename(): string {
    return "$this->path.version'";
  }

}
