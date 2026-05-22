<?php

namespace Drupal\taxonomy;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the taxonomy entity type.
 */
class TermViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    if ($this->connection->driver() == 'mongodb') {
      $data_table = 'taxonomy_term_data';
      $parent_table = 'taxonomy_term_data';
      $node_table = 'node';
    }
    else {
      $data_table = 'taxonomy_term_field_data';
      $parent_table = 'taxonomy_term__parent';
      $node_table = 'node_field_data';
    }

    $data[$data_table]['table']['base']['help'] = $this->t('Taxonomy terms are attached to nodes.');
    $data[$data_table]['table']['base']['access query tag'] = 'taxonomy_term_access';
    $data[$data_table]['table']['wizard_id'] = 'taxonomy_term';

    $data[$data_table]['table']['join'] = [
      // This is provided for the many_to_one argument.
      'taxonomy_index' => [
        'field' => 'tid',
        'left_field' => 'tid',
      ],
    ];

    $data[$data_table]['tid']['help'] = $this->t('The tid of a taxonomy term.');

    $data[$data_table]['tid']['argument']['id'] = 'taxonomy';
    $data[$data_table]['tid']['argument']['name field'] = 'name';
    $data[$data_table]['tid']['argument']['zero is null'] = TRUE;

    $data[$data_table]['tid']['filter']['id'] = 'taxonomy_index_tid';
    $data[$data_table]['tid']['filter']['title'] = $this->t('Term');
    $data[$data_table]['tid']['filter']['help'] = $this->t('Taxonomy term chosen from autocomplete or select widget.');
    $data[$data_table]['tid']['filter']['hierarchy table'] = $parent_table;
    $data[$data_table]['tid']['filter']['numeric'] = TRUE;

    $data[$data_table]['tid_raw'] = [
      'title' => $this->t('Term ID'),
      'help' => $this->t('The tid of a taxonomy term.'),
      'real field' => 'tid',
      'filter' => [
        'id' => 'numeric',
        'allow empty' => TRUE,
      ],
    ];

    $data[$data_table]['tid_representative'] = [
      'relationship' => [
        'title' => $this->t('Representative node'),
        'label'  => $this->t('Representative node'),
        'help' => $this->t('Obtains a single representative node for each term, according to a chosen sort criterion.'),
        'id' => 'groupwise_max',
        'relationship field' => 'tid',
        'outer field' => 'taxonomy_term_field_data.tid',
        'argument table' => $data_table,
        'argument field' => 'tid',
        'base'   => $node_table,
        'field'  => 'nid',
        'relationship' => "$node_table:term_node_tid",
      ],
    ];

    $data[$data_table]['vid']['help'] = $this->t('Filter the results of "Taxonomy: Term" to a particular vocabulary.');
    $data[$data_table]['vid']['field']['help'] = t('The vocabulary name.');
    $data[$data_table]['vid']['argument']['id'] = 'vocabulary_vid';

    $data[$data_table]['vid']['sort']['title'] = t('Vocabulary ID');
    $data[$data_table]['vid']['sort']['help'] = t('The raw vocabulary ID.');

    $data[$data_table]['name']['field']['id'] = 'term_name';
    $data[$data_table]['name']['argument']['many to one'] = TRUE;
    $data[$data_table]['name']['argument']['empty field name'] = $this->t('Uncategorized');

    $data[$data_table]['description__value']['field']['click sortable'] = FALSE;

    $data[$data_table]['changed']['title'] = $this->t('Updated date');
    $data[$data_table]['changed']['help'] = $this->t('The date the term was last updated.');

    $data[$data_table]['changed_fulldate'] = [
      'title' => $this->t('Updated date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_fulldate',
      ],
    ];

    $data[$data_table]['changed_year_month'] = [
      'title' => $this->t('Updated year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year_month',
      ],
    ];

    $data[$data_table]['changed_year'] = [
      'title' => $this->t('Updated year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year',
      ],
    ];

    $data[$data_table]['changed_month'] = [
      'title' => $this->t('Updated month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_month',
      ],
    ];

    $data[$data_table]['changed_day'] = [
      'title' => $this->t('Updated day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_day',
      ],
    ];

    $data[$data_table]['changed_week'] = [
      'title' => $this->t('Updated week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_week',
      ],
    ];

    $data['taxonomy_index']['table']['group'] = $this->t('Taxonomy term');

    $data['taxonomy_index']['table']['join'] = [
      $data_table => [
        // Links directly to taxonomy_term_field_data via tid
        'left_field' => 'tid',
        'field' => 'tid',
      ],
      $node_table => [
        // Links directly to node via nid
        'left_field' => 'nid',
        'field' => 'nid',
      ],
    ];

    if ($this->connection->driver() != 'mongodb') {
      $data['taxonomy_index']['table']['join']['taxonomy_term__parent'] = [
        'left_field' => 'entity_id',
        'field' => 'tid',
      ];
    }

    $data['taxonomy_index']['nid'] = [
      'title' => $this->t('Content with term'),
      'help' => $this->t('Relate all content tagged with a term.'),
      'relationship' => [
        'id' => 'standard',
        'base' => $node_table,
        'base field' => 'nid',
        'label' => $this->t('node'),
        'skip base' => $node_table,
      ],
    ];

    // @todo This stuff needs to move to a node field since really it's all
    //   about nodes.
    $data['taxonomy_index']['tid'] = [
      'group' => $this->t('Content'),
      'title' => $this->t('Has taxonomy term ID'),
      'help' => $this->t('Display content if it has the selected taxonomy terms.'),
      'argument' => [
        'id' => 'taxonomy_index_tid',
        'name table' => $data_table,
        'name field' => 'name',
        'empty field name' => $this->t('Uncategorized'),
        'numeric' => TRUE,
        'skip base' => $data_table,
      ],
      'filter' => [
        'title' => $this->t('Has taxonomy term'),
        'id' => 'taxonomy_index_tid',
        'hierarchy table' => $parent_table,
        'numeric' => TRUE,
        'skip base' => $data_table,
        'allow empty' => TRUE,
      ],
    ];

    $data['taxonomy_index']['status'] = [
      'title' => $this->t('Publish status'),
      'help' => $this->t('Whether or not the content related to a term is published.'),
      'filter' => [
        'id' => 'boolean',
        'label' => $this->t('Published status'),
        'type' => 'yes-no',
      ],
    ];

    $data['taxonomy_index']['sticky'] = [
      'title' => $this->t('Sticky status'),
      'help' => $this->t('Whether or not the content related to a term is sticky.'),
      'filter' => [
        'id' => 'boolean',
        'label' => $this->t('Sticky status'),
        'type' => 'yes-no',
      ],
      'sort' => [
        'id' => 'standard',
        'help' => $this->t('Whether or not the content related to a term is sticky. To list sticky content first, set this to descending.'),
      ],
    ];

    $data['taxonomy_index']['created'] = [
      'title' => $this->t('Post date'),
      'help' => $this->t('The date the content related to a term was posted.'),
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ];

    if ($this->connection->driver() != 'mongodb') {
      // Link to self through left.parent = right.tid (going down in depth).
      $data['taxonomy_term__parent']['table']['join']['taxonomy_term__parent'] = [
        'left_field' => 'entity_id',
        'field' => 'parent_target_id',
      ];

      $data['taxonomy_term__parent']['parent_target_id']['help'] = $this->t('The parent term of the term. This can produce duplicate entries if you are using a vocabulary that allows multiple parents.');
      $data['taxonomy_term__parent']['parent_target_id']['relationship']['label'] = $this->t('Parent');
      $data['taxonomy_term__parent']['parent_target_id']['argument']['id'] = 'taxonomy';
    }

    return $data;
  }

}
