<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests the upgrade path for converting Views term references to UUIDs.
 *
 * @group Update
 *
 * @see taxonomy_post_update_convert_term_views_filters_to_uuids()
 */
#[IgnoreDeprecations]
class ViewsTermIdToUuidUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/views-term-id-to-uuid.php',
    ];
  }

  /**
   * Tests that views term references are updated as expected.
   */
  public function testViewsTermIdToUuidUpdate(): void {
    $view = View::load('test_term_id_to_uuid_update');
    $data = $view->toArray();
    $this->assertEquals([2], $data['display']['default']['display_options']['filters']['tid']['value']);

    $this->runUpdates();

    $view = View::load('test_term_id_to_uuid_update');
    $data = $view->toArray();
    $this->assertEquals(['4b60f518-07a4-46ab-9800-f4240446738b'], $data['display']['default']['display_options']['filters']['tid']['value']);
  }

}
