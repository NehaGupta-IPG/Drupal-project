<?php

namespace Drupal\Core\Config;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\Extension;

/**
 * Storage used by the Drupal installer.
 *
 * This storage performs a full filesystem scan to discover all available
 * extensions and reads from all default config directories that exist.
 *
 * This special implementation MUST NOT be used anywhere else than the early
 * installer environment.
 *
 * @see \Drupal\Core\DependencyInjection\InstallServiceProvider
 */
class InstallStorage extends FileStorage {

  /**
   * Extension sub-directory containing default configuration for installation.
   */
  const CONFIG_INSTALL_DIRECTORY = 'config/install';

  /**
   * Extension sub-directory containing optional configuration for installation.
   */
  const CONFIG_OPTIONAL_DIRECTORY = 'config/optional';

  /**
   * Extension sub-directory containing configuration schema.
   */
  const CONFIG_SCHEMA_DIRECTORY = 'config/schema';

  /**
   * Extension sub-directory containing configuration database driver overrides.
   */
  const CONFIG_OVERRIDES_DIRECTORY = 'config/overrides';

  /**
   * Folder map indexed by configuration name.
   *
   * @var array
   */
  protected $folders;

  /**
   * The directory to scan in each extension to scan for files.
   *
   * @var string
   */
  protected $directory;

  /**
   * The database override directory.
   *
   * @var string
   */
  protected $databaseDriverOverrideDirectory;

  /**
   * Constructs an InstallStorage object.
   *
   * @param string $directory
   *   The directory to scan in each extension to scan for files. Defaults to
   *   'config/install'.
   * @param string $collection
   *   (optional) The collection to store configuration in. Defaults to the
   *   default collection.
   */
  public function __construct($directory = self::CONFIG_INSTALL_DIRECTORY, $collection = StorageInterface::DEFAULT_COLLECTION) {
    parent::__construct($directory, $collection);

    // Init the base database driver override directory. We do this here to do
    // it only once.
    $this->initBaseDatabaseDriverOverrideDirectory();
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::getFilePath().
   *
   * Returns the path to the configuration file.
   *
   * Determines the owner and path to the default configuration file of a
   * requested config object name located in the installation profile, a module,
   * or a theme (in this order).
   *
   * @return string
   *   The path to the configuration file.
   *
   * @todo Improve this when figuring out how we want to handle configuration in
   *   installation profiles. For instance, a config object actually has to be
   *   searched in the profile first (whereas the profile is never the owner);
   *   only afterwards check for a corresponding module or theme.
   */
  public function getFilePath($name) {
    $folders = $this->getAllFolders();
    if (isset($folders[$name])) {
      return $folders[$name] . '/' . $name . '.' . $this->getFileExtension();
    }
    // If any code in the early installer requests a configuration object that
    // does not exist anywhere as default config, then that must be mistake.
    throw new StorageException("Missing configuration file: $name");
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    return array_key_exists($name, $this->getAllFolders());
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::write().
   *
   * @throws \Drupal\Core\Config\StorageException
   */
  public function write($name, array $data) {
    throw new StorageException('Write operation is not allowed.');
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::delete().
   *
   * @throws \Drupal\Core\Config\StorageException
   */
  public function delete($name) {
    throw new StorageException('Delete operation is not allowed.');
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::rename().
   *
   * @throws \Drupal\Core\Config\StorageException
   */
  public function rename($name, $new_name) {
    throw new StorageException('Rename operation is not allowed.');
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    $names = array_keys($this->getAllFolders());
    if (!$prefix) {
      return $names;
    }
    else {
      $return = [];
      foreach ($names as $index => $name) {
        if (str_starts_with($name, $prefix)) {
          $return[$index] = $names[$index];
        }
      }
      return $return;
    }
  }

  /**
   * Returns a map of all config object names and their folders.
   *
   * @return array
   *   An array mapping config object names with directories.
   */
  protected function getAllFolders() {
    if (!isset($this->folders)) {
      $this->folders = [];
      $this->folders += $this->getCoreNames();
      // Perform an ExtensionDiscovery scan as we cannot use
      // \Drupal\Core\Extension\ExtensionList::getPath() yet because the system
      // module may not yet be enabled during install.
      // @todo Remove as part of https://www.drupal.org/node/2186491
      $listing = new ExtensionDiscovery(\Drupal::root());
      if ($profile = \Drupal::installProfile()) {
        $profile_list = $listing->scan('profile');
        if (isset($profile_list[$profile])) {
          // Prime the \Drupal\Core\Extension\ExtensionList::getPathname static
          // cache with the profile info file location so we can use
          // \Drupal\Core\Extension\ExtensionList::getPath() on the active
          // profile during the module scan.
          // @todo Remove as part of https://www.drupal.org/node/2186491
          /** @var \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list */
          $profile_extension_list = \Drupal::service('extension.list.profile');
          $profile_extension_list->setPathname($profile, $profile_list[$profile]->getPathname());
          $this->folders += $this->getComponentNames([$profile_list[$profile]]);
        }
      }
      // @todo Remove as part of https://www.drupal.org/node/2186491
      $this->folders += $this->getComponentNames($listing->scan('module'));
      $this->folders += $this->getComponentNames($listing->scan('theme'));
    }
    return $this->folders;
  }

  /**
   * Get all configuration names and folders for a list of modules or themes.
   *
   * @param \Drupal\Core\Extension\Extension[] $list
   *   An associative array of Extension objects, keyed by extension name.
   *
   * @return array
   *   Folders indexed by configuration name.
   */
  public function getComponentNames(array $list) {
    $extension = '.' . $this->getFileExtension();
    $pattern = '/' . preg_quote($extension, '/') . '$/';
    $folders = [];
    foreach ($list as $extension_object) {
      // We don't have to use ExtensionDiscovery here because our list of
      // extensions was already obtained through an ExtensionDiscovery scan.
      $directory = $this->getComponentFolder($extension_object);
      if (is_dir($directory)) {
        // glob() directly calls into libc glob(), which is not aware of PHP
        // stream wrappers. Same for \GlobIterator (which additionally requires
        // an absolute realpath() on Windows).
        // @see https://github.com/mikey179/vfsStream/issues/2
        $files = scandir($directory);

        foreach ($files as $file) {
          if ($file[0] !== '.' && preg_match($pattern, $file)) {
            $folders[basename($file, $extension)] = $directory;
          }
        }

        // Let a config item be overridden by a database driver one.
        if ($this->hasBaseDatabaseDriverOverrideDirectory()) {
          $database_driver_override_directory = $this->getDatabaseDriverOverrideDirectory($directory, $extension_object);
          if (is_dir($database_driver_override_directory)) {
            $database_driver_override_files = scandir($database_driver_override_directory);
            foreach ($database_driver_override_files as $database_driver_override_file) {
              if ($database_driver_override_file[0] !== '.' && preg_match($pattern, $database_driver_override_file)) {
                $folders[basename($database_driver_override_file, $extension)] = $database_driver_override_directory;
              }
            }
          }
        }
      }
    }
    return $folders;
  }

  /**
   * Initiate the base database driver override directory.
   */
  protected function initBaseDatabaseDriverOverrideDirectory(): void {
    if (Database::isActiveConnection()) {
      $connection = Database::getConnection();
      // Get the module root directory from the autoload directory setting from
      // the database connection.
      $database_driver_autoload_directory = $connection->getConnectionOptions()['autoload'] ?? '';
      $pos = strpos($database_driver_autoload_directory, 'src/Driver/Database/');
      if ($pos !== FALSE) {
        $database_driver_override_directory = substr($database_driver_autoload_directory, 0, $pos) . self::CONFIG_OVERRIDES_DIRECTORY;
        if (is_dir($database_driver_override_directory)) {
          // Only set the base database driver when the module providing the
          // database driver has one.
          $this->databaseDriverOverrideDirectory = $database_driver_override_directory;
        }
      }
    }
  }

  /**
   * Check whether the database driver has a config override directory.
   *
   * @return bool
   *   Return TRUE when the database driver has the config override directory.
   */
  protected function hasBaseDatabaseDriverOverrideDirectory(): bool {
    return (bool) $this->databaseDriverOverrideDirectory;
  }

  /**
   * Get the database driver directory for overridden config items.
   *
   * @param string $directory
   *   The directory in which to search for config items.
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension item from which the config belongs to.
   *
   * @return string
   *   The directory to search for by the database driver overridden config
   *   items.
   */
  protected function getDatabaseDriverOverrideDirectory(string $directory, Extension $extension): string {
    // The overridden config items are in  the database drivers override directory
    $dir = $this->databaseDriverOverrideDirectory . '/' . $extension->getName();

    if (str_ends_with($directory, self::CONFIG_INSTALL_DIRECTORY)) {
      $dir .= '/install';
    }
    elseif (str_ends_with($directory, self::CONFIG_OPTIONAL_DIRECTORY)) {
      $dir .= '/optional';
    }
    elseif (str_ends_with($directory, self::CONFIG_SCHEMA_DIRECTORY)) {
      $dir .= '/schema';
    }

    return $dir;
  }

  /**
   * Get all configuration names and folders for Drupal core.
   *
   * @return array
   *   Folders indexed by configuration name.
   */
  public function getCoreNames() {
    $extension = '.' . $this->getFileExtension();
    $pattern = '/' . preg_quote($extension, '/') . '$/';
    $folders = [];
    $directory = $this->getCoreFolder();
    if (is_dir($directory)) {
      // glob() directly calls into libc glob(), which is not aware of PHP
      // stream wrappers. Same for \GlobIterator (which additionally requires an
      // absolute realpath() on Windows).
      // @see https://github.com/mikey179/vfsStream/issues/2
      $files = scandir($directory);

      foreach ($files as $file) {
        if ($file[0] !== '.' && preg_match($pattern, $file)) {
          $folders[basename($file, $extension)] = $directory;
        }
      }
    }
    return $folders;
  }

  /**
   * Get folder inside each component that contains the files.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The Extension object for the component.
   *
   * @return string
   *   The configuration folder name for this component.
   */
  protected function getComponentFolder(Extension $extension) {
    return $extension->getPath() . '/' . $this->getCollectionDirectory();
  }

  /**
   * Get folder inside Drupal core that contains the files.
   *
   * @return string
   *   The configuration folder name for core.
   */
  protected function getCoreFolder() {
    return 'core/' . $this->getCollectionDirectory();
  }

  /**
   * Overrides Drupal\Core\Config\FileStorage::deleteAll().
   *
   * @throws \Drupal\Core\Config\StorageException
   */
  public function deleteAll($prefix = '') {
    throw new StorageException('Delete operation is not allowed.');
  }

  /**
   * Resets the static cache.
   */
  public function reset() {
    $this->folders = NULL;
  }

}
