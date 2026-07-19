<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests moving sections via the form.
 *
 * @group layout_builder
 */
class MoveSectionsFormTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'block',
    'node',
    'contextual',
    'field_ui',
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

    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'access contextual links',
    ]));
  }

  /**
   * Tests moving sections.
   */
  public function testMoveSections() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Enable layout builder.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $page->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');

    $expected_section_order = [
      '.layout--onecol',
    ];
    $this->assertSectionsOrder($expected_section_order);

    // Add a top section using the Two column layout.
    $page->clickLink('Add section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add section"]'));
    $page->pressButton('Add section');

    $expected_section_order = [
      '.layout--twocol-section',
      '.layout--onecol',
    ];
    $this->assertSectionsOrder($expected_section_order);

    // Ensure the request has completed before the test starts.
    $assert_session->assertWaitOnAjaxRequest();

    // Reorder sections by dragging with keyboard.
    $this->openSectionMoveForm(['Section 1', 'Section 2']);
    $this->moveSectionWithKeyboard('up', 'Section 2', ['Section 2 *', 'Section 1']);
    $page->pressButton('Reorder');
    $expected_section_order = [
      '.layout--onecol',
      '.layout--twocol-section',
    ];
    $this->assertSectionsOrder($expected_section_order);
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    $this->assertSectionsOrder($expected_section_order);

    // Reorder sections by setting delta values.
    $this->openSectionMoveForm(['Section 1', 'Section 2']);
    $page->pressButton('Show row weights');
    $page->selectFieldOption('Delta for Section 1 section', '1');
    $page->selectFieldOption('Delta for Section 2 section', '0');
    $page->pressButton('Hide row weights');
    $page->pressButton('Reorder');
    $expected_section_order = [
      '.layout--twocol-section',
      '.layout--onecol',
    ];
    $this->assertSectionsOrder($expected_section_order);
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    $this->assertSectionsOrder($expected_section_order);

    // Drag section with keyboard and set delta values to be equal.
    // When delta values are equal row order is respected.
    $this->openSectionMoveForm(['Section 1', 'Section 2']);
    $this->moveSectionWithKeyboard('up', 'Section 2', ['Section 2 *', 'Section 1']);
    $page->pressButton('Show row weights');
    $page->selectFieldOption('Delta for Section 1 section', '0');
    $page->pressButton('Reorder');
    $expected_section_order = [
      '.layout--onecol',
      '.layout--twocol-section',
    ];
    $this->assertSectionsOrder($expected_section_order);
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    $this->assertSectionsOrder($expected_section_order);
  }

  /**
   * Asserts the correct section labels appear in the draggable tables.
   *
   * @param string[] $expected_section_labels
   *   The expected section labels.
   */
  protected function assertSectionTable(array $expected_section_labels) {
    $page = $this->getSession()->getPage();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $section_tds = $page->findAll('css', '.layout-builder-sections-table__section-label');
    $this->assertCount(count($section_tds), $expected_section_labels);
    /** @var \Behat\Mink\Element\NodeElement $section_td */
    foreach ($section_tds as $section_td) {
      $this->assertSame(array_shift($expected_section_labels), trim($section_td->getText()));
    }
  }

  /**
   * Moves a section in the draggable table.
   *
   * @param string $direction
   *   The direction to move the section in the table.
   * @param string $section_label
   *   The section label.
   * @param array $updated_sections
   *   The updated sections order.
   */
  protected function moveSectionWithKeyboard($direction, $section_label, array $updated_sections) {
    $keys = [
      'up' => 38,
      'down' => 40,
    ];
    $key = $keys[$direction];
    $handle = $this->findRowHandle($section_label);

    $handle->keyDown($key);
    $handle->keyUp($key);

    $handle->blur();
    $this->assertSectionTable($updated_sections);
  }

  /**
   * Finds the row handle for a section in the draggable table.
   *
   * @param string $section_label
   *   The section label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row handle element.
   */
  protected function findRowHandle($section_label) {
    $assert_session = $this->assertSession();
    return $assert_session->elementExists('css', "[data-drupal-selector=\"edit-sections\"] td:contains(\"$section_label\") a.tabledrag-handle");
  }

  /**
   * Asserts that sections are in the correct order for the layout.
   *
   * @param array $expected_section_selectors
   *   The section selectors.
   */
  protected function assertSectionsOrder(array $expected_section_selectors) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');

    // Get all sections currently in the layout.
    $sections = $page->findAll('css', "[data-layout-delta]");
    $this->assertCount(count($expected_section_selectors), $sections);

    /** @var \Behat\Mink\Element\NodeElement $section */
    foreach ($sections as $section) {
      $section_selector = array_shift($expected_section_selectors);
      $assert_session->elementsCount('css', "$section_selector", 1);
      $expected_section = $page->find('css', "$section_selector");
      $this->assertSame($expected_section->getAttribute('data-layout-delta'), $section->getAttribute('data-layout-delta'));
    }
  }

  /**
   * Open move sections form.
   *
   * @param array $initial_sections
   *   The initial sections that should be shown in the draggable table.
   */
  protected function openSectionMoveForm(array $initial_sections) {
    $assert_session = $this->assertSession();

    $this->clickLink('Reorder sections');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'button.tabledrag-toggle-weight'));
    $this->assertSectionTable($initial_sections);
  }

}
