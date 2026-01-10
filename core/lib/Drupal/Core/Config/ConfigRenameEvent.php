<?php

namespace Drupal\Core\Config;

/**
 * Configuration event fired when renaming a configuration object.
 */
class ConfigRenameEvent extends ConfigCrudEvent {

  /**
   * The old configuration object name.
   *
   * @var string
   */
  protected $oldName;

  public function __construct(StorableConfigBase $config, $old_name) {
    $this->config = $config;
    $this->oldName = $old_name;
  }

  /**
   * Gets the old configuration object name.
   *
   * @return string
   *   The old configuration object name.
   */
  public function getOldName() {
    return $this->oldName;
  }

}
