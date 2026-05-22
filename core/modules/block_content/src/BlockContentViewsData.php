<?php

namespace Drupal\block_content;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the block_content entity type.
 */
class BlockContentViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {

    $data = parent::getViewsData();

    if ($this->connection->driver() == 'mongodb') {
      $data_table = 'block_content';
      $revision_table = 'block_content';
    }
    else {
      $data_table = 'block_content_field_data';
      $revision_table = 'block_content_field_revision';
    }

    $data[$data_table]['id']['field']['id'] = 'field';

    $data[$data_table]['info']['field']['id'] = 'field';
    $data[$data_table]['info']['field']['link_to_entity default'] = TRUE;

    $data[$data_table]['type']['field']['id'] = 'field';

    $data[$data_table]['table']['wizard_id'] = 'block_content';

    $data['block_content']['block_content_listing_empty'] = [
      'title' => $this->t('Empty block library behavior'),
      'help' => $this->t('Provides a link to add a new block.'),
      'area' => [
        'id' => 'block_content_listing_empty',
      ],
    ];
    // Advertise this table as a possible base table.
    $data[$revision_table]['table']['base']['help'] = $this->t('Block Content revision is a history of changes to block content.');
    $data[$revision_table]['table']['base']['defaults']['title'] = 'info';

    return $data;
  }

}
