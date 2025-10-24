<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the usage of form caching for AJAX forms.
 */
#[Group('Ajax')]
#[RunTestsInSeparateProcesses]
class AjaxFormCacheTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test', 'ajax_forms_test', 'node', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the usage of form cache for AJAX forms.
   */
  public function testFormCacheUsage(): void {
    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $key_value_expirable */
    $key_value_expirable = \Drupal::service('keyvalue.expirable')->get('form');
    $this->drupalLogin($this->rootUser);

    // Ensure that the cache is empty.
    $this->assertCount(0, $key_value_expirable->getAll());

    // Visit an AJAX form that is not cached, 3 times.
    $uncached_form_url = Url::fromRoute('ajax_forms_test.commands_form');
    $this->drupalGet($uncached_form_url);
    $this->drupalGet($uncached_form_url);
    $this->drupalGet($uncached_form_url);

    // The number of cache entries should not have changed.
    $this->assertCount(0, $key_value_expirable->getAll());
  }

  /**
   * Tests AJAX forms in blocks.
   */
  public function testBlockForms(): void {
    $this->container->get('module_installer')->install(['block', 'search']);
    $this->rebuildContainer();
    $this->drupalLogin($this->rootUser);

    $this->drupalPlaceBlock('search_form_block', ['weight' => -5]);
    $this->drupalPlaceBlock('ajax_forms_test_block');

    $this->drupalGet('');
    $session = $this->getSession();

    // Select first option and trigger ajax update.
    $session->getPage()->selectFieldOption('edit-test1', 'option1');

    // DOM update: The InsertCommand in the AJAX response changes the text
    // in the option element to 'Option1!!!'.
    $opt1_selector = $this->assertSession()->waitForElement('css', "select[data-drupal-selector='edit-test1'] option:contains('Option 1!!!')");
    $this->assertNotEmpty($opt1_selector);
    $this->assertTrue($opt1_selector->isSelected());

    // Confirm option 3 exists.
    $page = $session->getPage();
    $opt3_selector = $page->find('xpath', '//select[@data-drupal-selector="edit-test1"]//option[@value="option3"]');
    $this->assertNotEmpty($opt3_selector);

    // Confirm success message appears after a submit.
    $page->findButton('edit-submit')->click();
    $this->assertSession()->waitForButton('edit-submit');
    $updated_page = $session->getPage();
    $updated_page->hasContent('Submission successful.');
  }

  /**
   * Tests AJAX forms on pages with a query string.
   */
  public function testQueryString(): void {
    $this->container->get('module_installer')->install(['block']);
    $this->drupalLogin($this->rootUser);

    $this->drupalPlaceBlock('ajax_forms_test_block');

    $url = Url::fromRoute('entity.user.canonical', ['user' => $this->rootUser->id()], ['query' => ['foo' => 'bar']]);
    $this->drupalGet($url);

    $session = $this->getSession();
    // Select first option and trigger ajax update.
    $session->getPage()->selectFieldOption('edit-test1', 'option1');

    // DOM update: The InsertCommand in the AJAX response changes the text
    // in the option element to 'Option1!!!'.
    $opt1_selector = $this->assertSession()->waitForElement('css', "option:contains('Option 1!!!')");
    $this->assertNotEmpty($opt1_selector);

    $url->setOption('query', [
      'foo' => 'bar',
    ]);
    $this->assertSession()->addressEquals($url);
  }

  /**
   * Tests form behavior when cache expires.
   */
  public function testExpiredCache(): void {
    // Ensure required modules are installed and available in this process.
    $this->rebuildContainer();

    // Create a vocabulary to reference.
    $vocab = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'tags',
    ]);
    $vocab->save();

    // Create a content type.
    $type = $this->createContentType([
      'type' => 'ajax_ref_test',
      'name' => 'AJAX Ref Test',
    ]);
    $bundle = $type->id();

    // Create an unlimited entity reference field targeting taxonomy terms.
    $field_name = 'field_terms';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => 'Terms',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['tags' => 'tags'],
        ],
      ],
    ])->save();

    // Ensure form display uses the standard autocomplete widget.
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $form_display = $display_repository->getFormDisplay('node', $bundle);
    $form_display->setComponent($field_name, [
      'type' => 'entity_reference_autocomplete',
    ])->save();

    // Set the form_cache_expiry global setting to 0.
    $settings['settings']['form_cache_expiry'] = (object) [
      'value' => 0,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Open the content type add form with a user who can create this content.
    $user = $this->drupalCreateUser([
      'access content',
      'create ' . $bundle . ' content',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('node/add/' . $bundle);

    $assert = $this->assertSession();
    // Wait for the initial autocomplete input to be present.
    $assert->waitForElement('css', sprintf('[name="%s[0][target_id]"]', $field_name));

    // Click the "Add another item" button twice to add two empty items.
    $add_more_selector = sprintf('[data-drupal-selector="edit-%s-add-more"]', str_replace('_', '-', $field_name));
    $this->getSession()->getPage()->find('css', $add_more_selector)->click();
    $assert->waitForElement('css', sprintf('[name="%s[1][target_id]"]', $field_name));
    $this->getSession()->getPage()->find('css', $add_more_selector)->click();
    $assert->waitForElement('css', sprintf('[name="%s[2][target_id]"]', $field_name));

    // Count how many item inputs there are for that field.
    $assert->elementsCount('css', sprintf('input.ui-autocomplete-input[name^="%s"][name$="[target_id]"]', $field_name), 3);
  }

}
