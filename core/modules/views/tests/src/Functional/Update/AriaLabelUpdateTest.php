<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the upgrade path for adding aria labels to views field handler.
 *
 * @group Update
 * @group legacy
 *
 * @see views_post_update_add_aria_label()
 */
class AriaLabelUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests that numeric argument plugins are updated properly.
   */
  public function testAriaLabelPostUpdate(): void {
    $view = View::load('files');
    $data = $view->toArray();
    $this->assertArrayHasKey('alter', $data['display']['default']['display_options']['fields']['filename']);
    $this->assertArrayNotHasKey('aria_label', $data['display']['default']['display_options']['fields']['filename']['alter']);

    $this->runUpdates();

    $view = View::load('files');
    $data = $view->toArray();
    $this->assertEquals('', $data['display']['default']['display_options']['fields']['filename']['alter']['aria_label']);

  }

}
