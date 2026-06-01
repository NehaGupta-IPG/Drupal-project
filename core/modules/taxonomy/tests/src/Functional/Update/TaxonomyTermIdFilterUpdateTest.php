<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Functional\Update;

use Drupal\Core\Serialization\Yaml;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update of term ID filter handlers to allow multiple vocabularies.
 *
 * @group taxonomy
 * @group legacy
 *
 * @see taxonomy_post_update_multiple_vocabularies_filter()
 */
class TaxonomyTermIdFilterUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function getConfigSchemaExclusions() {
    return ['views.view.test_filter_taxonomy_index_tid'];
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      dirname(__DIR__, 5) . '/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests that filter handlers are updated properly.
   */
  public function testPostUpdateTaxonomyIndexFilterMultipleVocabularies(): void {
    $config_factory = \Drupal::configFactory();
    // Prepare the test view with the old schema.
    $view_as_array = Yaml::decode(file_get_contents(dirname(__DIR__, 3) . '/modules/taxonomy_test_views/test_views/views.view.test_filter_taxonomy_index_tid.yml'));
    $view_as_array['uuid'] = \Drupal::service('uuid')->generate();
    unset($view_as_array['display']['default']['display_options']['filters']['tid']['vids']);
    $view_as_array['display']['default']['display_options']['filters']['tid']['vid'] = 'tags';
    $config_factory->getEditable('views.view.test_filter_taxonomy_index_tid')
      ->setData($view_as_array)
      ->save();

    $path = 'display.default.display_options.filters.tid';
    $view_as_config = $config_factory->get('views.view.test_filter_taxonomy_index_tid');

    // Check that, before running updates, only the legacy 'vid' key exists.
    $this->assertSame('tags', $view_as_config->get("{$path}.vid"));
    $this->assertArrayNotHasKey('vids', $view_as_config->get($path));

    $this->runUpdates();

    $view_as_config = $config_factory->get('views.view.test_filter_taxonomy_index_tid');

    // Check that, after running updates, only the new 'vids' key exists.
    $this->assertSame(['tags'], $view_as_config->get("{$path}.vids"));
    $this->assertArrayNotHasKey('vid', $view_as_config->get($path));
  }

}
