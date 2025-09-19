<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the update path for the operator labels feature.
 *
 * @see views_post_update_set_operator_label_defaults()
 *
 * @group Update
 * @group legacy
 */
class OperatorLabelsDefaultsTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/exposed-operators-labels.php',
    ];
  }

  /**
   * Tests the default settings for exposed filter operators labels are present.
   */
  public function testViewsPostUpdateOperatorLabelsDefaultValues() {
    // Load and initialize our test view.
    $view = View::load('test_exposed_operator_label_update');
    $data = $view->toArray();

    // Check that the exposed filters have no operator label default value.
    $title_filter = $data['display']['default']['display_options']['filters']['title']['expose'];
    $this->assertArrayNotHasKey('operator_label', $title_filter);

    $created_filter = $data['display']['default']['display_options']['filters']['created']['expose'];
    $this->assertArrayNotHasKey('operator_label', $created_filter);

    $this->runUpdates();

    // Load and initialize our test view after running the updates.
    $view = View::load('test_exposed_operator_label_update');
    $data = $view->toArray();

    // Check that the exposed filters have the operator label default value.
    $title_filter = $data['display']['default']['display_options']['filters']['title']['expose'];
    $this->assertArrayHasKey('operator_label', $title_filter);
    $this->assertEquals('Operator', $title_filter['operator_label']);

    $created_filter = $data['display']['default']['display_options']['filters']['created']['expose'];
    $this->assertArrayHasKey('operator_label', $created_filter);
    $this->assertEquals('Operator', $created_filter['operator_label']);
  }

}
