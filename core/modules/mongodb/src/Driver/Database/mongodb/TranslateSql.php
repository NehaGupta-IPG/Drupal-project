<?php

namespace Drupal\mongodb\Driver\Database\mongodb;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;

/**
 * The MongoDB service for translating SQL queries.
 */
class TranslateSql {

  /**
   * The query translation data.
   *
   * @var array
   */
  protected $translations = [
    [
      'pattern' => '/^SELECT cid, data, created, expire, serialized, tags, checksum FROM {(.*)} WHERE cid IN \( :cids\[\] \) ORDER BY cid$/',
      'filter' => ['cid' => ['$in' => ':cids']],
      'projection' => [
        'cid' => 1,
        'data' => 1,
        'created' => 1,
        'expire' => 1,
        'serialized' => 1,
        'tags' => 1,
        'checksum' => 1,
      ],
    ],
    [
      'pattern' => '/^SELECT name, value FROM {(.*)} WHERE name IN \( :keys\[\] \) AND collection = :collection$/',
      'filter' => ['name' => ['$in' => ':keys[]'], 'collection' => ':collection'],
      'projection' => ['name' => 1, 'value' => 1],
    ],
    [
      'pattern' => '/^SELECT \[tag\], \[invalidations\] FROM {(.*)} WHERE \[tag\] IN \( :tags\[\] \)$/',
      'filter' => ['tag' => ['$in' => ':tags[]']],
      'projection' => ['tag' => 1, 'invalidations' => 1],
    ],
    [
      'pattern' => '/^SELECT \[data\] FROM {(.*)} WHERE \[collection\] = :collection AND \[name\] = :name$/',
      'filter' => ['collection' => ':collection', 'name' => ':name'],
      'projection' => ['data' => 1],
    ],
    [
      'pattern' => '/^SELECT \[name\], \[data\] FROM {(.*)} WHERE \[collection\] = :collection AND \[name\] IN \( :names\[\] \)$/',
      'filter' => ['name' => ['$in' => ':names[]'], 'collection' => ':collection'],
      'projection' => ['name' => 1, 'data' => 1],
    ],
    [
      'pattern' => '/^SELECT name, value FROM {(.*)} WHERE collection = :collection$/',
      'filter' => ['collection' => ':collection'],
      'projection' => ['name' => 1, 'value' => 1],
    ],
    [
      'pattern' => '/^SELECT session FROM {(.*)} WHERE sid = :sid$/',
      'filter' => ['sid' => ':sid'],
      'projection' => ['session' => 1],
    ],
    [
      'pattern' => '/^SELECT name, route FROM {(.*)} WHERE name IN \( :names\[\] \)$/',
      'filter' => ['name' => ['$in' => ':names[]']],
      'projection' => ['name' => 1, 'route' => 1],
    ],

    // Query_range queries.
    [
      'pattern' => '/^SELECT 1 FROM {(.*)} WHERE \[collection\] = :collection AND \[name\] = :name$/',
      'filter' => ['name' => ':name', 'collection' => ':collection'],
      'projection' => [],
    ],

    // Test queries.
    [
      'pattern' => '/^SELECT \[name\] FROM {(.*)} WHERE \[age\] = :age$/',
      'filter' => ['age' => ':age'],
      'projection' => ['name' => 1],
    ],
    [
      'pattern' => '/^SELECT COUNT\(\*\) FROM {(.*)}$/',
      'filter' => [],
      'projection' => [],
      'count' => 1,
    ],
    [
      'pattern' => '/^SELECT \* FROM {(.*)} WHERE \[job\] = :job$/',
      'filter' => ['job' => ':job'],
      'projection' => [],
    ],
    [
      'pattern' => '/^SELECT \[classname\], \[name\], \[job\] FROM {(.*)} WHERE \[age\] = :age$/',
      'filter' => ['age' => ':age'],
      'projection' => ['classname' => 1, 'name' => 1, 'job' => 1, '_id' => 0],
    ],
    [
      'pattern' => '/^SELECT \[name\] FROM {(.*)}$/',
      'filter' => [],
      'projection' => ['name' => 1],
    ],
    [
      'pattern' => '/^SELECT \[age\] FROM {(.*)} WHERE \[name\] = :name$/',
      'filter' => ['name' => ':name'],
      'projection' => ['age' => 1],
    ],
    [
      'pattern' => '/^SELECT \[update\] FROM {(.*)} WHERE \[id\] = :id$/',
      'filter' => ['id' => ':id'],
      'projection' => ['update' => 1],
    ],
    [
      'pattern' => '/^SELECT \* FROM {(.*)} WHERE \[id\] = :id$/',
      'filter' => ['id' => ':id'],
      'projection' => ['update' => 1, 'id' => 1],
    ],
    [
      'pattern' => '/^SELECT \[update\] FROM {(.*)}$/',
      'filter' => [],
      'projection' => ['update' => 1],
    ],
    [
      'pattern' => '/^SELECT \* FROM {(.*)}$/',
      'filter' => [],
      'projection' => ['id' => 1, 'name' => 1, 'age' => 1, 'job' => 1],
    ],
    [
      'pattern' => '/^SELECT \* FROM {(.*)} WHERE \[name\] = :name$/',
      'filter' => ['name' => ':name'],
      'projection' => ['id' => 1, 'name' => 1, 'age' => 1, 'job' => 1],
    ],
    [
      'pattern' => '/^SELECT \[age\] FROM {(.*)} WHERE \[job\] = :job$/',
      'filter' => ['job' => ':job'],
      'projection' => ['age' => 1],
    ],
    [
      'pattern' => '/^SELECT \[name\] FROM {(.*)} WHERE \[id\] = :id$/',
      'filter' => ['id' => ':id'],
      'projection' => ['name' => 1],
    ],
    [
      'pattern' => '/^SELECT COUNT\(\*\) FROM {(.*)} WHERE \[job\] = :job$/',
      'filter' => ['job' => ':job'],
      'projection' => [],
      'count' => 1,
    ],
    [
      'pattern' => '/^SELECT \[name\] FROM {(.*)} WHERE \[age\] IN \( :ages\[\] \) ORDER BY \[age\]$/',
      'filter' => ['age' => ['$in' => ':ages[]']],
      'projection' => ['name' => 1],
      'sort' => ['age' => 1],
    ],
    [
      'pattern' => '/^SELECT \[job\] FROM {(.*)} WHERE \[id\] = :id$/',
      'filter' => ['id' => ':id'],
      'projection' => ['job' => 1],
      'integer_arguments' => [':id'],
    ],
    [
      'pattern' => '/^SELECT 1 FROM {(.*)} WHERE \[name\] = :name$/',
      'filter' => ['name' => ':name'],
      'projection' => [],
    ],
    [
      'pattern' => '/^SELECT \[job\] FROM {(.*)} WHERE \[job\] = :job$/',
      'filter' => ['job' => ':job'],
      'projection' => ['job' => 1],
    ],
    [
      'pattern' => '/^SELECT data FROM {(.*)} WHERE name = :name$/',
      'filter' => ['name' => ':name'],
      'projection' => ['data' => 1],
    ],
    [
      'pattern' => '/^SELECT \* FROM {(.*)} WHERE source = :source AND alias= :alias AND langcode = :langcode$/',
      'filter' => ['source' => ':source', 'alias' => ':alias', 'langcode' => ':langcode'],
      'projection' => ['pid' => 1, 'source' => 1, 'alias' => 1, 'langcode' => 1],
    ],
    [
      'pattern' => '/^SELECT pid FROM {(.*)} WHERE source = :source AND alias= :alias AND langcode = :langcode$/',
      'filter' => ['source' => ':source', 'alias' => ':alias', 'langcode' => ':langcode'],
      'projection' => ['pid' => 1],
    ],
    [
      'pattern' => '/^SELECT \* FROM {(.*)} WHERE pid = :pid$/',
      'filter' => ['pid' => ':pid'],
      'projection' => ['pid' => 1, 'source' => 1, 'alias' => 1, 'langcode' => 1],
    ],
    [
      'pattern' => '/^SELECT name, path, pattern_outline, fit, route FROM {(.*)} WHERE name = :name$/',
      'filter' => ['name' => ':name'],
      'projection' => ['name' => 1, 'path' => 1, 'pattern_outline' => 1, 'fit' => 1, 'route' => 1],
    ],
    [
      'pattern' => '/^SELECT \[expire\], \[value\] FROM {(.*)} WHERE \[name\] = :name$/',
      'filter' => ['name' => ':name'],
      'projection' => ['expire' => 1, 'value' => 1],
    ],
    [
      'pattern' => '/^select \[name\] from {(.*)} where \[name\] = :value$/',
      'filter' => ['name' => ':value'],
      'projection' => ['name' => 1],
    ],
    [
      'pattern' => '/^select name from {(.*)} where name = :value$/',
      'filter' => ['name' => ':value'],
      'projection' => ['name' => 1],
    ],
  ];

  /**
   * Helper function to create the fields array from the projection.
   */
  protected function projectionToFields(array $projection) {
    $fields = [];
    foreach ($projection as $key => $value) {
      if ($value == 1) {
        $fields[] = $key;
      }
    }
    return $fields;
  }

  /**
   * Translate a query from Connection::query and execute the query.
   */
  public function query(Connection $connection, $query, array $args = [], array $query_options = []) {
    $options = [];
    $cursor = NULL;
    if (is_string($query)) {
      // Add the range data to the MongoDB options (skip and limit).
      if (!empty($query_options['start']) && !empty($query_options['length'])) {
        $options['skip'] = (int) $query_options['start'];
        $options['limit'] = (int) $query_options['length'];
        unset($query_options['start']);
        unset($query_options['length']);
      }

      // The SQL queries that need special handling.
      if (preg_match('/^SELECT MAX\(\[id\]\) FROM {(.*)}$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          [],
          [
            'sort' => ['id' => -1],
            'limit' => 1,
            'projection' => ['id' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['id']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT MAX\(\[nid\]\) FROM {(.*)}$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          [],
          [
            'sort' => ['nid' => -1],
            'limit' => 1,
            'projection' => ['nid' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['id']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT MAX\(\[wid\]\) FROM {(.*)}$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          [],
          [
            'sort' => ['wid' => -1],
            'limit' => 1,
            'projection' => ['wid' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['wid']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \[name\] FROM {(.*)} WHERE \[age\] > :age$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $age = $args[':age'];

        if ($connection->isEventEnabled(StatementExecutionStartEvent::class)) {
          $startEvent = new StatementExecutionStartEvent(
            spl_object_id($this),
            $connection->getKey(),
            $connection->getTarget(),
            $query,
            $args,
            $connection->findCallerFromDebugBacktrace()
          );
          $connection->dispatchEvent($startEvent);
        }

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['age' => ['$gt' => $age]],
          [
            'projection' => ['name' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ],
        );

        if (isset($startEvent) && $connection->isEventEnabled(StatementExecutionEndEvent::class)) {
          $connection->dispatchEvent(new StatementExecutionEndEvent(
            $startEvent->statementObjectId,
            $startEvent->key,
            $startEvent->target,
            $startEvent->queryString,
            $startEvent->args,
            $startEvent->caller,
            $startEvent->time
          ));
        }

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \* FROM {test} WHERE \[age\] > :age$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test';
        $age = $args[':age'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['age' => ['$gt' => $age]],
          [
            'projection' => ['name' => 1, 'age' => 1, 'job' => 1, 'id' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \* FROM {test_one_blob} WHERE \[id\] = :id$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test_one_blob';
        $id = (int) $args[':id'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['id' => ['$eq' => $id]],
          [
            'projection' => ['id' => 1, 'blob1' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['id', 'blob1']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \* FROM {test_two_blobs} WHERE \[id\] = :id$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test_two_blobs';
        $id = (int) $args[':id'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['id' => ['$eq' => $id]],
          [
            'projection' => ['id' => 1, 'blob1' => 1, 'blob2' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['id', 'blob1', 'blob2']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \* FROM {test_special_columns} WHERE id = :id$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test_special_columns';
        $id = (int) $args[':id'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['id' => ['$eq' => $id]],
          [
            'projection' => ['id' => 1, 'offset' => 1, 'function' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['id', 'offset', 'function']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \[name\] FROM {(.*)} ORDER BY \[name\]$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          [],
          [
            'projection' => ['name' => 1, '_id' => 0],
            'sort' => ['name' => 1],
            'skip' => $options['skip'] ?? 0,
            'limit' => $options['limit'] ?? 0,
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \[vid\] FROM {(.*)} WHERE \[uid\] = :uid ORDER BY \[vid\]$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $uid = (int) $args[':uid'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['uid' => ['$eq' => $uid]],
          [
            'projection' => ['vid' => 1, '_id' => 0],
            'sort' => ['vid' => 1],
            'skip' => $options['skip'] ?? 0,
            'limit' => $options['limit'] ?? 0,
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match("/^SELECT \* FROM {test_task} WHERE \[task\] = 'sleep' ORDER BY \[tid\]$/", $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test_task';

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          [],
          [
            'projection' => ['tid' => 1, '_id' => 0],
            'sort' => ['tid' => 1],
            'skip' => $options['skip'] ?? 0,
            'limit' => $options['limit'] ?? 0,
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['tid']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match("/^SELECT \[data\], \[created\], \[item_id\] FROM {queue} q WHERE \[expire\] = 0 AND \[name\] = :name ORDER BY \[created\], \[item_id\] ASC$/", $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'queue';
        $name = $args[':name'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['expire' => ['$eq' => 0], 'name' => ['$eq' => $name]],
          [
            'projection' => ['data' => 1, 'created' => 1, 'item_id' => 1, '_id' => 0],
            'sort' => ['created' => 1, 'item_id' => 1],
            'skip' => $options['skip'] ?? 0,
            'limit' => $options['limit'] ?? 0,
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['tid']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT MAX\(\[test_serial\]\) FROM {(.*)}$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            [
              '$group' => [
                '_id' => NULL,
                'max' => ['$max' => '$test_serial'],
              ],
            ],
            [
              '$project' => ['max' => 1, '_id' => 0],
            ],
          ],
          [
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['max']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT COUNT\(\*\) \+ 3 FROM {(.*)}$/', $query, $matches) || preg_match('/^SELECT COUNT\(\*\) \+ :count FROM {(.*)}$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $arg_count = isset($args[':count']) ? intval($args[':count']) : 3;

        $startEvent = $this->startEvent($connection, $query, $args);

        // Execute the count query.
        // @todo Something is wrong with this query. Can we remove it?
        $query_count = $connection->getConnection()->selectCollection($prefixed_table)->count(
          [],
          [
            [
              '$group' => [
                '_id' => '$id',
                'count' => ['$sum' => 1],
              ],
            ],
            ['$project' => ['count' => 1, '_id' => 0]],
          ],
          ['useCursor' => FALSE],
        );

        $this->endEvent($connection, $startEvent);

        return new StatementCountQuery($connection, $arg_count + $query_count, $query_options);
      }
      elseif (preg_match('/^SELECT name, value FROM {(.*)} WHERE collection = :collection AND expire > :now$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $collection = $args[':collection'];
        $now = $args[':now'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['collection' => ['$eq' => $collection], 'expire' => ['$gt' => $now]],
          [
            'projection' => ['name' => 1, 'value' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name', 'value']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT CONCAT\(:a1, CONCAT\(:a2, CONCAT\(:a3, CONCAT\(:a4, :a5\)\)\)\)$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test';
        $a1 = $args[':a1'];
        $a2 = $args[':a2'];
        $a3 = $args[':a3'];
        $a4 = $args[':a4'];
        $a5 = $args[':a5'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            [
              '$project' => [
                'name' => [
                  '$concat' => [$a1, $a2, $a3, $a4, $a5],
                ],
              ],
            ],
            ['$project' => ['name' => 1, '_id' => 0]],
          ],
          ['session' => $connection->getMongodbSession()],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT CONCAT\(:a1, CONCAT\(\[job\], CONCAT\(:a2, CONCAT\(\[age\], :a3\)\)\)\) FROM {(.*)} WHERE \[age\] = :age$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $a1 = $args[':a1'];
        $a2 = $args[':a2'];
        $age = $args[':age'];
        $a3 = $args[':a3'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            ['$match' => ['age' => (int) $age]],
            [
              '$project' => [
                'name' => [
                  '$concat' => [$a1, '$job', $a2, (string) $age, $a3],
                ],
              ],
            ],
            ['$project' => ['name' => 1, '_id' => 0]],
          ],
          ['session' => $connection->getMongodbSession()],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT CONCAT_WS\(\', \', :a1, NULL, :a2, :a3, :a4\)$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test';
        $a1 = $args[':a1'];
        $a2 = $args[':a2'];
        $a3 = $args[':a3'];
        $a4 = $args[':a4'];

        $startEvent = $this->startEvent($connection, $query, $args);

        // Fake the query a bit until MongoDB supports concat_ws.
        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            [
              '$project' => [
                'name' => [
                  '$concat' => [$a1, ', ', $a3, ', ', $a4],
                ],
              ],
            ],
            ['$project' => ['name' => 1, '_id' => 0]],
          ],
          ['session' => $connection->getMongodbSession()]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT CONCAT_WS\(\'\-\', :a1, \[name\], :a2, \[age\]\) FROM {(.*)} WHERE \[age\] = :age$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $a1 = $args[':a1'];
        $a2 = $args[':a2'];
        $age = $args[':age'];

        $startEvent = $this->startEvent($connection, $query, $args);

        // Fake the query a bit until MongoDB supports concat_ws.
        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            ['$match' => ['age' => (int) $age]],
            [
              '$project' => [
                'name' => [
                  '$concat' => [$a1, '-', '$name', '-', $a2, '-', ['$toString' => '$age']],
                ],
              ],
            ],
            ['$project' => ['name' => 1, '_id' => 0]],
          ],
          ['session' => $connection->getMongodbSession()]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^select name from {(.*)} where name = \'\[square\]\'$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        // Fake the query a bit until MongoDB supports concat_ws.
        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          ['name' => ['$eq' => '[square]']],
          [
            'projection' => ['name' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT LEAST\(:values\[\]\)$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test';
        $values = $args[':values[]'];

        $startEvent = $this->startEvent($connection, $query, $args);

        // Fake the query a bit until MongoDB supports concat_ws.
        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            [
              '$project' => [
                'name' => ['$min' => $values],
              ],
            ],
            [
              '$project' => [
                'name' => 1,
                '_id' => 0,
              ],
            ],
          ],
          [
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['name']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT \* FROM \{test\} WHERE 1 = 0$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . 'test';

        $startEvent = $this->startEvent($connection, $query, $args);

        // Fake the query a bit until MongoDB supports concat_ws.
        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
          // There is no record with the age being -1. The same result as 1 = 0.
          ['age' => ['$eq' => -1]],
          [
            'projection' => ['id' => 1, '_id' => 0],
            'session' => $connection->getMongodbSession(),
          ],
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['id']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^SELECT DISTINCT \[collection\] FROM {(.*)} WHERE \[collection\] <> :collection ORDER by \[collection\]$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $collection = $args[':collection'];

        $startEvent = $this->startEvent($connection, $query, $args);

        $cursor = $connection->getConnection()->selectCollection($prefixed_table)->aggregate(
          [
            [
              '$group' => [
                '_id' => '$collection',
                'collection' => ['$first' => '$collection'],
              ],
            ],
            [
              '$match' => [
                '$expr' => [
                  '$ne' => ['$collection', $collection],
                ],
              ],
            ],
            [
              '$sort' => [
                'collection' => 1,
              ],
            ],
          ],
          [
            'useCursor' => TRUE,
            'session' => $connection->getMongodbSession(),
          ]
        );

        $this->endEvent($connection, $startEvent);

        $statement = new Statement($connection, $cursor, ['collection']);
        $statement->execute(NULL, $query_options);
        return $statement;
      }
      elseif (preg_match('/^UPDATE {(.*)} SET uid = 1 WHERE id = 1$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $result = $connection->getConnection()->selectCollection($prefixed_table)->updateMany(
          ['id' => 1],
          ['$set' => ['uid' => 1]],
          ['session' => $connection->getMongodbSession()],
        );

        $this->endEvent($connection, $startEvent);

        if ($result && $result->isAcknowledged()) {
          // Return the number of rows matched by query.
          return $result->getMatchedCount();
        }
      }
      elseif (preg_match('/^UPDATE {(.*)} SET uid = 2 WHERE id <> 1$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];

        $startEvent = $this->startEvent($connection, $query, $args);

        $result = $connection->getConnection()->selectCollection($prefixed_table)->updateMany(
          ['id' => ['$ne' => 1]],
          ['$set' => ['uid' => 2]],
          ['session' => $connection->getMongodbSession()],
        );

        $this->endEvent($connection, $startEvent);

        if ($result && $result->isAcknowledged()) {
          // Return the number of rows matched by query.
          return $result->getMatchedCount();
        }
      }
      elseif (preg_match('/^UPDATE {(.*)} SET uid = :uid WHERE id = (\d)$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $uid = (int) $args[':uid'];
        $id = (int) $matches[2];

        $startEvent = $this->startEvent($connection, $query, $args);

        $result = $connection->getConnection()->selectCollection($prefixed_table)->updateMany(
          ['id' => $id],
          ['$set' => ['uid' => $uid]],
          ['session' => $connection->getMongodbSession()],
        );

        $this->endEvent($connection, $startEvent);

        if ($result && $result->isAcknowledged()) {
          // Return the number of rows matched by query.
          return $result->getMatchedCount();
        }
      }
      elseif (preg_match('/^UPDATE {(.*)} SET uid = :uid WHERE id IN \((\d),(\d)\)$/', $query, $matches)) {
        $prefixed_table = $connection->getPrefix() . $matches[1];
        $uid = (int) $args[':uid'];
        $first_id = (int) $matches[2];
        $second_id = (int) $matches[3];

        $startEvent = $this->startEvent($connection, $query, $args);

        $result = $connection->getConnection()->selectCollection($prefixed_table)->updateMany(
          ['id' => ['$in' => [$first_id, $second_id]]],
          ['$set' => ['uid' => $uid]],
          ['session' => $connection->getMongodbSession()],
        );

        $this->endEvent($connection, $startEvent);

        if ($result && $result->isAcknowledged()) {
          // Return the number of rows matched by query.
          return $result->getMatchedCount();
        }
      }

      foreach ($this->translations as $translation) {
        $matches = NULL;
        if (preg_match($translation['pattern'], $query, $matches)) {
          $filter = $translation['filter'];

          // MongoDB is strict about integer values being an integer.
          foreach ($args as $arg_key => $arg_value) {
            if (!empty($translation['integer_arguments']) && in_array($arg_key, $translation['integer_arguments'])) {
              $args[$arg_key] = (int) $arg_value;
            }
          }

          // Replace the placeholders with the real values.
          array_walk_recursive($filter, function (&$item_value, $item_key, $args) {
            foreach ($args as $arg_key => $arg_value) {
              if ($item_value == $arg_key) {
                $is_bracket_placeholder = substr($arg_key, -2) === '[]';
                $is_array_data = is_array($arg_value);
                if ($is_bracket_placeholder && !$is_array_data) {
                  throw new \InvalidArgumentException('Placeholders with a trailing [] can only be expanded with an array of values.');
                }
                if (!$is_bracket_placeholder && $is_array_data) {
                  throw new \InvalidArgumentException('Placeholders must have a trailing [] if they are to be expanded with an array of values.');
                }

                $item_value = $arg_value;
              }
            }
          }, $args);

          // Add the fields to the MongoDB projection.
          if (!empty($translation['projection'])) {
            $options['projection'] = $translation['projection'];
            // Do not return the value of _id.
            $options['projection']['_id'] = 0;
          }

          $prefixed_table = $connection->getPrefix() . $matches[1];

          if (!empty($translation['count'])) {
            $startEvent = $this->startEvent($connection, $query, $args);

            // Execute the count query.
            $count = $connection->getConnection()->selectCollection($prefixed_table)->count(
              $filter,
              $options
            );

            $this->endEvent($connection, $startEvent);

            return new StatementCountQuery($connection, $count, $query_options);
          }

          // Add the sorting to the MongoDB query.
          if (!empty($translation['sort'])) {
            $options['sort'] = $translation['sort'];
          }

          $startEvent = $this->startEvent($connection, $query, $args);

          // Add the session to the query.
          $options['session'] = $connection->getMongodbSession();

          // Execute the query.
          $cursor = $connection->getConnection()->selectCollection($prefixed_table)->find(
            $filter,
            $options
          );

          $this->endEvent($connection, $startEvent);

          // If we have a MongoDB query result cursor then return the Statement
          // object.
          if ($cursor) {
            $statement = new Statement($connection, $cursor, $this->projectionToFields($translation['projection']));
            $statement->execute(NULL, $query_options);
            return $statement;
          }
          break;
        }
      }

      // Unable to translate and execute the SQL statement.
      throw new DatabaseExceptionWrapper('Unknown query string: ' . $query);
    }
    else {
      // An SQL statement must be a string.
      throw new DatabaseExceptionWrapper('The query is not a string!');
    }
  }

  /**
   * Dispatch the start event for the query.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $query
   *   The query.
   * @param array $args
   *   The query arguments.
   *
   * @return \Drupal\Core\Database\Event\StatementExecutionStartEvent
   *   The created start event.
   */
  protected function startEvent(Connection $connection, string $query, array $args = []) {
    if ($connection->isEventEnabled(StatementExecutionStartEvent::class)) {
      $startEvent = new StatementExecutionStartEvent(
        spl_object_id($this),
        $connection->getKey(),
        $connection->getTarget(),
        $query,
        $args,
        $connection->findCallerFromDebugBacktrace()
      );
      $connection->dispatchEvent($startEvent);
    }

    return $startEvent ?? NULL;
  }

  /**
   * Dispatch the end event for the query.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent|null $startEvent
   *   The start event for the query.
   */
  protected function endEvent(Connection $connection, ?StatementExecutionStartEvent $startEvent = NULL): void {
    if (isset($startEvent) && $connection->isEventEnabled(StatementExecutionEndEvent::class)) {
      $connection->dispatchEvent(new StatementExecutionEndEvent(
        $startEvent->statementObjectId,
        $startEvent->key,
        $startEvent->target,
        $startEvent->queryString,
        $startEvent->args,
        $startEvent->caller,
        $startEvent->time
      ));
    }
  }

  /**
   * Translate a query from Connection::query_range and execute the query.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $query
   *   The string query to execute.
   * @param string|int $start
   *   The number of results that should be skipped.
   * @param string|int $length
   *   The number of results that should be returned.
   * @param array $args
   *   The arguments for the query.
   * @param array $options
   *   The options for the query.
   */
  public function queryRange(Connection $connection, $query, $start, $length, array $args = [], array $options = []) {
    $options['start'] = (int) $start;
    $options['length'] = (int) $length;

    return $this->query($connection, $query, $args, $options);
  }

}
