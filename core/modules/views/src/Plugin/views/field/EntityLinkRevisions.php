<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;

/**
 * Field handler to present a link to revisions page of an entity.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("entity_link_revisions")]
class EntityLinkRevisions extends EntityLink {

  /**
   * {@inheritdoc}
   */
  protected function getEntityLinkTemplate() {
    return 'version-history';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('revisions');
  }

}
