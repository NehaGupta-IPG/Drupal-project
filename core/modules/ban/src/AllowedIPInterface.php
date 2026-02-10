<?php

namespace Drupal\ban;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for Allowed IP config entities.
 */
interface AllowedIPInterface extends ConfigEntityInterface {

  public function getAllowedIp(): string;

  public function setAllowedIp(string $ip): void;

}
