<?php

namespace Drupal\Core\Archiver;

// cspell:ignore filelist tarname typeflag

/**
 * Provides a PharData-based implementation for tar archive handling.
 *
 * Replaces the PEAR Archive_Tar dependency with PHP's built-in PharData.
 * Maintains the same public API for backward compatibility.
 */
class ArchiveTar {

  /**
   * The PharData instance for the archive.
   */
  private \PharData $pharData;

  /**
   * The final target path for the archive (may include compression extension).
   */
  private string $archivePath;

  /**
   * The path to the uncompressed .tar file used by PharData.
   */
  private string $tarPath;

  /**
   * The compression type: 'gz', 'bz2', or NULL for no compression.
   */
  private ?string $compressionType;

  /**
   * Constructs a new ArchiveTar instance.
   *
   * @param string $p_tarname
   *   The path to the tar archive.
   * @param string|null $p_compress
   *   The compression type: 'gz', 'bz2', or NULL for no compression.
   */
  public function __construct(string $p_tarname, ?string $p_compress = NULL) {
    $this->tarPath = $p_tarname;
    $this->archivePath = $p_tarname;
    $this->compressionType = match ($p_compress) {
      'gz', 'bz2' => $p_compress,
      default => NULL,
    };

    if (file_exists($p_tarname)) {
      // PharData requires a recognized file extension. If the file lacks one
      // (e.g. an uploaded temp file), copy it with the right extension so
      // PharData can detect the format.
      if (!preg_match('/\.(tar(\.gz|\.bz2)?|tgz)$/', $p_tarname)) {
        $ext = $this->compressionType !== NULL ? '.tar.' . $this->compressionType : '.tar';
        $this->tarPath = $p_tarname . $ext;
        copy($p_tarname, $this->tarPath);
      }
    }
    elseif ($this->compressionType !== NULL) {
      // PharData cannot write to compressed archives, so create an
      // uncompressed .tar and compress after each write operation.
      $this->tarPath = $this->deriveUncompressedPath($p_tarname);
    }
    $this->pharData = new \PharData($this->tarPath);
  }

  /**
   * Destructor. Cleans up the intermediate .tar file if present.
   */
  public function __destruct() {
    if ($this->tarPath !== $this->archivePath && file_exists($this->tarPath)) {
      @unlink($this->tarPath);
    }
  }

  /**
   * Lists the content of the archive.
   *
   * @return array
   *   An array of file information arrays, each containing:
   *   - filename: The relative path of the file within the archive.
   *   - size: The file size in bytes.
   *   - mtime: The last modification time.
   *   - typeflag: '5' for directories, '0' for files.
   */
  public function listContent(): array {
    $list = [];
    $prefix = strlen('phar://' . $this->pharData->getPath() . '/');
    /** @var \PharFileInfo $entry */
    foreach (new \RecursiveIteratorIterator($this->pharData) as $entry) {
      $list[] = [
        'filename' => substr($entry->getPathname(), $prefix),
        'size' => $entry->getSize(),
        'mtime' => $entry->getMTime(),
        'typeflag' => $entry->isDir() ? '5' : '0',
      ];
    }
    return $list;
  }

  /**
   * Extracts the complete archive to a directory.
   *
   * @param string $p_path
   *   The path to extract to. Defaults to current directory.
   *
   * @return bool
   *   TRUE on success.
   */
  public function extract(string $p_path = ''): bool {
    $this->pharData->extractTo($p_path ?: '.', NULL, TRUE);
    return TRUE;
  }

  /**
   * Extracts the archive with path modifications.
   *
   * @param string $p_path
   *   The path to extract to.
   * @param string $p_remove_path
   *   A path prefix to remove from extracted file paths.
   *
   * @return bool
   *   TRUE on success.
   */
  public function extractModify(string $p_path, string $p_remove_path): bool {
    if ($p_remove_path === '') {
      return $this->extract($p_path);
    }
    $files = array_column($this->listContent(), 'filename');
    return $this->extractList($files, $p_path, $p_remove_path);
  }

  /**
   * Extracts specific files from the archive.
   *
   * @param array $p_filelist
   *   The list of filenames to extract.
   * @param string $p_path
   *   The path to extract to. Defaults to current directory.
   * @param bool|string $p_remove_path
   *   A path prefix to remove from extracted file paths.
   *
   * @return bool
   *   TRUE on success.
   */
  public function extractList(array $p_filelist, string $p_path = '', bool|string $p_remove_path = ''): bool {
    $extractPath = $p_path ?: '.';

    if ($p_remove_path === '' || $p_remove_path === FALSE) {
      $this->pharData->extractTo($extractPath, $p_filelist, TRUE);
      return TRUE;
    }

    // PharData does not support path prefix removal, so extract to a
    // temporary directory and move files with adjusted paths.
    $tempDir = sys_get_temp_dir() . '/ArchiveTar_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0777, TRUE);
    try {
      $this->pharData->extractTo($tempDir, $p_filelist, TRUE);
      $p_remove_path = (string) $p_remove_path;
      $removeLen = strlen($p_remove_path);
      foreach ($p_filelist as $file) {
        $newPath = str_starts_with($file, $p_remove_path)
          ? substr($file, $removeLen)
          : $file;
        $targetFile = $extractPath . '/' . ltrim($newPath, '/');
        $targetDir = dirname($targetFile);
        if (!is_dir($targetDir)) {
          mkdir($targetDir, 0777, TRUE);
        }
        rename($tempDir . '/' . $file, $targetFile);
      }
    }
    finally {
      $this->removeDirectory($tempDir);
    }
    return TRUE;
  }

  /**
   * Adds a string as a file to the archive.
   *
   * @param string $p_filename
   *   The filename within the archive.
   * @param string $p_string
   *   The file content.
   *
   * @return bool
   *   TRUE on success.
   */
  public function addString(string $p_filename, string $p_string): bool {
    $this->pharData->addFromString($p_filename, $p_string);
    $this->compressArchive();
    return TRUE;
  }

  /**
   * Extracts a file from the archive and returns its content as a string.
   *
   * @param string $p_filename
   *   The filename to extract.
   *
   * @return string|null
   *   The file content, or NULL if the file is not found.
   */
  public function extractInString(string $p_filename): ?string {
    try {
      if (isset($this->pharData[$p_filename])) {
        return $this->pharData[$p_filename]->getContent();
      }
    }
    catch (\Exception) {
      // Fall through to return NULL.
    }
    return NULL;
  }

  /**
   * Creates a new archive from the given list of files.
   *
   * @param array|string $p_filelist
   *   A list of files to add, or a space-separated string of file paths.
   *
   * @return bool
   *   TRUE on success.
   */
  public function create(array|string $p_filelist): bool {
    $files = is_string($p_filelist) ? explode(' ', $p_filelist) : $p_filelist;
    foreach ($files as $file) {
      if ($file !== '') {
        $this->pharData->addFile($file);
      }
    }
    $this->compressArchive();
    return TRUE;
  }

  /**
   * Adds files to an existing archive.
   *
   * @param array|string $p_filelist
   *   A list of files to add, or a space-separated string of file paths.
   *
   * @return bool
   *   TRUE on success.
   */
  public function add(array|string $p_filelist): bool {
    return $this->create($p_filelist);
  }

  /**
   * Creates a new archive with path modifications.
   *
   * @param array|string $p_filelist
   *   A list of files to add, or a space-separated string of file paths.
   * @param string $p_add_dir
   *   Directory prefix to add within the archive.
   * @param string $p_remove_dir
   *   Directory prefix to remove from file paths.
   *
   * @return bool
   *   TRUE on success.
   */
  public function createModify(array|string $p_filelist, string $p_add_dir, string $p_remove_dir = ''): bool {
    $files = is_string($p_filelist) ? explode(' ', $p_filelist) : $p_filelist;
    foreach ($files as $file) {
      if ($file === '') {
        continue;
      }
      $localName = $file;
      if ($p_remove_dir !== '' && str_starts_with($file, $p_remove_dir)) {
        $localName = substr($file, strlen($p_remove_dir));
      }
      if ($p_add_dir !== '') {
        $localName = rtrim($p_add_dir, '/') . '/' . ltrim($localName, '/');
      }
      $this->pharData->addFile($file, $localName);
    }
    $this->compressArchive();
    return TRUE;
  }

  /**
   * Adds files to the archive with path modifications.
   *
   * @param array|string $p_filelist
   *   A list of files to add, or a space-separated string of file paths.
   * @param string $p_add_dir
   *   Directory prefix to add within the archive.
   * @param string $p_remove_dir
   *   Directory prefix to remove from file paths.
   *
   * @return bool
   *   TRUE on success.
   */
  public function addModify(array|string $p_filelist, string $p_add_dir, string $p_remove_dir = ''): bool {
    return $this->createModify($p_filelist, $p_add_dir, $p_remove_dir);
  }

  /**
   * Compresses the .tar archive to the target path.
   *
   * Called after every write operation to keep the compressed file in sync.
   * This is a no-op if no compression was requested.
   */
  private function compressArchive(): void {
    if ($this->compressionType === NULL) {
      return;
    }

    $compressedPath = $this->tarPath . '.' . $this->compressionType;

    // Remove existing compressed file so compress() can recreate it.
    if (file_exists($compressedPath)) {
      @unlink($compressedPath);
    }

    $pharCompression = match ($this->compressionType) {
      'gz' => \Phar::GZ,
      'bz2' => \Phar::BZ2,
    };
    $this->pharData->compress($pharCompression);

    // Move to the expected target path if it differs (e.g. for .tgz).
    if ($compressedPath !== $this->archivePath) {
      if (file_exists($this->archivePath)) {
        @unlink($this->archivePath);
      }
      rename($compressedPath, $this->archivePath);
    }
  }

  /**
   * Derives the uncompressed .tar path from a compressed archive path.
   *
   * @param string $path
   *   The compressed archive path (e.g. /tmp/config.tar.gz).
   *
   * @return string
   *   The uncompressed .tar path (e.g. /tmp/config.tar).
   */
  private function deriveUncompressedPath(string $path): string {
    // .tar.gz -> .tar, .tar.bz2 -> .tar
    if (preg_match('/\.tar\.(gz|bz2)$/', $path)) {
      return preg_replace('/\.(gz|bz2)$/', '', $path);
    }
    // .tgz -> .tar
    if (str_ends_with($path, '.tgz')) {
      return substr($path, 0, -4) . '.tar';
    }
    return $path . '.working.tar';
  }

  /**
   * Recursively removes a directory and its contents.
   *
   * @param string $directory
   *   The directory path to remove.
   */
  private function removeDirectory(string $directory): void {
    if (!is_dir($directory)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
      if ($item->isDir()) {
        @rmdir($item->getPathname());
      }
      else {
        @unlink($item->getPathname());
      }
    }
    @rmdir($directory);
  }

}
