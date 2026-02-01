<?php

declare(strict_types=1);

namespace Drupal\Tests\rest\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the addition of the pager option to serializer style displays.
 *
 * @see rest_post_update_add_serializer_pager()
 */
#[Group('Update')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class SerializerPagerUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/test_serializer_pager.php',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installModulesFromClassProperty($this->container);
  }

  /**
   * Tests the upgrade path adding pager option to serializer displays.
   */
  public function testSerializerPagerUpdate(): void {
    $view = View::load('test_serializer_pager_update');
    $data = $view->toArray();

    // Verify that serializer displays do not have the pager option before update.
    foreach ($data['display'] as $display) {
      if (isset($display['display_options']['style']) && $display['display_options']['style']['type'] === 'serializer') {
        $this->assertArrayNotHasKey('pager', $display['display_options']['style']['options']);
      }
    }

    $this->runUpdates();

    $view = View::load('test_serializer_pager_update');
    $data = $view->toArray();

    // Verify that serializer displays now have the pager option set to FALSE.
    $serializer_count = 0;
    foreach ($data['display'] as $display) {
      if (isset($display['display_options']['style']) && $display['display_options']['style']['type'] === 'serializer') {
        $this->assertArrayHasKey('pager', $display['display_options']['style']['options']);
        $this->assertFalse($display['display_options']['style']['options']['pager']);
        $serializer_count++;
      }
    }

    $this->assertEquals(2, $serializer_count);
  }

}
