<?php

namespace Drupal\list_filter_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a controller for testing the ListFilter functionality.
 *
 * @see \Drupal\Core\Render\Element\ListFilter
 * @see \Drupal\FunctionalJavascriptTests\ListFilterTest
 */
class ListFilterTestController extends ControllerBase {

  public function __invoke($test_case): array {
    $build = [];

    $filterElement = $this->getDefaultFilterElementSettings();
    $overrides = $this->overrideFilterElementSettings($test_case);
    $filterElement = array_merge($filterElement, $overrides);

    // Define the filter element.
    $build['filter'] = $filterElement;

    [$header, $rows] = $this->dataProvider();

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['filter-container'],
      ],
    ];

    if ($test_case === 'override_library') {
      $build['#attached']['library'][] = 'list_filter_test/list_filter_test';
      $build['filter']['#auto_activate'] = FALSE;
      $build['filter']['#suffix'] = "<span class='js-filter-count hidden'></span>";
    }
    return $build;
  }

  /**
   * Provides the data for the table.
   *
   * @return array
   *   An array containing the header and rows for the table.
   */
  protected function dataProvider(): array {
    $data = [
      [
        'name' => 'Inception',
        'description' => 'A thief who steals corporate secrets through dream-sharing technology.',
        'category' => 'Drama',
        'likes' => 1200,
        'release_date' => '2010-07-16',
      ],
      [
        'name' => 'The Godfather',
        'description' => 'The aging patriarch of an organized crime dynasty transfers control to his reluctant son.',
        'category' => 'Crime',
        'likes' => 1500,
        'release_date' => '1972-03-24',
      ],
      [
        'name' => 'Pulp Fiction',
        'description' => 'The lives of two mob hitmen, a boxer, and others intertwine in four tales of violence and redemption.',
        'category' => 'Drama',
        'likes' => 980,
        'release_date' => '1994-10-14',
      ],
      [
        'name' => 'The Matrix',
        'description' => 'A computer hacker learns about the true nature of his reality and his role in the war.',
        'category' => 'Action',
        'likes' => 1100,
        'release_date' => '1999-03-31',
      ],
      [
        'name' => 'Spirited Away',
        'description' => 'During her family\'s move to the suburbs, a sullen 10-year-old girl wanders into a world ruled by gods.',
        'category' => 'Animation',
        'likes' => 870,
        'release_date' => '2001-07-20',
      ],
    ];

    $header = [
      $this->t('Name'),
      $this->t('Description'),
      $this->t('Category'),
      $this->t('Likes'),
      $this->t('Release Date'),
    ];

    $rows = [];
    foreach ($data as $row) {
      $rows[] = [
        'data' => [
          ['data' => $row['name'], 'class' => ['name']],
          ['data' => $row['description'], 'class' => ['description']],
          ['data' => $row['category'], 'class' => ['category']],
          ['data' => $row['likes'], 'class' => ['likes']],
          ['data' => $row['release_date'], 'class' => ['release_date']],
        ],
      ];
    }
    return [$header, $rows];
  }

  /**
   * Provides the default settings for the filter element.
   *
   * @return array
   *   The default filter element settings.
   */
  protected function getDefaultFilterElementSettings(): array {
    return [
      '#type' => 'list_filter',
      '#title' => $this->t('Filter users'),
      '#placeholder' => $this->t('Search by name or email'),
      '#list_container_selector' => '.filter-container',
      '#list_item' => 'tbody tr',
      '#list_text' => '.name, .description, .category, .likes, .release_date',
      '#minimum_filter_length' => 1,
      '#search_start_of_words' => FALSE,
      '#auto_activate' => TRUE,
      '#attributes' => [
        'class' => ['filter-input'],
      ],
    ];
  }

  /**
   * Overrides the default filter element settings based on the test case.
   *
   * @param string $test_case
   *   The test case identifier.
   *
   * @return array
   *   The modified filter element settings.
   */
  protected function overrideFilterElementSettings($test_case): array {
    $filterElement = $this->getDefaultFilterElementSettings();
    switch ($test_case) {
      case 'minimum_filter_length':
        $filterElement['#minimum_filter_length'] = 5;
        break;

      case 'search_start_of_words':
        $filterElement['#search_start_of_words'] = TRUE;
        break;

      case 'auto_activate_false':
        $filterElement['#auto_activate'] = FALSE;
        break;

      default:
        return $filterElement;
    }
    return $filterElement;
  }

}