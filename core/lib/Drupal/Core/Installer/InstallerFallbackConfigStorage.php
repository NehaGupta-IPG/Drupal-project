<?php

namespace Drupal\Core\Installer;

use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageException;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Database;

/**
 * Config storage that writes to the database with InstallStorage fallback.
 *
 * During the early installer phase, this storage delegates reads to both
 * the database (via DatabaseStorage) and the filesystem (via
 * InstallStorage). Writes
 * always go to the database, with lazy table creation.
 *
 * Before settings.php is available (no database connection), all operations
 * fall back entirely to InstallStorage. Once a database connection exists,
 * DatabaseStorage handles writes and takes precedence for reads.
 *
 * @internal
 *   This class is only for use by the installer.
 */
class InstallerFallbackConfigStorage implements StorageInterface {

  /**
   * The database-backed config storage, or NULL if no connection is available.
   */
  protected ?DatabaseStorage $databaseStorage = NULL;

  /**
   * The file-based install storage for reading default config.
   */
  protected InstallStorage $installStorage;

  /**
   * The storage collection.
   */
  protected string $collection;

  /**
   * Constructs an InstallerFallbackConfigStorage.
   *
   * @param string $collection
   *   (optional) The collection to store configuration in.
   */
  public function __construct(string $collection = StorageInterface::DEFAULT_COLLECTION) {
    $this->collection = $collection;
    $this->installStorage = new InstallStorage(InstallStorage::CONFIG_INSTALL_DIRECTORY, $collection);
  }

  /**
   * Lazily initializes the database storage.
   *
   * @return \Drupal\Core\Config\DatabaseStorage|null
   *   The database storage, or NULL if no database connection is available.
   */
  protected function getDatabaseStorage(): ?DatabaseStorage {
    if ($this->databaseStorage === NULL) {
      try {
        $connection = Database::getConnection();
        $this->databaseStorage = new DatabaseStorage($connection, 'config', [], $this->collection);
      }
      catch (\Exception) {
        // No database connection available yet. Will retry on next call,
        // since the database may become available later in the install process
        // (e.g., after settings.php is written).
      }
    }
    return $this->databaseStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    $db = $this->getDatabaseStorage();
    return $db ? $db->exists($name) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $db = $this->getDatabaseStorage();
    if ($db) {
      $data = $db->read($name);
      if ($data !== FALSE) {
        return $data;
      }
    }
    return $this->installStorage->read($name);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    if (empty($names)) {
      return [];
    }

    $db = $this->getDatabaseStorage();
    $dbResults = $db ? $db->readMultiple($names) : [];

    // Find names not found in the database.
    $remaining = array_diff($names, array_keys($dbResults));
    if (!empty($remaining)) {
      $fileResults = $this->installStorage->readMultiple($remaining);
      $dbResults += $fileResults;
    }

    return $dbResults;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    $db = $this->getDatabaseStorage();
    if ($db) {
      return $db->write($name, $data);
    }
    // No database available; this should not happen during normal installation
    // flow since writes only occur after settings.php is available.
    throw new StorageException('Cannot write configuration: no database connection available.');
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    $db = $this->getDatabaseStorage();
    if ($db) {
      return $db->delete($name);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    $db = $this->getDatabaseStorage();
    if ($db) {
      return $db->rename($name, $new_name);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    $db = $this->getDatabaseStorage();
    if ($db) {
      return $db->encode($data);
    }
    return $this->installStorage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    $db = $this->getDatabaseStorage();
    if ($db) {
      return $db->decode($raw);
    }
    return $this->installStorage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    $db = $this->getDatabaseStorage();
    return $db ? $db->listAll($prefix) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    $db = $this->getDatabaseStorage();
    if ($db) {
      return $db->deleteAll($prefix);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static($collection);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    $db = $this->getDatabaseStorage();
    return $db ? $db->getAllCollectionNames() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->collection;
  }

}
