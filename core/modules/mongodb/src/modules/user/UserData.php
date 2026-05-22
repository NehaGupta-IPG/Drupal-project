<?php

namespace Drupal\mongodb\modules\user;

use Drupal\user\UserData as CoreUserData;

/**
 * The MongoDB implementation of \Drupal\user\UserData.
 */
class UserData extends CoreUserData {

  /**
   * {@inheritdoc}
   */
  public function get($module, $uid = NULL, $name = NULL) {
    $uid = !is_null($uid) ? (int) $uid : $uid;

    return parent::get($module, $uid, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function set($module, $uid, $name, $value) {
    $uid = (int) $uid;

    parent::set($module, $uid, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($module = NULL, $uid = NULL, $name = NULL) {
    $uid = !is_null($uid) ? (int) $uid : $uid;

    parent::delete($module, $uid, $name);
  }

}
