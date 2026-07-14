<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\layout_builder\Functional\TranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that inline blocks works with content translation.
 */
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class InlineBlockTranslationTest extends InlineBlockTestBase {

  use LayoutBuilderTestTrait;
  use TranslationTestTrait;
  use JavascriptTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Adds a new language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the node type 'bundle_with_section_field'.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'bundle_with_section_field', TRUE);

    // Add a non-translatable text field to the basic block bundle.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_non_translatable_text',
      'entity_type' => 'block_content',
      'type' => 'string',
      'translatable' => 0,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'basic',
      'label' => 'Non-translatable text',
    ]);
    $field->save();
    $display_repository = \Drupal::service('entity_display.repository');
    // Assign widget settings for the default form mode.
    $display_repository->getFormDisplay('block_content', 'basic')
      ->setComponent('field_non_translatable_text', [
        'type' => 'string_textfield',
      ])
      ->save();
    // Assign display settings for default view mode.
    $display_repository->getViewDisplay('block_content', 'basic')
      ->setComponent('field_non_translatable_text', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->save();
  }

  /**
   * Tests that inline blocks works with content translation.
   */
  public function testInlineBlockContentTranslation(): void {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'translate bundle_with_section_field node',
      'create content translations',
      'create and edit custom blocks',
    ]));

    // Allow layout overrides.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(['layout[enabled]' => TRUE],
      'Save'
    );
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->submitForm(['layout[allow_custom]' => TRUE],
      'Save'
    );

    // Add a new inline block to the original node.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block en label', 'Block en body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1/layout');
    $this->updateInlineBlockNonTranslatableField('', 'Block non-translatable text');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Block en label');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextContains('Block non-translatable text');
    $block_id = $this->getLatestBlockEntityId();

    // Create a translation.
    $add_translation_url = Url::fromRoute("entity.node.content_translation_add", [
      'node' => 1,
      'source' => 'en',
      'target' => 'it',
    ]);
    $this->drupalGet($add_translation_url);
    $this->submitForm([
      'title[0][value]' => 'The translated node title',
      'body[0][value]' => 'The translated node body',
    ], 'Save');

    // Update the translate node's inline block.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();

    $this->updateBlockTranslation(
      static::INLINE_BLOCK_LOCATOR,
      'Block en label',
      'Block it label',
      '',
      ['[name="settings[block_form][body][0][value]"]']
    );

    $this->assertSaveLayout();
    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created.');
    $this->blockStorage->resetCache([$block_id]);
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $this->blockStorage->load($block_id);
    $this->assertFalse($block->hasTranslation('it'), 'A block translation was not created when only the label was translatable.');

    // Enable translation for block_content type 'bundle_with_section_field'.
    /** @var \Drupal\content_translation\ContentTranslationManager $content_translation_manager */
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('block_content', 'basic', TRUE);

    // Update the translate node's inline block.
    $this->drupalGet('it/node/1/layout');
    $this->assertNonTranslationActionsRemoved();
    $this->updateTranslatedBlock('Block it label', 'Block en body', 'Block non-translatable text', 'Block updated it label', 'Block it body', 'Block non-translatable text updated from translation language');

    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created.');
    $this->blockStorage->resetCache([$block_id]);
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $this->blockStorage->load($block_id);
    $this->assertFalse($block->hasTranslation('it'), 'A block translation was not created before the layout was saved.');

    $this->assertSaveLayout();
    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created');
    $this->blockStorage->resetCache([$block_id]);
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = $this->blockStorage->load($block_id);
    $this->assertTrue($block->hasTranslation('it'), 'A block translation was created when the layout was saved.');
    $this->assertEquals('Block non-translatable text updated from translation language', $block->get('field_non_translatable_text')->getString(), 'The non-translatable field was updated when the layout was saved.');
    $block_translation = $block->getTranslation('it');
    $this->assertEquals('Block it body', $block_translation->get('body')->get(0)->getValue()['value'], 'The translated block body field was created correctly.');
    $this->assertEquals('Block non-translatable text updated from translation language', $block_translation->get('field_non_translatable_text')->getString(), 'The non-translatable field was updated when the layout was saved.');

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block it body');
    $assert_session->pageTextContains('Block non-translatable text updated from translation language');
    $assert_session->pageTextContains('Block updated it label');
    $assert_session->pageTextNotContains('Block en body');
    $assert_session->pageTextNotContains('Block en label');

    // Confirm that the default translation was not affected.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block it body');
    $assert_session->pageTextNotContains('Block updated it label');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextContains('Block en label');

    // Confirm that the non-translatable field has the correct value on the
    // translated block.
    $assert_session->pageTextContains('Block non-translatable text updated from translation language');

    // Update the translation inline block again.
    $this->drupalGet('it/node/1/layout');
    $this->updateTranslatedBlock('Block updated it label', 'Block it body', 'Block non-translatable text updated from translation language', 'Block newer updated it label', 'Block updated it body', 'Block non-translatable text updated from translation language');
    $this->assertSaveLayout();

    $this->assertEquals($block_id, $this->getLatestBlockEntityId(), 'A new block was not created.');
    $this->blockStorage->resetCache([$block_id]);
    $block = $this->blockStorage->load($block_id);
    $block_translation = $block->getTranslation('it');
    $this->assertEquals('Block updated it body', $block_translation->get('body')->get(0)->getValue()['value'], 'The translated block body field was created correctly.');

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block updated it body');
    $assert_session->pageTextContains('Block newer updated it label');
    $assert_session->pageTextNotContains('Block en body');
    $assert_session->pageTextNotContains('Block en label');

    // Confirm that the default translation was not effected.
    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block updated it body');
    $assert_session->pageTextNotContains('Block newer updated it label');
    $assert_session->pageTextContains('Block en body');
    $assert_session->pageTextContains('Block en label');

    // Update the default translation's version of the block.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('Block en body', 'Block updated en body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1/layout');
    $this->updateInlineBlockNonTranslatableField('Block non-translatable text updated from translation language', 'Block non-translatable text updated from source language');
    $this->assertSaveLayout();

    $assert_session->addressEquals('node/1');
    $assert_session->pageTextNotContains('Block updated it body');
    $assert_session->pageTextNotContains('Block newer updated it label');
    $assert_session->pageTextContains('Block updated en body');
    $assert_session->pageTextContains('Block en label');
    $assert_session->pageTextContains('Block non-translatable text updated from source language');

    // Confirm that the translation was not effected.
    $this->drupalGet('it/node/1');
    $assert_session->pageTextContains('Block updated it body');
    $assert_session->pageTextContains('Block newer updated it label');
    $assert_session->pageTextNotContains('Block updated en body');
    $assert_session->pageTextNotContains('Block en label');

    // Confirm that the non-translatable field has the correct value on the
    // translated block.
    $assert_session->pageTextContains('Block non-translatable text updated from source language');

    // Update the translation block after updating default translation block.
    $this->drupalGet('it/node/1/layout');
    $this->updateTranslatedBlock('Block newer updated it label', 'Block updated it body', 'Block non-translatable text updated from source language', 'Block even newer updated it label', 'Block newer updated it body', 'Block non-translatable text updated from translation language newer');
    $this->assertSaveLayout();

    $assert_session->addressEquals('it/node/1');
    $assert_session->pageTextContains('Block newer updated it body');
    $assert_session->pageTextContains('Block non-translatable text updated from translation language newer');
    $assert_session->pageTextContains('Block even newer updated it label');
    $assert_session->pageTextNotContains('Block updated en body');
    $assert_session->pageTextNotContains('Block en label');

    $this->drupalGet('node/1');
    $assert_session->pageTextNotContains('Block newer updated it body');
    $assert_session->pageTextContains('Block non-translatable text updated from translation language newer');
    $assert_session->pageTextNotContains('Block even newer updated it label');
    $assert_session->pageTextContains('Block updated en body');
    $assert_session->pageTextContains('Block en label');
  }

  /**
   * Update a translation inline block.
   *
   * @param string $existing_label
   *   The inline block's existing label.
   * @param string $existing_body
   *   The inline block's existing body field value.
   * @param string $existing_non_translatable_text
   *   The inline block's existing field_non_translatable_text value.
   * @param string $new_label
   *   The new label.
   * @param string $new_body
   *   The new body field value.
   * @param string $new_non_translatable_text
   *   The new non translatable text value.
   */
  protected function updateTranslatedBlock($existing_label, $existing_body, $existing_non_translatable_text, $new_label, $new_body, $new_non_translatable_text): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink(static::INLINE_BLOCK_LOCATOR, 'Translate block');
    $textarea = $assert_session->waitForElement('css', '[name="body[0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertEquals($existing_body, $textarea->getValue());
    $textarea->setValue($new_body);
    $non_translatable_input = $assert_session->elementExists('css', '[name="field_non_translatable_text[0][value]"]');
    $this->assertEquals($existing_non_translatable_text, $non_translatable_input->getValue());
    $non_translatable_input->setValue($new_non_translatable_text);

    $label_input = $assert_session->elementExists('css', '#drupal-off-canvas [name="info[0][value]"]');
    $this->assertNotEmpty($label_input);
    $this->assertEquals($existing_label, $label_input->getValue());
    $label_input->setValue($new_label);
    $page->pressButton('Save');

    $this->assertNoElementAfterWait('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextContains($new_label);
    $assert_session->pageTextContains($new_body);
    $assert_session->pageTextNotContains($existing_label);
    $assert_session->pageTextNotContains($existing_body);
  }

  /**
   * Update the value of the field_non_translatable_text source field.
   *
   * @param string $old_value
   *   The field's current value.
   * @param string $new_value
   *   The field's new value.
   */
  protected function updateInlineBlockNonTranslatableField($old_value, $new_value): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink(static::INLINE_BLOCK_LOCATOR, 'Configure');
    $input = $assert_session->waitForElementVisible('css', '[name="settings[block_form][field_non_translatable_text][0][value]"]');
    $this->assertSame($old_value, $input->getValue());
    $input->setValue($new_value);
    $page->pressButton('Update');
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertDialogClosedAndTextVisible($new_value);
  }

}
