<?php

namespace Drupal\mongodb\modules\user;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorage as CoreUserStorage;
use MongoDB\BSON\UTCDateTime;

/**
 * The MongoDB implementation of \Drupal\user\UserStorage.
 */
class UserStorage extends CoreUserStorage {

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    // The anonymous user account is saved with the fixed user ID of 0.
    // Therefore we need to check for NULL explicitly.
    if ($entity->id() === NULL) {
      $entity->set($this->idKey, $this->getMongoSequences()->nextEntityId('users'));

      $entity->enforceIsNew();
    }
    elseif (($entity->id() > 0) && ($this->getMongoSequences()->currentEntityId('users') < $entity->id())) {
      $this->getMongoSequences()->setEntityId('users', $entity->id());
    }

    return SqlContentEntityStorage::doSaveFieldItems($entity, $names);
  }

  /**
   * {@inheritdoc}
   */
  protected function isColumnSerial($table_name, $schema_name) {
    // User storage does not use a serial column for the user id.
    return $table_name == $this->revisionTable && $schema_name == $this->revisionKey;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastLoginTimestamp(UserInterface $account) {
    $prefixed_table = $this->database->getPrefix() . 'users';
    $this->database->getConnection()->selectCollection($prefixed_table)->updateMany(
      [
        'uid' => ['$eq' => (int) $account->id()],
      ],
      [
        '$set' => [
          "user_translations.$[].login" => new UTCDateTime($account->getLastLoginTime() * 1000),
          "login" => new UTCDateTime($account->getLastLoginTime() * 1000),
        ],
      ],
      ['session' => $this->database->getMongodbSession()],
    );
    // Ensure that the entity cache is cleared.
    $this->resetCache([$account->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastAccessTimestamp(AccountInterface $account, $timestamp) {
    $prefixed_table = $this->database->getPrefix() . 'users';
    $this->database->getConnection()->selectCollection($prefixed_table)->updateMany(
      [
        'uid' => ['$eq' => (int) $account->id()],
      ],
      [
        '$set' => [
          "user_translations.$[].access" => new UTCDateTime($timestamp * 1000),
          "access" => new UTCDateTime($timestamp * 1000),
        ],
      ],
      ['session' => $this->database->getMongodbSession()],
    );
    // Ensure that the entity cache is cleared.
    $this->resetCache([$account->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRoleReferences(array $rids) {
    $prefixed_table = $this->database->getPrefix() . 'users';
    $this->database->getConnection()->selectCollection($prefixed_table)->updateMany(
      [],
      [
        '$pull' => [
          "user_translations.$[].user_translations__roles" => [
            "roles_target_id" => ['$in' => array_values($rids)],
          ],
        ],
      ],
      ['session' => $this->database->getMongodbSession()],
    );
    $this->resetCache();
  }

}
