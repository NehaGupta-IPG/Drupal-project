<?php

declare(strict_types=1);

namespace Drupal\pgsql\Cache;

use Drupal\Core\Cache\DatabaseCacheTagsChecksum as CoreDatabaseCacheTagsChecksum;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database cache tags checksum service.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseCacheTagsChecksum extends CoreDatabaseCacheTagsChecksum {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  protected function doInvalidateTags(array $tags): void {
    $this->verifyTableInTransaction('cachetags');
    parent::doInvalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTagInvalidationCounts(array $tags) {
    $this->verifyTableInTransaction('cachetags');
    return parent::getTagInvalidationCounts($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function purge(): void {
    $this->verifyTableInTransaction('cachetags');
    parent::purge();
  }

}
