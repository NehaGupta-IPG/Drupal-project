<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests nested rows on entity display forms.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class DisplayGroupTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field_ui',
    'field_ui_test',
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

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_source',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Source field',
    ])->save();

    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test');
    $display->setComponent('field_test_text', [
      'type' => 'text_default',
      'region' => 'content',
      'weight' => 0,
    ]);
    $display->setComponent('field_source', [
      'type' => 'string',
      'region' => 'content',
      'weight' => 100,
    ]);
    $display->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer entity_test display',
      'view the administration theme',
    ]));
  }

  /**
   * Tests that nesting a field does not change its region to its parent ID.
   */
  public function testNestedFieldRegion(): void {
    $display_path = 'entity_test/structure/entity_test/display/default';
    $this->drupalGet($display_path);
    $assert_session = $this->assertSession();

    $assert_session->waitForElement('css', '#field-test-text .tabledrag-handle');
    $field_row = $assert_session->elementExists('css', '#field-test-text');
    $handle = $field_row->find('css', '.tabledrag-handle');
    $handle->focus();
    $handle->keyDown(39);
    $handle->keyUp(39);
    $handle->blur();

    $assert_session->waitForElementVisible('css', '#field-test-text abbr.tabledrag-changed');
    $assert_session->fieldValueEquals('fields[field_test_text][parent]', 'indent');
    $assert_session->fieldValueEquals('fields[field_test_text][region]', 'content');

    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->waitForText('Your settings have been saved.');

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display');
    $display_id = 'entity_test.entity_test.default';
    $storage->resetCache([$display_id]);
    $display = $storage->load($display_id);
    $this->assertSame('content', $display->getComponent('field_test_text')['region']);

    $this->drupalGet($display_path);
    $assert_session->fieldValueEquals('fields[field_test_text][region]', 'content');
  }

  /**
   * Tests that outdenting a hidden field does not change its region.
   */
  public function testOutdentHiddenFieldRegion(): void {
    $display = $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test');
    $display->removeComponent('field_test_text');
    $display->save();
    $state = $this->container->get('state');
    $state->set('field_ui_test.group_parent', 'indent');
    $state->set('field_ui_test.group_region', 'hidden');

    $display_path = 'entity_test/structure/entity_test/display/default';
    $this->drupalGet($display_path);
    $assert_session = $this->assertSession();
    $this->assertSame('hidden', $this->getSession()->evaluateScript('return drupalSettings.fieldUIRowsData.indent.region;'));
    $assert_session->fieldValueEquals('fields[field_source][parent]', '');
    $assert_session->fieldValueEquals('fields[field_source][region]', 'content');
    $assert_session->fieldValueEquals('fields[field_test_text][parent]', 'indent');
    $assert_session->fieldValueEquals('fields[field_test_text][region]', 'hidden');

    $field_row = $assert_session->elementExists('css', '#field-test-text');
    $handle = $field_row->find('css', '.tabledrag-handle');
    $handle->focus();
    $handle->keyDown(37);
    $handle->keyUp(37);
    $handle->blur();

    $assert_session->waitForElementVisible('css', '#field-test-text abbr.tabledrag-changed');
    $assert_session->fieldValueEquals('fields[field_test_text][parent]', '');
    $assert_session->fieldValueEquals('fields[field_test_text][region]', 'hidden');
    $assert_session->fieldValueEquals('fields[field_source][parent]', '');
    $assert_session->fieldValueEquals('fields[field_source][region]', 'content');

    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->waitForText('Your settings have been saved.');

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display');
    $display_id = 'entity_test.entity_test.default';
    $storage->resetCache([$display_id]);
    $this->assertNull($storage->load($display_id)->getComponent('field_test_text'));

    $this->drupalGet($display_path);
    $assert_session->fieldValueEquals('fields[field_test_text][region]', 'hidden');
  }

}
