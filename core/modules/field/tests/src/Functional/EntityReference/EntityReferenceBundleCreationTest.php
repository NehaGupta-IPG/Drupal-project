<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\EntityReference;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests returning to reference field settings after creating a bundle.
 */
#[Group('entity_reference')]
#[RunTestsInSeparateProcesses]
class EntityReferenceBundleCreationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'block',
    'taxonomy',
    'entity_reference_bundle_return_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests returning from bundle creation after opening settings in a modal.
   */
  public function testAjaxBundleCreationDestination(): void {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    FieldStorageConfig::create([
      'field_name' => 'field_ajax_bundles',
      'entity_type' => 'user',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_ajax_bundles',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Ajax references',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => []],
      ],
    ])->save();
    $field_path = Url::fromRoute('entity.field_config.user_field_edit_form', [
      'field_config' => 'user.user.field_ajax_bundles',
    ])->toString();
    $return_path = Url::fromRoute('entity.user.field_ui_fields')->toString();
    $add_path = Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString();
    $commands = json_decode($this->drupalGet($field_path, [
      'query' => [
        'destination' => $return_path,
        '_wrapper_format' => 'drupal_modal',
        '_drupal_ajax' => 1,
        'js' => TRUE,
        'ajax_page_state' => ['theme' => 'stark', 'theme_token' => '', 'libraries' => ''],
      ],
    ], ['X-Requested-With' => 'XMLHttpRequest']), TRUE, 512, JSON_THROW_ON_ERROR);
    $href = NULL;
    foreach ($commands as $command) {
      if (isset($command['data']) && is_string($command['data'])) {
        $link = (new Crawler($command['data']))->filter('a[href^="' . $add_path . '?"]');
        if ($link->count()) {
          $href = $link->attr('href');
          break;
        }
      }
    }
    $this->assertNotNull($href);
    parse_str(parse_url($href, PHP_URL_QUERY), $query);
    $this->assertSame($field_path, parse_url($query['destination'], PHP_URL_PATH));
    parse_str(parse_url($query['destination'], PHP_URL_QUERY), $destination_query);
    $this->assertSame(['destination' => $return_path], $destination_query);

    $this->drupalGet($this->getAbsoluteUrl($href));
    $this->submitForm(['name' => 'Ajax tags', 'vid' => 'ajax_tags'], 'Save');
    $this->assertSame($field_path, parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
    $this->assertSession()->pageTextNotContains('There is no vocabulary yet.');
    $this->assertSession()->checkboxNotChecked('settings[handler_settings][target_bundles][ajax_tags]');
    $this->submitForm([
      'settings[handler_settings][target_bundles][ajax_tags]' => 'ajax_tags',
    ], 'Save settings');
    $this->assertSame(['ajax_tags' => 'ajax_tags'], FieldConfig::load('user.user.field_ajax_bundles')->getSetting('handler_settings')['target_bundles']);
  }

  /**
   * Tests that ordinary and collection-only paths do not select new bundles.
   */
  public function testBundleCreationWithoutDirectAddLink(): void {
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    FieldStorageConfig::create([
      'field_name' => 'field_collection',
      'entity_type' => 'user',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_collection',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Collection references',
      'settings' => ['handler_settings' => ['target_bundles' => []]],
    ])->save();
    $field_path = Url::fromRoute('entity.field_config.user_field_edit_form', [
      'field_config' => 'user.user.field_collection',
    ])->toString();
    $add_path = Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString();

    // Even with a return destination, an ordinary add form must not preselect.
    $this->drupalGet('/admin/structure/taxonomy/add', [
      'query' => ['destination' => $field_path],
    ]);
    $this->submitForm(['name' => 'Ordinary tags', 'vid' => 'ordinary'], 'Save');
    $this->assertSession()->checkboxNotChecked('settings[handler_settings][target_bundles][ordinary]');
    Vocabulary::load('ordinary')->delete();

    // Keep a working add route but expose only the collection link template.
    $this->container->get('state')->set('entity_reference_bundle_return_test.collection_only', TRUE);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('router.builder')->rebuild();
    $this->drupalGet($field_path);
    $this->assertSession()->linkByHrefNotExists($add_path);
    $this->clickLink('Manage vocabularies');
    $this->assertSession()->addressEquals('/admin/structure/taxonomy');
    $this->clickLink('Add vocabulary');
    $this->submitForm(['name' => 'Collection tags', 'vid' => 'collection'], 'Save');
    $this->assertSession()->checkboxNotChecked('settings[handler_settings][target_bundles][collection]');
    $this->submitForm([], 'Save settings');
    $this->assertSession()->pageTextContains('Vocabulary field is required.');
    $this->assertSame([], FieldConfig::load('user.user.field_collection')->getSetting('handler_settings')['target_bundles']);
  }

}
