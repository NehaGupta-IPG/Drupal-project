<?php

declare(strict_types=1);

namespace Drupal\Core\File;

/**
 * Provides a class for checking file permissions.
 *
 * This class provides methods to verify and fix file and directory permissions,
 * including checks for existence, readability, writability, and executability.
 */
class FilePermissions {

  /**
   * File permission check -- File exists.
   */
  const EXIST = 1;

  /**
   * File permission check -- File is readable.
   */
  const READABLE = 2;

  /**
   * File permission check -- File is writable.
   */
  const WRITABLE = 4;

  /**
   * File permission check -- File is executable.
   */
  const EXECUTABLE = 8;

  /**
   * File permission check -- File does not exist.
   */
  const NOT_EXIST = 16;

  /**
   * File permission check -- File is not readable.
   */
  const NOT_READABLE = 32;

  /**
   * File permission check -- File is not writable.
   */
  const NOT_WRITABLE = 64;

  /**
   * File permission check -- File is not executable.
   */
  const NOT_EXECUTABLE = 128;

  public function __construct(
    protected readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Returns an array of all permission masks.
   *
   * This method returns an array of constants representing the various file
   * permission checks that can be performed.
   *
   * @return array<int>
   *   An array of permission masks.
   */
  public static function masks(): array {
    return [
      FilePermissions::READABLE,
      FilePermissions::WRITABLE,
      FilePermissions::EXECUTABLE,
      FilePermissions::NOT_READABLE,
      FilePermissions::NOT_WRITABLE,
      FilePermissions::NOT_EXECUTABLE,
    ];
  }

  public function verifyFile($file, ?int $mask = NULL, bool $auto_fix = TRUE): bool {
    return self::verify($file, $mask, FileType::File, $auto_fix);
  }

  public function verifyDirectory($directory, $mask = NULL, bool $auto_fix = TRUE): bool {
    return self::verify($directory, $mask, FileType::Directory, $auto_fix);
  }

  /**
   * Verifies the permissions of the specified file or directory.
   *
   * @param string $file
   *   The file to check for.
   * @param int|null $mask
   *   An optional bitmask created from various FILE_* constants.
   * @param \Drupal\Core\File\FileType|null $type
   *   The type of file. Can be file (default), dir, or link.
   * @param bool $auto_fix
   *   (optional) Determines whether to attempt fixing the permissions according
   *   to the provided $mask. Defaults to TRUE.
   *
   * @return bool
   *   TRUE on success or FALSE on failure. A message is set for the latter.
   */
  public function verify(string $file, ?int $mask = NULL, ?FileType $type = FileType::File, bool $auto_fix = TRUE): bool {
    $return = TRUE;
    // Check for files that shouldn't be there.
    if (isset($mask) && ($mask & self::NOT_EXIST) && file_exists($file)) {
      return FALSE;
    }
    // Verify that the file is the type of file it is supposed to be.
    if (isset($type) && file_exists($file)) {
      $check = 'is_' . $type->value;
      if (!function_exists($check) || !$check($file)) {
        $return = FALSE;
      }
    }

    // Verify file permissions.
    if (isset($mask)) {
      $masks = [
        self::EXIST,
        self::READABLE,
        self::WRITABLE,
        self::EXECUTABLE,
        self::NOT_READABLE,
        self::NOT_WRITABLE,
        self::NOT_EXECUTABLE,
      ];
      foreach ($masks as $current_mask) {
        if ($mask & $current_mask) {
          switch ($current_mask) {
            case self::EXIST:
              if (!file_exists($file)) {
                if ($type == 'dir' && $auto_fix) {
                  $this->mkdir($file, $mask);
                }
                if (!file_exists($file)) {
                  $return = FALSE;
                }
              }
              break;

            case self::READABLE:
              if (!is_readable($file)) {
                $return = FALSE;
              }
              break;

            case self::WRITABLE:
              if (!is_writable($file)) {
                $return = FALSE;
              }
              break;

            case self::EXECUTABLE:
              if (!is_executable($file)) {
                $return = FALSE;
              }
              break;

            case self::NOT_READABLE:
              if (is_readable($file)) {
                $return = FALSE;
              }
              break;

            case self::NOT_WRITABLE:
              if (is_writable($file)) {
                $return = FALSE;
              }
              break;

            case self::NOT_EXECUTABLE:
              if (is_executable($file)) {
                $return = FALSE;
              }
              break;
          }
        }
      }
    }
    if (!$return && $auto_fix) {
      return self::fix($file, $mask);
    }
    return $return;
  }

  /**
   * Attempts to create a directory with the specified permissions.
   *
   * This function attempts to create a directory with the specified file
   * permissions. If the directory already exists, it will not attempt to
   * change its permissions.
   *
   * @param string $file
   *   The name of the directory to create.
   * @param int $mask
   *   The desired permissions for the directory.
   *
   * @return bool
   *   TRUE if the directory was created successfully, FALSE otherwise.
   */
  public function mkdir(string $file, int $mask): bool {
    $mod = 0;
    $masks = FilePermissions::masks();
    foreach ($masks as $m) {
      if ($mask & $m) {
        switch ($m) {
          case FilePermissions::READABLE:
            $mod |= 0444;
            break;

          case FilePermissions::WRITABLE:
            $mod |= 0222;
            break;

          case FilePermissions::EXECUTABLE:
            $mod |= 0111;
            break;
        }
      }
    }

    if ($this->fileSystem->mkdir($file, $mod)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Attempts to fix file permissions.
   *
   * The general approach here is that, because we do not know the security
   * setup of the webserver, we apply our permission changes to all three
   * digits of the file permission (i.e. user, group and all).
   *
   * To ensure that the values behave as expected (and numbers don't carry
   * from one digit to the next) we do the calculation on the octal value
   * using bitwise operations. This lets us remove, for example, 0222 from
   * 0700 and get the correct value of 0500.
   *
   * @param string $file
   *   The name of the file with permissions to fix.
   * @param int|null $mask
   *   The desired permissions for the file.
   *
   * @return bool
   *   TRUE/FALSE whether or not we were able to fix the file's permissions.
   */
  public function fix(string $file, ?int $mask): bool {
    // If $file does not exist, fileperms() issues a PHP warning.
    if (!file_exists($file)) {
      return FALSE;
    }

    $mod = fileperms($file) & 0777;
    $masks = [
      self::READABLE,
      self::WRITABLE,
      self::EXECUTABLE,
      self::NOT_READABLE,
      self::NOT_WRITABLE,
      self::NOT_EXECUTABLE,
    ];

    // FILE_READABLE, FILE_WRITABLE, and FILE_EXECUTABLE permission strings
    // can theoretically be 0400, 0200, and 0100 respectively, but to be safe
    // we set all three access types in case the administrator intends to
    // change the owner of settings.php after installation.
    foreach ($masks as $m) {
      if ($mask & $m) {
        switch ($m) {
          case self::READABLE:
            if (!is_readable($file)) {
              $mod |= 0444;
            }
            break;

          case self::WRITABLE:
            if (!is_writable($file)) {
              $mod |= 0222;
            }
            break;

          case self::EXECUTABLE:
            if (!is_executable($file)) {
              $mod |= 0111;
            }
            break;

          case self::NOT_READABLE:
            if (is_readable($file)) {
              $mod &= ~0444;
            }
            break;

          case self::NOT_WRITABLE:
            if (is_writable($file)) {
              $mod &= ~0222;
            }
            break;

          case self::NOT_EXECUTABLE:
            if (is_executable($file)) {
              $mod &= ~0111;
            }
            break;
        }
      }
    }

    // chmod() will work if the web server is running as owner of the file.
    if (@chmod($file, $mod)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
