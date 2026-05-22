<?php

namespace Drupal\media;

use Drupal\views\EntityViewsData;

/**
 * Provides the Views data for the media entity type.
 */
class MediaViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    if ($this->connection->driver() == 'mongodb') {
      $data_table = 'media';
      $revision_table = 'media';
    }
    else {
      $data_table = 'media_field_data';
      $revision_table = 'media_field_revision';
    }

    $data[$data_table]['table']['wizard_id'] = 'media';
    $data[$revision_table]['table']['wizard_id'] = 'media_revision';

    $data[$data_table]['user_name']['filter'] = $data[$data_table]['uid']['filter'];
    $data[$data_table]['user_name']['filter']['title'] = $this->t('Authored by');
    $data[$data_table]['user_name']['filter']['help'] = $this->t('The username of the content author.');
    $data[$data_table]['user_name']['filter']['id'] = 'user_name';
    $data[$data_table]['user_name']['filter']['real field'] = 'uid';

    $data[$data_table]['status_extra'] = [
      'title' => $this->t('Published status or admin user'),
      'help' => $this->t('Filters out unpublished media if the current user cannot view it.'),
      'filter' => [
        'field' => 'status',
        'id' => 'media_status',
        'label' => $this->t('Published status or admin user'),
      ],
    ];

    return $data;
  }

}
