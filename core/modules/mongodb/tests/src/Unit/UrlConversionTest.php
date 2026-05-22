<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Unit;

use Drupal\Tests\Core\Database\UrlConversionTest as CoreUrlConversionTest;

// cspell:ignore dummydb replicaset dbrs

/**
 * Tests for database URL to/from database connection array conversions.
 *
 * These tests run in isolation since we don't want the database static to
 * affect other tests.
 *
 * @coversDefaultClass \Drupal\Core\Database\Database
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group Database
 */
class UrlConversionTest extends CoreUrlConversionTest {

  /**
   * Data provider for testDbUrlToConnectionConversion().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - url: The full URL string to be tested.
   *   - database_array: An array containing the expected results.
   */
  public static function providerConvertDbUrlToConnectionInfo() {
    return [
      'MongoDB with replicaset and a single host without port' => [
        'mongodb://test_user:test_pass@test_host/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaset and a single host with port' => [
        'mongodb://test_user:test_pass@test_host:3306/test_database?replicaSet=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host',
              'port' => 3306,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaset and multiple hosts without ports' => [
        'mongodb://test_user:test_pass@test_host1,test_host2,test_host3/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaset and multiple hosts with port' => [
        'mongodb://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaset and multiple hosts with ports' => [
        'mongodb://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
              'port' => 27017,
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
              'port' => 27019,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaset, multiple hosts with port and module' => [
        'mongodb://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?module=mongodb&replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaset, multiple hosts with ports and module' => [
        'mongodb://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?module=mongodb&replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
              'port' => 27017,
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
              'port' => 27019,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with replicaSet, multiple hosts with ports and module' => [
        'mongodb://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?module=mongodb&replicaSet=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
              'port' => 27017,
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
              'port' => 27019,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB with prefix, replicaset and multiple hosts with port' => [
        'mongodb://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?replicaset=dbrs#bar',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'prefix' => 'bar',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => FALSE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset and a single host without port' => [
        'mongodb+srv://test_user:test_pass@test_host/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset and a single host with port' => [
        'mongodb+srv://test_user:test_pass@test_host:3306/test_database?replicaSet=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host',
              'port' => 3306,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset and multiple hosts without ports' => [
        'mongodb+srv://test_user:test_pass@test_host1,test_host2,test_host3/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset and multiple hosts with port' => [
        'mongodb+srv://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset and multiple hosts with ports' => [
        'mongodb+srv://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
              'port' => 27017,
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
              'port' => 27019,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset, multiple hosts with port and module' => [
        'mongodb+srv://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?module=mongodb&replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaset, multiple hosts with ports and module' => [
        'mongodb+srv://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?module=mongodb&replicaset=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
              'port' => 27017,
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
              'port' => 27019,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with replicaSet, multiple hosts with ports and module' => [
        'mongodb+srv://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?module=mongodb&replicaSet=dbrs',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
              'port' => 27017,
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
              'port' => 27019,
            ],
          ],
          'database' => 'test_database',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
      'MongoDB+SRV with prefix, replicaset and multiple hosts with port' => [
        'mongodb+srv://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?replicaset=dbrs#bar',
        [
          'driver' => 'mongodb',
          'username' => 'test_user',
          'password' => 'test_pass',
          'hosts' => [
            [
              'host' => 'test_host1',
            ],
            [
              'host' => 'test_host2',
              'port' => 27018,
            ],
            [
              'host' => 'test_host3',
            ],
          ],
          'database' => 'test_database',
          'prefix' => 'bar',
          'replicaset' => 'dbrs',
          'namespace' => 'Drupal\mongodb\Driver\Database\mongodb',
          'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
          'srv' => TRUE,
        ],
        FALSE,
      ],
    ];
  }

  /**
   * Data provider for testGetConnectionInfoAsUrl().
   *
   * @return array
   *   Array of arrays with the following elements:
   *   - An array mocking the database connection info. Possible keys are
   *     database, username, password, prefix, host, port, namespace and driver.
   *   - The expected URL after conversion.
   */
  public static function providerGetConnectionInfoAsUrl() {
    $info1 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host',
          'port' => 5432,
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => FALSE,
    ];
    $expected_url1 = 'mongodb://test_user:test_pass@test_host:5432/test_database?replicaSet=dbrs';

    $info2 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host',
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => FALSE,
    ];
    $expected_url2 = 'mongodb://test_user:test_pass@test_host/test_database?replicaSet=dbrs';

    $info3 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => 'bar',
      'hosts' => [
        [
          'host' => 'test_host',
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => FALSE,
    ];
    $expected_url3 = 'mongodb://test_user:test_pass@test_host/test_database?replicaSet=dbrs#bar';

    $info4 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host1',
          'port' => 27017,
        ],
        [
          'host' => 'test_host2',
          'port' => 27018,
        ],
        [
          'host' => 'test_host3',
          'port' => 27019,
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => FALSE,
    ];
    $expected_url4 = 'mongodb://test_user:test_pass@test_host1:27017,test_host2:27018,test_host3:27019/test_database?replicaSet=dbrs';

    $info5 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host1',
        ],
        [
          'host' => 'test_host2',
          'port' => 27018,
        ],
        [
          'host' => 'test_host3',
        ],
      ],
      'replicaset' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => FALSE,
    ];
    $expected_url5 = 'mongodb://test_user:test_pass@test_host1,test_host2:27018,test_host3/test_database?replicaSet=dbrs';

    $info6 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host',
          'port' => 5432,
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => TRUE,
    ];
    $expected_url6 = 'mongodb+srv://test_user:test_pass@test_host/test_database?replicaSet=dbrs';

    $info7 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host',
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => TRUE,
    ];
    $expected_url7 = 'mongodb+srv://test_user:test_pass@test_host/test_database?replicaSet=dbrs';

    $info8 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => 'bar',
      'hosts' => [
        [
          'host' => 'test_host',
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => TRUE,
    ];
    $expected_url8 = 'mongodb+srv://test_user:test_pass@test_host/test_database?replicaSet=dbrs#bar';

    $info9 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host1',
          'port' => 27017,
        ],
        [
          'host' => 'test_host2',
          'port' => 27018,
        ],
        [
          'host' => 'test_host3',
          'port' => 27019,
        ],
      ],
      'replicaSet' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => TRUE,
    ];
    $expected_url9 = 'mongodb+srv://test_user:test_pass@test_host1,test_host2,test_host3/test_database?replicaSet=dbrs';

    $info10 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host1',
        ],
        [
          'host' => 'test_host2',
          'port' => 27018,
        ],
        [
          'host' => 'test_host3',
        ],
      ],
      'replicaset' => 'dbrs',
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => TRUE,
    ];
    $expected_url10 = 'mongodb+srv://test_user:test_pass@test_host1,test_host2,test_host3/test_database?replicaSet=dbrs';

    $info11 = [
      'database' => 'test_database',
      'username' => 'test_user',
      'password' => 'test_pass',
      'prefix' => '',
      'hosts' => [
        [
          'host' => 'test_host',
        ],
      ],
      'driver' => 'mongodb',
      'namespace' => 'Drupal\\mongodb\\Driver\\Database\\mongodb',
      'autoload' => 'core/modules/mongodb/src/Driver/Database/mongodb/',
      'srv' => TRUE,
    ];
    $expected_url11 = 'mongodb+srv://test_user:test_pass@test_host/test_database';

    return [
      [$info1, $expected_url1],
      [$info2, $expected_url2],
      [$info3, $expected_url3],
      [$info4, $expected_url4],
      [$info5, $expected_url5],
      [$info6, $expected_url6],
      [$info7, $expected_url7],
      [$info8, $expected_url8],
      [$info9, $expected_url9],
      [$info10, $expected_url10],
      [$info11, $expected_url11],
    ];
  }

}
