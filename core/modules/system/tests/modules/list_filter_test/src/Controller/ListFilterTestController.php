<?php

declare(strict_types=1);

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

    [$header, $rows] = $this->drinksRecipes();

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
      $build['filter']['#auto_activate'] = TRUE;
      $build['filter']['#suffix'] = "<span class='js-filter-count hidden'></span>";
    }
    return $build;
  }

  /**
   * Provides a build with two filter elements and their respective tables.
   *
   * @return array
   *   A render array containing two filter elements and their associated tables.
   */
  public function multipleFiltersOnPage(): array {
    [$header, $rows] = $this->drinksRecipes();
    $build['filter_one'] = $this->getDefaultFilterElementSettings();
    $build['filter_one']['#title'] = $this->t('Filter Recipes');
    $build['filter_one']['#list_container_selector'] = '.filter-container-one';
    $build['filter_one']['#attributes']['class'] = ['filter-input-one'];
    $build['filter_one']['#list_text'] = '.name, .category, .is_alcoholic, .ingredients, .instructions';

    $build['table_one'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['filter-container-one'],
      ],
    ];

    $build['filter_two'] = $this->getDefaultFilterElementSettings();
    $build['filter_two']['#title'] = $this->t('Filter Ingredients');
    $build['filter_two']['#list_container_selector'] = '.filter-container-two';
    $build['filter_two']['#attributes']['class'] = ['filter-input-two'];
    $build['filter_two']['#list_text'] = '.ingredient, .type, .is_alcoholic';

    [$header, $rows] = $this->ingredientsList();
    $build['table_two'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['filter-container-two'],
      ],
    ];
    return $build;
  }

  /**
   * Provides a list of drinks recipes.
   */
  protected function drinksRecipes(): array {
    $data = [
      [
        'name' => 'Lemonade',
        'category' => 'Non-alcoholic',
        'is_alcoholic' => 'No',
        'ingredients' => 'Lemon, Sugar, Water',
        'instructions' => 'Mix lemon juice, sugar, and water. Serve chilled.',
      ],
      [
        'name' => 'Mojito',
        'category' => 'Cocktail',
        'is_alcoholic' => 'Yes',
        'ingredients' => 'Rum, Mint, Sugar, Lime, Soda Water',
        'instructions' => 'Muddle mint with sugar and lime. Add rum and soda water. Serve with ice.',
      ],
      [
        'name' => 'Espresso Martini',
        'category' => 'Cocktail',
        'is_alcoholic' => 'Yes',
        'ingredients' => 'Vodka, Coffee Liqueur, Espresso',
        'instructions' => 'Shake vodka, coffee liqueur, and espresso with ice. Strain into a glass.',
      ],
      [
        'name' => 'Iced Tea',
        'category' => 'Non-alcoholic',
        'is_alcoholic' => 'No',
        'ingredients' => 'Tea, Lemon, Sugar, Ice',
        'instructions' => 'Brew tea, add lemon and sugar, pour over ice.',
      ],
      [
        'name' => 'Virgin Mojito',
        'category' => 'Mocktail',
        'is_alcoholic' => 'No',
        'ingredients' => 'Mint, Sugar, Lime, Soda Water',
        'instructions' => 'Muddle mint with sugar and lime. Add soda water. Serve with ice.',
      ],
    ];

    $header = [
      $this->t('Name'),
      $this->t('Category'),
      $this->t('Alcoholic'),
      $this->t('Ingredients'),
      $this->t('Instructions'),
    ];

    $rows = [];
    foreach ($data as $row) {
      $rows[] = [
        'data' => [
          ['data' => $row['name'], 'class' => ['name']],
          ['data' => $row['category'], 'class' => ['category']],
          ['data' => $row['is_alcoholic'], 'class' => ['is_alcoholic']],
          ['data' => $row['ingredients'], 'class' => ['ingredients']],
          ['data' => $row['instructions'], 'class' => ['instructions']],
        ],
      ];
    }
    return [$header, $rows];
  }

  /**
   * Provides a list of drink ingredients.
   */
  protected function ingredientsList(): array {
    $data = [
      [
        'ingredient' => 'Lemon',
        'type' => 'Fruit',
        'is_alcoholic' => 'No',
      ],
      [
        'ingredient' => 'Rum',
        'type' => 'Spirit',
        'is_alcoholic' => 'Yes',
      ],
      [
        'ingredient' => 'Mint',
        'type' => 'Herb',
        'is_alcoholic' => 'No',
      ],
      [
        'ingredient' => 'Vodka',
        'type' => 'Spirit',
        'is_alcoholic' => 'Yes',
      ],
      [
        'ingredient' => 'Coffee Liqueur',
        'type' => 'Liqueur',
        'is_alcoholic' => 'Yes',
      ],
      [
        'ingredient' => 'Tea',
        'type' => 'Beverage',
        'is_alcoholic' => 'No',
      ],
      [
        'ingredient' => 'Sugar',
        'type' => 'Sweetener',
        'is_alcoholic' => 'No',
      ],
      [
        'ingredient' => 'Lime',
        'type' => 'Fruit',
        'is_alcoholic' => 'No',
      ],
      [
        'ingredient' => 'Soda Water',
        'type' => 'Beverage',
        'is_alcoholic' => 'No',
      ],
      [
        'ingredient' => 'Espresso',
        'type' => 'Beverage',
        'is_alcoholic' => 'No',
      ],
    ];

    $header = [
      $this->t('Ingredient'),
      $this->t('Type'),
      $this->t('Alcoholic'),
    ];

    $rows = [];
    foreach ($data as $row) {
      $rows[] = [
        'data' => [
          ['data' => $row['ingredient'], 'class' => ['ingredient']],
          ['data' => $row['type'], 'class' => ['type']],
          ['data' => $row['is_alcoholic'], 'class' => ['is_alcoholic']],
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
      '#list_text' => '.name, .ingredients, .instructions, .category, .is_alcoholic',
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
