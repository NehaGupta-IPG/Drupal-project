<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the fix for the Content Moderation 'status' field validation conflict.
 *
 * Content Moderation requires the 'status' field to diverge across translations.
 * However, if the 'status' field is non-translatable, Drupal validation blocks this save.
 *
 * @see https://www.drupal.org/project/drupal/issues/3025039
 */
#[Group('content_translation')]
#[RunTestsInSeparateProcesses]
class ContentModerationNonTranslatableStatusTest extends KernelTestBase {

  use UserCreationTrait;
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
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['content_moderation', 'workflows']);
    $this->installConfig(['language', 'content_translation', 'node']);
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Set up translatable content type with un-translatable status field, untranslatable fields should be hidden.
    $node_type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $node_type->save();
    $config = ContentLanguageSettings::loadByEntityTypeBundle('node', 'page');
    $config->setLanguageAlterable(TRUE);
    $config->setThirdPartySetting('content_translation', 'enabled', TRUE);
    $config->setThirdPartySetting('content_translation', 'bundle_settings', [
      'untranslatable_fields_hide' => '1',
    ]);
    $config->save();

    $status_override = \Drupal::entityTypeManager()
      ->getStorage('base_field_override')
      ->create([
        'field_name' => 'status',
        'entity_type' => 'node',
        'bundle' => 'page',
        'label' => 'Published',
        'description' => '',
        'required' => FALSE,
        'translatable' => FALSE,
        'default_value' => [['value' => 1]],
        'settings' => [
          'on_label' => 'On',
          'off_label' => 'Off',
        ],
      ]);
    $status_override->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    $admin_user = $this->createUser([
      'administer nodes',
      'use editorial transition publish',
      'use editorial transition create_new_draft',
    ]);

    $this->container->get('current_user')->setAccount($admin_user);
  }

  /**
   * Verifies that moderated bundles can have different status values per language.
   */
  public function testStatusExceptionTriggered(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Original',
      'status' => 0,
      'moderation_state' => 'draft',
      'langcode' => 'en',
    ]);
    $node->save();

    $is_translatable = $node->getFieldDefinition('status')->isTranslatable();
    $this->assertTrue($is_translatable, 'The status field should be translatable.');

    $translation = $node->addTranslation('it', [
      'title' => 'Italian Translation',
      'moderation_state' => 'published',
    ]);

    $this->assertCount(0, $translation->validate());
  }

}
