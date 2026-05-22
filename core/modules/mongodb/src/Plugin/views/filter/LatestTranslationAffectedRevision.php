<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\LatestTranslationAffectedRevision as CoreLatestTranslationAffectedRevision;

/**
 * Overriding the views filter plugin "latest_translation_affected_revision".
 */
class LatestTranslationAffectedRevision extends CoreLatestTranslationAffectedRevision {

  use FilterPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $field = 'revision_translation_affected';
    if ($all_revisions_table = $this->query->getAllRevisionsTable()) {
      $field = "$all_revisions_table.$field";
    }
    elseif ($current_revision_table = $this->query->getCurrentRevisionTable()) {
      $field = "$current_revision_table.$field";
    }

    $condition = $this->query->getConnection()->condition('AND');
    $condition->condition($field, NULL, 'IS NOT NULL');
    $condition->condition($field, TRUE);
    $this->query->addCondition($this->options['group'], $condition);
    $this->query->setLatestTranslationAffectedRevision();
  }

}
