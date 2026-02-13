<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the fix for the Content Moderation 'status' field validation.
 *
 * Ensure EntityUntranslatableFieldsConstraintValidator does not prevent
 * moderated node translations to be saved.
 *
 * @see https://www.drupal.org/project/drupal/issues/3025039
 */
#[Group('content_translation')]
#[RunTestsInSeparateProcesses]
class ContentModerationNonTranslatableStatusTest extends BrowserTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'language',
    'content_translation',
    'workflows',
    'content_moderation',
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

    ConfigurableLanguage::createFromLangcode('it')->save();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $config = ContentLanguageSettings::loadByEntityTypeBundle('node', 'page');
    $config->setLanguageAlterable(TRUE);
    $config->setThirdPartySetting('content_translation', 'enabled', TRUE);
    $config->setThirdPartySetting('content_translation', 'bundle_settings', [
      'untranslatable_fields_hide' => '1',
    ]);
    $config->save();

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service(EntityFieldManagerInterface::class);
    $fields = $entityFieldManager->getBaseFieldDefinitions('node');
    \assert($fields['status'] instanceof BaseFieldDefinition);
    $status_override = BaseFieldOverride::createFromBaseFieldDefinition($fields['status'], 'page');
    $status_override->setTranslatable(FALSE);
    $status_override->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    $admin_user = $this->drupalCreateUser(admin: TRUE);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that the hidden status field does not trigger the constraint.
   */
  public function testModerationStatusField(): void {
    // Create a draft node.
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Original',
      'moderation_state' => 'draft',
      'langcode' => 'en',
    ]);

    // Add an Italian translation and publish it via the UI.
    $this->drupalGet("node/{$node->id()}/translations/add/en/it");
    $this->submitForm([
      'title[0][value]' => 'Italian Translation',
      'moderation_state[0][state]' => 'published',
    ], 'Save');

    // The save should succeed with no validation errors.
    $this->assertSession()->statusMessageNotContains('Non-translatable fields can only be changed when updating the original language.');
    $this->assertSession()->statusMessageContains('Basic page Italian Translation has been updated.');

    // Validate node status and moderation state.
    $node = Node::load($node->id());
    $this->assertFalse($node->getUntranslated()->isPublished());
    $this->assertEquals('draft', $node->getUntranslated()->get('moderation_state')->value);
    $this->assertTrue($node->getTranslation('it')->isPublished());
    $this->assertEquals('published', $node->getTranslation('it')->get('moderation_state')->value);
  }

}
