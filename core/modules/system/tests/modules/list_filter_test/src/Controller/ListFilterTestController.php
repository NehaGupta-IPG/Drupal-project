<?php

namespace Drupal\list_filter_test\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Controller for list-filter test routes.
 */
class ListFilterTestController {

  use StringTranslationTrait;

  /**
   * Simple filtering with DIV and P elements.
   */
  public function contentDivAndP() {
    $build = [];

    $build['list_filter'] = [
      '#type' => 'list_filter',
      '#placeholder' => $this->t('Filter list'),
      '#attributes' => [
        'title' => $this->t('Enter a part of an item to filter by.'),
      ],
      '#list_container_id' => 'filter-container',
      '#list_item' => '.filter-item',
      '#list_text' => '.filter-text',
    ];

    $build['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['filter-container'],
      ],
    ];

    foreach (['alpha', 'beta', 'gamma'] as $row) {
      $build['container'][$row] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['filter-item'],
        ],
        'paragraph' => [
          '#markup' => "<p><span class=\"filter-text\">$row</span> (excluded text)</p>",
          '#allowed_tags' => ['p', 'span'],
        ],
      ];
    }

    return $build;
  }

  /**
   * Simple filtering with a list.
   */
  public function contentList() {
    $build = [];

    $build['list_filter'] = [
      '#type' => 'list_filter',
      '#placeholder' => $this->t('Filter list'),
      '#attributes' => [
        'title' => $this->t('Enter a part of an item to filter by.'),
      ],
      '#list_container_id' => 'filter-container',
      '#list_item' => 'li',
      '#list_text' => '',
    ];

    $build['container'] = [
      '#theme' => 'item_list',
      '#attributes' => [
        'id' => ['filter-container'],
      ],
    ];

    foreach (['alpha', 'beta', 'gamma'] as $row) {
      $build['container']['#items'][] = $row;
    }

    return $build;
  }

  /**
   * Simple filtering with a table.
   */
  public function contentTable() {
    $build = [];

    $build['list_filter'] = [
      '#type' => 'list_filter',
      '#placeholder' => $this->t('Filter list'),
      '#attributes' => [
        'title' => $this->t('Enter a part of an item to filter by.'),
      ],
      '#list_container_id' => 'filter-container',
      '#list_item' => 'tr',
      '#list_text' => '.list-text',
    ];

    $build['container'] = [
      '#theme' => 'table',
      '#attributes' => [
        'id' => ['filter-container'],
      ],
    ];

    foreach (['alpha', 'beta', 'gamma'] as $row) {
      $build['container']['#rows'][] = [
        [
          'data' => $row,
          'class' => 'list-text',
        ],
        '(excluded text)',
      ];
    }

    return $build;
  }

  /**
   * Grouped filtering with DETAILS and DIV and P elements.
   */
  public function contentDetails() {
    $build = [];

    $build['list_filter'] = [
      '#type' => 'list_filter',
      '#placeholder' => $this->t('Filter list'),
      '#attributes' => [
        'title' => $this->t('Enter a part of an item to filter by.'),
      ],
      '#list_container_id' => 'filter-container',
      '#list_item' => '.filter-item',
      '#list_text' => '.filter-text',
      '#list_group' => 'details',
      '#library' => 'core/drupal.list-filter.details',
    ];

    $build['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => ['filter-container'],
      ],
    ];

    $build['container']['letters'] = [
      '#type' => 'details',
      '#title' => 'Letters',
      '#open' => TRUE,
    ];
    foreach (['alpha', 'beta', 'gamma'] as $row) {
      $build['container']['letters'][$row] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['filter-item'],
        ],
        'paragraph' => [
          '#markup' => "<p><span class=\"filter-text\">$row</span> (excluded text)</p>",
          '#allowed_tags' => ['p', 'span'],
        ],
      ];
    }

    $build['container']['numbers'] = [
      '#type' => 'details',
      '#title' => 'Numbers',
      '#open' => TRUE,
    ];
    foreach (['one', 'two', 'three'] as $row) {
      $build['container']['numbers'][$row] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['filter-item'],
        ],
        'paragraph' => [
          '#markup' => "<p><span class=\"filter-text\">$row</span> (excluded text)</p>",
          '#allowed_tags' => ['p', 'span'],
        ],
      ];
    }

    return $build;
  }

  /**
   * Grouped filtering with a table.
   */
  public function contentTableWithHeaders() {
    $build = [];

    $build['list_filter'] = [
      '#type' => 'list_filter',
      '#placeholder' => $this->t('Filter list'),
      '#attributes' => [
        'title' => $this->t('Enter a part of an item to filter by.'),
      ],
      '#list_container_id' => 'filter-container',
      '#list_item' => 'tr:has(td.list-text)',
      '#list_group' => 'tr:has(td.list-header)',
      '#list_text' => '.list-text',
      '#library' => 'core/drupal.list-filter.sibling-groups',
    ];

    $build['container'] = [
      '#theme' => 'table',
      '#attributes' => [
        'id' => ['filter-container'],
      ],
    ];

    $build['container']['#rows'][] = [
      [
        'data' => 'Letters',
        'class' => 'list-header',
        'colspan' => 2,
      ],
    ];
    foreach (['alpha', 'beta', 'gamma'] as $row) {
      $build['container']['#rows'][] = [
        [
          'data' => $row,
          'class' => 'list-text',
        ],
        '(excluded text)',
      ];
    }
    $build['container']['#rows'][] = [
      [
        'data' => 'Numbers',
        'class' => 'list-header',
        'colspan' => 2,
      ],
    ];
    foreach (['one', 'two', 'three'] as $row) {
      $build['container']['#rows'][] = [
        [
          'data' => $row,
          'class' => 'list-text',
        ],
        '(excluded text)',
      ];
    }

    return $build;
  }

}
