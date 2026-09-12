<?php

declare(strict_types=1);

namespace Drupal\pgsql\Batch;

use Drupal\Core\Batch\BatchStorage as CoreBatchStorage;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the batch storage service.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class BatchStorage extends CoreBatchStorage {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   *
   * The batch storage describes its table with a schema object.
   */
  protected function createVerifiedTable(string $table): void {
    $this->connection->schema()->createSchemaFromDefinition($this->schemaDefinition());
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::delete($id);
  }

  /**
   * {@inheritdoc}
   */
  public function update(array $batch): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::update($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::cleanup();
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): int {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::getId();
  }

}
