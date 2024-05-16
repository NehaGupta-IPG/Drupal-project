<?php

namespace Drupal\pgsql;

use Drupal\Core\Database\Schema\Index;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\path_alias\PathAliasStorageSchema as CorePathAliasStorageSchema;
use Drupal\pgsql\Schema\IndexType;

/**
 * Defines the path_alias schema handler.
 */
class PathAliasStorageSchema extends CorePathAliasStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    // Add GIST indexes to make queries on the columns 'alias' and 'source'
    // faster.
    // @see https://www.postgresql.org/docs/12/textsearch-indexes.html
    $schema[$this->storage->getBaseTable()]['indexes'] += [
      'path_alias__alias_gist' => new Index(['alias'], [
        'pgsql' => [
          'type' => IndexType::Gist,
          'operator' => 'gist_trgm_ops',
        ],
      ]),
      'path_alias__path_gist' => new Index(['path'], [
        'pgsql' => [
          'type' => IndexType::Gist,
          'operator' => 'gist_trgm_ops',
        ],
      ]),
    ];
    if ($revision_table = $this->storage->getRevisionTable()) {
      $schema[$revision_table]['indexes'] += [
        'path_alias_revision__alias_gist' => new Index(['alias'], [
          'pgsql' => [
            'type' => IndexType::Gist,
            'operator' => 'gist_trgm_ops',
          ],
        ]),
        'path_alias_revision__path_gist' => new Index(['path'], [
          'pgsql' => [
            'type' => IndexType::Gist,
            'operator' => 'gist_trgm_ops',
          ],
        ]),
      ];
    }

    return $schema;
  }

}
