<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config\Storage;

use Drupal\Core\Config\MemoryStorage;
use Drupal\TestTools\Attribute\Skip;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MemoryStorage operations.
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class MemoryStorageTest extends ConfigStorageTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->storage = new MemoryStorage();
  }

  /**
   * {@inheritdoc}
   */
  protected function read($name): array|object {
    return $this->storage->read($name);
  }

  /**
   * {@inheritdoc}
   */
  protected function insert($name, $data): void {
    $this->storage->write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  protected function update($name, $data): void {
    $this->storage->write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  protected function delete($name): void {
    $this->storage->delete($name);
  }

  /**
   * {@inheritdoc}
   */
  #[Skip('Permanent: see https://www.drupal.org/node/3015886')]
  public function testInvalidStorage(): void {
  }

}
