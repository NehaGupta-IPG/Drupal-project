<?php

namespace Drupal\ban\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Allowed IPs.
 */
class AllowedIPListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['allowedIp'] = $this->t('Allowed IP');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['allowedIp'] = $entity->getAllowedIp();
    return $row + parent::buildRow($entity);
  }

}
