<?php

namespace Drupal\node\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to revert a node to a revision.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("node_revision_link_revert")]
class RevisionLinkRevert extends RevisionLink {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntity($row);
    if (!$node) {
      return NULL;
    }
    $nid = $node->id();
    $node_revision = $node->getRevisionId();
    // Get language of current row.
    /** @var \Drupal\node\NodeInterface $translation */
    $translation = $this->getEntityTranslationByRelationship($node, $row);
    $langcode = $translation->language()->getId();
    // Detect if the latest version has any translation.
    $original = $node->original;
    $languages = $original->getTranslationLanguages();
    $has_translations = (count($languages) > 1);

    // When translations enabled default revision revert form will revert all
    // translations, but revert translation form only for specific language.
    return $has_translations ?
      Url::fromRoute('node.revision_revert_translation_confirm', [
        'node' => $nid,
        'node_revision' => $node_revision,
        'langcode' => $langcode,
      ]) :
      Url::fromRoute('node.revision_revert_confirm', [
        'node' => $nid,
        'node_revision' => $node_revision,
      ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('Revert');
  }

}
