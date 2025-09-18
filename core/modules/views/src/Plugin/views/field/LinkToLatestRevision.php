<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Field handler to view the latest revision of an entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("link_to_latest_revision")
 */
class LinkToLatestRevision extends EntityLink {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    $entity = $this->getEntity($row);
    $template = !$entity->isLatestRevision() && $entity->hasLinkTemplate('latest-version') ? 'latest-version' : $this->getEntityLinkTemplate();
    $entity = $this->getEntity($row);
    if ($this->languageManager->isMultilingual()) {
      $entity = $this->getEntityTranslationByRelationship($entity, $row);
      $this->addLangcode($row);
    }
    return $entity->toUrl($template)->setAbsolute($this->options['absolute']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('View Latest Revision');
  }

}
