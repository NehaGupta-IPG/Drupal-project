<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel\Views;

use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the filter language handler.
 *
 * @see \Drupal\language\Plugin\views\filter\Language
 */
#[Group('language')]
#[RunTestsInSeparateProcesses]
class FilterLanguageTest extends LanguageTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Tests the language filter.
   */
  public function testFilter(): void {
    $view = Views::getView('test_view');
    foreach (['en' => 'John', 'xx-lolspeak' => 'George'] as $langcode => $name) {
      $view->setDisplay();
      $view->displayHandlers->get('default')->overrideOption('filters', [
        'langcode' => [
          'id' => 'langcode',
          'table' => 'views_test_data',
          'field' => 'langcode',
          'value' => [$langcode => $langcode],
        ],
      ]);
      $this->executeView($view);

      $expected = [
        ['name' => $name],
      ];
      $this->assertIdenticalResultset($view, $expected, ['views_test_data_name' => 'name']);

      $view->destroy();
    }
  }

  /**
   * Tests the "Limit to the languages used by the content" option.
   */
  public function testLimitToContentLanguages(): void {
    // With the option off (the default), the exposed filter offers every
    // language option, including the special ones (the site default, the
    // negotiated interface language, "Not specified" and "Not applicable").
    $this->assertSame([
      'All',
      '***LANGUAGE_site_default***',
      '***LANGUAGE_language_interface***',
      'en',
      'xx-lolspeak',
      'und',
      'zxx',
    ], array_keys($this->getExposedLanguageOptions(FALSE)));

    // With the option on, the exposed filter offers only the languages the
    // content uses, plus "- Any -" ("All"). The test data uses English and
    // Lolspeak; the other rows have no language.
    $this->assertEqualsCanonicalizing(['All', 'en', 'xx-lolspeak'], array_keys($this->getExposedLanguageOptions(TRUE)));

    // A language only appears once the content uses it: give the rows that have
    // no language the "Not specified" language and confirm it is now offered.
    \Drupal::database()->update('views_test_data')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', '')
      ->execute();
    $this->assertEqualsCanonicalizing(['All', 'en', 'xx-lolspeak', LanguageInterface::LANGCODE_NOT_SPECIFIED], array_keys($this->getExposedLanguageOptions(TRUE)));

    // When the content uses only a single language there is nothing meaningful
    // to choose from, so the exposed filter is hidden entirely.
    \Drupal::database()->update('views_test_data')
      ->fields(['langcode' => 'en'])
      ->execute();
    $this->assertFalse($this->languageFilter(TRUE)->access(\Drupal::currentUser()));
  }

  /**
   * Returns an exposed language filter handler.
   *
   * @param bool $limit_to_content_languages
   *   Whether to limit the options to the languages used by the content.
   */
  protected function languageFilter(bool $limit_to_content_languages): FilterPluginBase {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'langcode' => [
        'id' => 'langcode',
        'table' => 'views_test_data',
        'field' => 'langcode',
        'plugin_id' => 'language',
        'value' => [],
        'limit_to_content_languages' => $limit_to_content_languages,
        'exposed' => TRUE,
        'expose' => [
          'identifier' => 'langcode',
          'label' => 'Language',
          'use_operator' => FALSE,
          'operator_id' => '',
          'multiple' => FALSE,
          'required' => FALSE,
          'reduce' => FALSE,
        ],
      ],
    ]);
    return $view->display_handler->getHandlers('filter')['langcode'];
  }

  /**
   * Builds the exposed filter and returns the language options offered.
   *
   * @param bool $limit_to_content_languages
   *   Whether to limit the options to the languages used by the content.
   *
   * @return array
   *   The exposed language widget options, keyed by language code.
   */
  protected function getExposedLanguageOptions(bool $limit_to_content_languages): array {
    $form = [];
    $form_state = new FormState();
    $form_state->set('exposed', TRUE);
    $this->languageFilter($limit_to_content_languages)->buildExposedForm($form, $form_state);
    return $form['langcode']['#options'];
  }

}
