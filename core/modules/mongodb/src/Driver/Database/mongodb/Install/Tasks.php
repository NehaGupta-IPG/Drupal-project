<?php

namespace Drupal\mongodb\Driver\Database\mongodb\Install;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Install\Tasks as InstallTasks;

// cspell:ignore replicaset

/**
 * Specifies installation tasks for MongoDB databases.
 */
class Tasks extends InstallTasks {

  /**
   * {@inheritdoc}
   */
  protected $pdoDriver = 'mongodb';

  /**
   * {@inheritdoc}
   */
  protected $error = NULL;

  /**
   * {@inheritdoc}
   */
  protected $tasks = [
    [
      'function'    => 'checkEngineVersion',
      'arguments'   => [],
    ],
    [
      'function'    => 'ensureReplicaSet',
      'arguments'   => [],
    ],
    [
      'function'    => 'checkDropCollectionIfExists',
      'arguments'   => ['name' => 'drupal_install_test'],
    ],
    [
      'function'    => 'checkCreateCollection',
      'arguments'   => [
        'name' => 'drupal_install_test',
        'definition' => [
          'fields' => [
            'id'  => [
              'type' => 'int',
              'default' => NULL,
            ],
          ],
        ],
      ],
    ],
    [
      'function'    => 'checkInsertCollection',
      'arguments'   => ['name' => 'drupal_install_test', 'fields' => ['id' => 1]],
    ],
    [
      'function'    => 'checkUpdateCollection',
      'arguments'   => ['name' => 'drupal_install_test', 'condition' => ['id', 1, '='], 'fields' => ['id' => 2]],
    ],
    [
      'function'    => 'checkDeleteCollection',
      'arguments'   => ['name' => 'drupal_install_test', 'condition' => ['id', 2, '=']],
    ],
    [
      'function'    => 'checkDropCollection',
      'arguments'   => ['name' => 'drupal_install_test'],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function name() {
    return t('MongoDB');
  }

  /**
   * {@inheritdoc}
   */
  public function minimumVersion() {
    return '7.0';
  }

  /**
   * Check whether Drupal is installable on the database.
   */
  public function installable() {
    return extension_loaded('mongodb') && empty($this->error);
  }

  /**
   * {@inheritdoc}
   */
  protected function connect() {
    try {
      Database::getConnection();
      $this->pass('Drupal can CONNECT to MongoDB.');
    }
    catch (\Exception) {
      $this->fail('Failed to connect to MongoDB');
    }
    return TRUE;
  }

  /**
   * Enable the MongoDB module.
   */
  public function enableModule() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface  */
    $installer = \Drupal::service('module_installer');
    $installer->install(['mongodb']);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormOptions(array $database) {
    $form = parent::getFormOptions($database);

    $replica_set = '';
    if (!empty($database['replicaset'])) {
      $replica_set = $database['replicaset'];
    }
    elseif (!empty($database['replicaSet'])) {
      $replica_set = $database['replicaSet'];
    }

    // Add the replica set setting to the main options.
    $form['srv'] = [
      '#type' => 'checkbox',
      '#title' => t('Use the <a href="https://www.mongodb.com/docs/manual/reference/connection-string/#std-label-connections-dns-seedlist">SRV</a> connection format'),
      '#description' => t('MongoDB supports a DNS-constructed seed list. Using DNS to construct the available servers list allows more flexibility of deployment and the ability to change the servers in rotation without reconfiguring clients.'),
      '#default_value' => $database['srv'] ?? '',
    ];

    // Add the replica set setting to the main options.
    $form['replicaset'] = [
      '#type' => 'textfield',
      '#title' => t('Database replica set'),
      '#description' => t('You can connect to a MongoDB database is different ways. When you connect to a replica set, you need to set this option and set the server names of the members of the replica set. For more information, see: <a href="https://www.mongodb.com/docs/manual/reference/connection-string/">Connecting to a MongoDB database</a>.'),
      '#default_value' => $replica_set,
      '#size' => 45,
    ];

    // The primary host of the replica set.
    $form['host1'] = [
      '#type' => 'fieldset',
      '#title' => t('Host #1'),
      '#weight' => 10,
    ];
    $form['host1']['host'] = [
      '#type' => 'textfield',
      '#title' => t('Host'),
      '#default_value' => $database['hosts'][0]['host'] ?? $database['host1']['host'] ?? '',
      '#size' => 45,
      // Host names can be 255 characters long.
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $form['host1']['port'] = [
      '#type' => 'number',
      '#title' => t('Port number'),
      '#default_value' => $database['hosts'][0]['port'] ?? $database['host1']['port'] ?? '27017',
      '#min' => 0,
      '#max' => 65535,
    ];

    // The secondary host of the replica set.
    $form['host2'] = [
      '#type' => 'fieldset',
      '#title' => t('Host #2'),
      '#weight' => 11,
    ];
    $form['host2']['host'] = [
      '#type' => 'textfield',
      '#title' => t('Host'),
      '#default_value' => $database['hosts'][1]['host'] ?? $database['host2']['host'] ?? '',
      '#size' => 45,
      // Host names can be 255 characters long.
      '#maxlength' => 255,
    ];
    $form['host2']['port'] = [
      '#type' => 'number',
      '#title' => t('Port number'),
      '#default_value' => $database['hosts'][1]['port'] ?? $database['host2']['port'] ?? '27017',
      '#min' => 0,
      '#max' => 65535,
    ];

    // The tertiary host of the replica set.
    $form['host3'] = [
      '#type' => 'fieldset',
      '#title' => t('Host #3'),
      '#weight' => 12,
    ];
    $form['host3']['host'] = [
      '#type' => 'textfield',
      '#title' => t('Host'),
      '#default_value' => $database['hosts'][2]['host'] ?? $database['host3']['host'] ?? '',
      '#size' => 45,
      // Host names can be 255 characters long.
      '#maxlength' => 255,
    ];
    $form['host3']['port'] = [
      '#type' => 'number',
      '#title' => t('Port number'),
      '#default_value' => $database['hosts'][2]['port'] ?? $database['host3']['port'] ?? '27017',
      '#min' => 0,
      '#max' => 65535,
    ];

    // Move the advanced options to the bottom.
    $form['advanced_options']['#weight'] = 20;
    // Remove the single host and port options.
    unset($form['advanced_options']['host']);
    unset($form['advanced_options']['port']);

    return $form;
  }

  /**
   * Ensure that the database is set up with a replica set.
   */
  public function ensureReplicaSet() {
    // When the method getReplicaSetName() return FALSE, then MongoDB is not
    // using a replica set.
    $name = Database::getConnection()->getReplicaSetName();
    if ($name) {
      $this->pass(t("The database is set up with the replica set: %name.", ['%name' => $name]));
    }
    else {
      $this->fail(t('The database is not set up with a replica set.'));
    }
  }

  /**
   * Check the if the test collection can be dropped if it exists.
   */
  protected function checkDropCollectionIfExists($name) {
    try {
      if (Database::getConnection()->schema()->tableExists($name)) {
        Database::getConnection()->schema()->dropTable($name);
        $this->pass(t("The database server was able to drop the existing collection %name.", ['%name' => $name]));
      }
    }
    catch (\Exception) {
      $this->fail(t("The database server is unable to drop the existing collection %name.", ['%name' => $name]));
    }
  }

  /**
   * Check the if the test collection can be created.
   */
  protected function checkCreateCollection($name, $definition) {
    try {
      Database::getConnection()->schema()->createTable($name, $definition);
      $this->pass(t("The database server was able to create the collection %name.", ['%name' => $name]));
    }
    catch (\Exception) {
      $this->fail(t("The database server is unable to create the collection %name.", ['%name' => $name]));
    }
  }

  /**
   * Check the if data can be inserted into the test collection.
   */
  protected function checkInsertCollection($name, $fields) {
    try {
      Database::getConnection()->insert($name)->fields($fields)->execute();
      $this->pass(t("The database server was able to insert data into the collection %name.", ['%name' => $name]));
    }
    catch (\Exception) {
      $this->fail(t("The database server is unable to insert data into the collection %name.", ['%name' => $name]));
    }
  }

  /**
   * Check the if data can be updated in the test collection.
   */
  protected function checkUpdateCollection($name, array $condition = [], array $fields = []) {
    try {
      Database::getConnection()->update($name)->fields($fields)->condition($condition[0], $condition[1], $condition[2])->execute();
      $this->pass(t("The database server was able to update data in the collection %name.", ['%name' => $name]));
    }
    catch (\Exception) {
      $this->fail(t("The database server is unable to update data in the collection %name.", ['%name' => $name]));
    }
  }

  /**
   * Check the if data can be deleted in the test collection.
   */
  protected function checkDeleteCollection($name, array $condition = []) {
    try {
      Database::getConnection()->delete($name)->condition($condition[0], $condition[1], $condition[2])->execute();
      $this->pass(t("The database server was able to delete data in the collection %name.", ['%name' => $name]));
    }
    catch (\Exception) {
      $this->fail(t("The database server is unable to delete data in the collection %name.", ['%name' => $name]));
    }
  }

  /**
   * Check the if the test collection can be dropped.
   */
  protected function checkDropCollection($name) {
    try {
      Database::getConnection()->schema()->dropTable($name);
      $this->pass(t("The database server was able to drop the collection %name.", ['%name' => $name]));
    }
    catch (\Exception) {
      $this->fail(t("The database server is unable to drop the collection %name.", ['%name' => $name]));
    }
  }

}
