<?php

namespace Drupal\ban\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ban\AllowedIPInterface;

/**
 * Config entity for an IP that is allowed (never banned).
 *
 * @ConfigEntityType(
 *   id = "ban_allowed_ip",
 *   label = @Translation("Allowed IP"),
 *   handlers = {
 *     "list_builder" = "Drupal\ban\Controller\AllowedIPListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ban\Form\AllowedIPForm",
 *       "edit" = "Drupal\ban\Form\AllowedIPForm",
 *       "delete" = "Drupal\ban\Form\AllowedIPDeleteForm"
 *     }
 *   },
 *   config_prefix = "allowed",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "allowedIp"
 *   },
 *   config_export = {
 *     "id",
 *     "allowedIp"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/people/ban-allowed/{ban_allowed_ip}",
 *     "delete-form" = "/admin/config/people/ban-allowed/{ban_allowed_ip}/delete"
 *   }
 * )
 */
class AllowedIP extends ConfigEntityBase implements AllowedIPInterface {

  /** @var string */
  public $id;

  /** @var string */
  public $allowedIp = '';

  public function getAllowedIp(): string {
    return (string) $this->allowedIp;
  }

  public function setAllowedIp(string $ip): void {
    $this->allowedIp = $ip;
  }

}
