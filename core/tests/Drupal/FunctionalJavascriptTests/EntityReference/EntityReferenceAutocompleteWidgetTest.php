<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\EntityReference;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the output of entity reference autocomplete widgets.
 */
#[Group('entity_reference')]
#[RunTestsInSeparateProcesses]
class EntityReferenceAutocompleteWidgetTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'entity_test',
    'entity_reference_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a Content type and two test nodes.
    $this->createContentType(['type' => 'page']);
    $this->createNode(['title' => 'Test page']);
    $this->createNode(['title' => 'Page test']);

    $user = $this->drupalCreateUser([
      'access content',
      'create page content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests progressively enhanced ambiguity messages for single and tag inputs.
   */
  public function testAmbiguousReferenceMessages(): void {
    for ($i = 0; $i < 6; $i++) {
      $this->createNode(['title' => 'Example']);
    }
    $display = EntityFormDisplay::load('node.page.default');
    foreach (['field_single' => 'entity_reference_autocomplete', 'field_tags' => 'entity_reference_autocomplete_tags'] as $field_name => $widget) {
      $this->createEntityReferenceField('node', 'page', $field_name, $field_name, 'node', 'default', ['target_bundles' => ['page']], -1);
      $display->setComponent($field_name, ['type' => $widget]);
    }
    $display->save();

    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Ambiguous references');
    $page->fillField('field_single[0][target_id]', 'Example');
    $page->fillField('field_tags[target_id]', 'Example');
    $page->pressButton('Save');

    $messages = $page->findAll('css', '[data-drupal-autocomplete-message]');
    $this->assertCount(2, $messages);
    foreach ($messages as $message) {
      // Check initial CSS visibility before the behavior takes control.
      $selector = json_encode('[data-drupal-autocomplete-message="' . $message->getAttribute('data-drupal-autocomplete-message') . '"]');
      $this->getSession()->executeScript(<<<JS
        const message = document.querySelector($selector);
        const full = message.querySelector('.autocomplete-message-full');
        const short = message.querySelector('.autocomplete-message-short');
        full.classList.remove('visually-hidden');
        full.classList.add('js-hide');
        short.hidden = false;
        short.classList.add('js-show');
        JS);
      $this->assertFalse($message->find('css', '.autocomplete-message-full')->isVisible());
      $this->assertTrue($message->find('css', '.autocomplete-message-short')->isVisible());
      $this->getSession()->executeScript("Drupal.attachBehaviors(document.querySelector($selector));");
      $short = $message->find('css', '.autocomplete-message-short');
      $this->assertTrue($short->isVisible());
      $this->assertFalse($short->hasClass('js-show'));
      $this->assertSame('true', $short->getAttribute('aria-hidden'));
      $this->assertSame('Multiple content items match Example. Select a match from the autocomplete suggestions.', $short->getText());
      $full = $message->find('css', '.autocomplete-message-full');
      $this->assertTrue($full->hasClass('visually-hidden'));
      $this->assertFalse($full->hasClass('js-hide'));
      $this->assertNull($full->getAttribute('hidden'));
      $this->assertNull($full->getAttribute('aria-hidden'));
      $this->assertStringContainsString('To select a specific match, append its ID in parentheses', $full->getText());
      $this->assertCount(5, $full->findAll('css', 'ul > li > code'));
    }

    // Reattaching a message must reflect the current autocomplete availability.
    $input_id = json_encode($messages[0]->getAttribute('data-drupal-autocomplete-message'));
    $this->getSession()->executeScript("jQuery(document.getElementById($input_id)).autocomplete('destroy');");
    $this->getSession()->executeScript('Drupal.attachBehaviors(document.querySelector("[data-drupal-autocomplete-message]"));');
    $this->assertFalse($messages[0]->find('css', '.autocomplete-message-full')->hasClass('visually-hidden'));
    $this->assertFalse($messages[0]->find('css', '.autocomplete-message-short')->isVisible());
    $this->getSession()->executeScript("once.remove('autocomplete', document.getElementById($input_id)); Drupal.attachBehaviors(document);");
    $this->assertTrue($messages[0]->find('css', '.autocomplete-message-full')->hasClass('visually-hidden'));
    $this->assertTrue($messages[0]->find('css', '.autocomplete-message-short')->isVisible());
  }

  /**
   * Tests that the default autocomplete widget return the correct results.
   */
  public function testEntityReferenceAutocompleteWidget(): void {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Create an entity reference field and use the default 'CONTAINS' match
    // operator.
    $field_name = 'field_test';
    $this->createEntityReferenceField(
      'node',
      'page',
      $field_name,
      $field_name,
      'node',
      'default',
      ['target_bundles' => ['page'], 'sort' => ['field' => 'title', 'direction' => 'DESC']]);
    $form_display = $display_repository->getFormDisplay('node', 'page');
    $form_display->setComponent($field_name, [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
      ],
    ]);
    // To satisfy config schema, the size setting must be an integer, not just
    // a numeric value. See https://www.drupal.org/node/2885441.
    $this->assertIsInt($form_display->getComponent($field_name)['settings']['size']);
    $form_display->save();
    $this->assertIsInt($form_display->getComponent($field_name)['settings']['size']);

    // Visit the node add page.
    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $autocomplete_field = $assert_session->waitForElement('css', '[name="' . $field_name . '[0][target_id]"].ui-autocomplete-input');
    $autocomplete_field->setValue('Test');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();

    $results = $page->findAll('css', '.ui-autocomplete li');

    $this->assertCount(2, $results);
    $assert_session->pageTextContains('Test page');
    $assert_session->pageTextContains('Page test');

    // Now switch the autocomplete widget to the 'STARTS_WITH' match operator.
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent($field_name, [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'STARTS_WITH',
        ],
      ])
      ->save();

    $this->drupalGet('node/add/page');

    $this->doAutocomplete($field_name);

    $results = $page->findAll('css', '.ui-autocomplete li');

    $this->assertCount(1, $results);
    $assert_session->pageTextContains('Test page');
    $assert_session->pageTextNotContains('Page test');

    // Change the size of the result set.
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent($field_name, [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_limit' => 1,
        ],
      ])
      ->save();

    $this->drupalGet('node/add/page');

    $this->doAutocomplete($field_name);
    $results = $page->findAll('css', '.ui-autocomplete li');

    $this->assertCount(1, $results);
    $assert_session->pageTextContains('Test page');
    $assert_session->pageTextNotContains('Page test');

    // Change the size of the result set via the UI.
    $this->drupalLogin($this->createUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'create page content',
    ]));
    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $assert_session->pageTextContains('Autocomplete suggestion list size: 1');
    // Click on the widget settings button to open the widget settings form.
    $this->submitForm([], $field_name . "_settings_edit");
    $this->assertSession()->waitForElement('css', sprintf('[name="fields[%s][settings_edit_form][settings][match_limit]"]', $field_name));
    $page->fillField('Number of results', 2);
    $page->pressButton('Save');
    $assert_session->pageTextContains('Your settings have been saved.');
    $assert_session->pageTextContains('Autocomplete suggestion list size: 2');

    $this->drupalGet('node/add/page');

    $this->doAutocomplete($field_name);
    $this->assertCount(2, $page->findAll('css', '.ui-autocomplete li'));
  }

  /**
   * Tests that the autocomplete widget knows about the entity its attached to.
   *
   * Ensures that the entity the autocomplete widget stores the entity it is
   * rendered on, and is available in the autocomplete results' AJAX request.
   */
  public function testEntityReferenceAutocompleteWidgetAttachedEntity(): void {
    $user = $this->drupalCreateUser([
      'administer entity_test content',
    ]);
    $this->drupalLogin($user);

    $field_name = 'field_test';
    $this->createEntityReferenceField('entity_test', 'entity_test', $field_name, $field_name, 'entity_test', 'entity_test_all_except_host', ['target_bundles' => ['entity_test']]);
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $form_display->setComponent($field_name, [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
      ],
    ]);
    $form_display->save();

    $host = EntityTest::create(['name' => 'dark green']);
    $host->save();
    EntityTest::create(['name' => 'dark blue'])->save();

    $this->drupalGet($host->toUrl('edit-form'));

    // Trigger the autocomplete.
    $page = $this->getSession()->getPage();
    $autocomplete_field = $page->findField($field_name . '[0][target_id]');
    $autocomplete_field->setValue('dark');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();

    // Check the autocomplete results.
    $results = $page->findAll('css', '.ui-autocomplete li');
    $this->assertCount(1, $results);
    $this->assertSession()->elementTextNotContains('css', '.ui-autocomplete li', 'dark green');
    $this->assertSession()->elementTextContains('css', '.ui-autocomplete li', 'dark blue');
  }

  /**
   * Executes an autocomplete on a given field and waits for it to finish.
   *
   * @param string $field_name
   *   The field name.
   */
  protected function doAutocomplete(string $field_name): void {
    $autocomplete_field = $this->getSession()->getPage()->findField($field_name . '[0][target_id]');
    $autocomplete_field->setValue('Test');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();
  }

}
