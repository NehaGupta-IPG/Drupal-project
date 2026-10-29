<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\EntityReference;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity reference field configuration when no target bundles exist.
 */
#[Group('entity_reference')]
#[RunTestsInSeparateProcesses]
class EntityReferenceNoBundlesTest extends BrowserTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_ui',
    'block',
    'taxonomy',
    'media',
    'block_content',
    'comment',
    'entity_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests guidance and required validation when no target bundles exist.
   */
  #[DataProvider('missingBundlesProvider')]
  public function testMissingBundles(string $target_type, string $message, ?string $link_path, bool $admin = TRUE): void {
    $account = $this->drupalCreateUser([
      'administer account settings',
      'administer user fields',
      'access administration pages',
      'access taxonomy overview',
    ], NULL, $admin);
    $this->drupalLogin($account);
    if (!$admin) {
      // A readable collection must not replace an inaccessible add form.
      $this->drupalGet('/admin/structure/taxonomy');
      $this->assertSession()->statusCodeEquals(200);
    }
    if ($target_type === 'entity_test') {
      $this->container->get('state')->set('entity_test.bundles', []);
      $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    }

    $entity_type = $this->container->get('entity_type.manager')->getDefinition($target_type);
    $this->assertSame([], $this->container->get('entity_type.bundle.info')->getBundleInfo($target_type));
    $this->fieldUIAddNewField('admin/config/people/accounts', 'no_bundles', 'No bundles', 'entity_reference', [], [], FALSE);
    if ($target_type === 'node') {
      // Return here even if the field form already has its own destination.
      $this->drupalGet($this->getSession()->getCurrentUrl(), [
        'query' => ['destination' => '/admin/config/people/accounts/fields'],
      ]);
    }
    $this->submitForm([
      'field_storage[subform][settings][target_type]' => $target_type,
    ], 'Update settings');
    $assert = $this->assertSession();
    $assert->pageTextContains($message);
    if ($link_path) {
      $assert->linkByHrefExists($link_path);
    }
    else {
      $assert->linkByHrefNotExists('/admin/structure/taxonomy/add');
      $assert->linkByHrefNotExists('/admin/structure/taxonomy');
    }

    // Help must not allow saving a field without any referenceable bundles.
    $this->submitForm([], 'Save');
    $assert->pageTextContains($entity_type->getBundleLabel() . ' field is required.');
    $this->assertNull(FieldConfig::load('user.user.field_no_bundles'));

    if (in_array($target_type, ['taxonomy_term', 'node'], TRUE) && $admin) {
      // Create a bundle using the link and return to finish the field.
      $field_url = $this->getSession()->getCurrentUrl();
      if ($target_type === 'node') {
        $this->clickLink('Add a new content type.');
        $this->submitForm(['name' => 'Article', 'type' => 'article'], 'Save and manage fields');
        $bundle = 'article';
      }
      else {
        $this->clickLink('Add a new vocabulary.');
        $this->submitForm(['name' => 'Tags', 'vid' => 'tags'], 'Save');
        $bundle = 'tags';
      }
      $this->assertSame($field_url, $this->getSession()->getCurrentUrl());
      // Without JavaScript, the add link remains an ordinary round trip.
      $this->submitForm([
        'field_storage[subform][settings][target_type]' => $target_type,
      ], 'Update settings');
      $assert->pageTextNotContains($message);
      $assert->checkboxNotChecked('settings[handler_settings][target_bundles][' . $bundle . ']');
      $this->submitForm([
        'settings[handler_settings][target_bundles][' . $bundle . ']' => $bundle,
      ], 'Save');
      $field = FieldConfig::load('user.user.field_no_bundles');
      $this->assertNotNull($field);
      $this->assertSame([$bundle => $bundle], $field->getSetting('handler_settings')['target_bundles']);
    }
  }

  /**
   * Tests unavailable targets without a way to create selectable options.
   */
  public function testTargetWithoutBundleManagement(): void {
    NodeType::create(['type' => 'previous', 'name' => 'Previous target'])->save();
    $this->container->get('state')->set('entity_test_rev.bundles', []);
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $bundle_label = $this->container->get('entity_type.manager')->getDefinition('entity_test_rev')->getBundleLabel();
    $message = "No $bundle_label is available. Choose a different reference method or type.";
    $assert = $this->assertSession();

    $this->fieldUIAddNewField('admin/config/people/accounts', 'reference_options', 'Reference options', 'entity_reference', [], [], FALSE);
    $assert->fieldExists('settings[handler_settings][target_bundles][previous]');
    $this->submitForm([
      'field_storage[subform][settings][target_type]' => 'entity_test_rev',
    ], 'Update settings');
    // Updating the target replaces the previous bundle selection.
    $assert->fieldNotExists('settings[handler_settings][target_bundles][previous]');
    $assert->fieldExists('settings[handler]');
    $this->submitForm([], 'Save');
    $assert->pageTextContains($message);
    $assert->pageTextNotContains('Content type field is required.');
    $assert->pageTextNotContains('Type field is required.');
    $this->assertNull(FieldStorageConfig::load('user.field_reference_options'));
    $this->assertNull(FieldConfig::load('user.user.field_reference_options'));

    // The user can recover by choosing a different target.
    $this->submitForm([
      'field_storage[subform][settings][target_type]' => 'user',
    ], 'Update settings');
    $assert->pageTextNotContains($message);
    $this->submitForm([], 'Save');
    $this->assertSame('user', FieldStorageConfig::load('user.field_reference_options')->getSetting('target_type'));

    // An existing API-created field has the same Default handler error.
    FieldStorageConfig::create([
      'entity_type' => 'user',
      'field_name' => 'field_existing_target',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'entity_test_rev'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'user',
      'bundle' => 'user',
      'field_name' => 'field_existing_target',
      'label' => 'Existing target',
      'settings' => ['handler_settings' => ['target_bundles' => []]],
    ])->save();
    $this->drupalGet('admin/config/people/accounts/fields/user.user.field_existing_target');
    $this->submitForm([], 'Save settings');
    $assert->pageTextContains($message);
    $assert->pageTextNotContains('Type field is required.');

    // Views can be configured even while the target has no bundles.
    View::create([
      'id' => 'empty_reference',
      'label' => 'Empty reference',
      'base_table' => 'entity_test_rev',
      'base_field' => 'id',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'access' => ['type' => 'none'],
            'fields' => [
              'name' => [
                'id' => 'name',
                'table' => 'entity_test_rev',
                'field' => 'name',
                'plugin_id' => 'field',
                'entity_type' => 'entity_test_rev',
                'entity_field' => 'name',
              ],
            ],
          ],
        ],
        'entity_reference' => [
          'id' => 'entity_reference',
          'display_plugin' => 'entity_reference',
          'display_title' => 'Entity Reference',
          'display_options' => [
            'style' => [
              'type' => 'entity_reference',
              'options' => ['search_fields' => ['name' => 'name']],
            ],
          ],
        ],
      ],
    ])->save();
    $this->submitForm(['settings[handler]' => 'views'], 'Change handler');
    $assert->pageTextNotContains($message);
    $assert->fieldNotExists('settings[handler_settings][target_bundles]');
    $this->submitForm([
      'settings[handler_settings][view][view_and_display]' => 'empty_reference:entity_reference',
    ], 'Save settings');
    $assert->pageTextContains('Saved Existing target configuration.');
    $field = FieldConfig::load('user.user.field_existing_target');
    $this->assertSame('views', $field->getSetting('handler'));
    $this->assertSame('empty_reference', $field->getSetting('handler_settings')['view']['view_name']);
    $this->assertSame([], $this->container->get('entity_type.bundle.info')->getBundleInfo('entity_test_rev'));
  }

  /**
   * Provides target types, management links, and access to bundle management.
   */
  public static function missingBundlesProvider(): array {
    return [
      'vocabularies' => ['taxonomy_term', 'There is no vocabulary yet.', '/admin/structure/taxonomy/add'],
      'media types' => ['media', 'There is no media type yet.', '/admin/structure/media/add'],
      'block types' => ['block_content', 'There is no block type yet.', '/admin/structure/block-content/add'],
      'comment types' => ['comment', 'There is no comment type yet.', '/admin/structure/comment/types/add'],
      'content types' => ['node', 'There is no content type yet.', '/admin/structure/types/add'],
      'collection access without creation access' => ['taxonomy_term', 'There is no vocabulary yet.', NULL, FALSE],
    ];
  }

}
