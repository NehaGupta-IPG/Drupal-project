<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;

/**
 * Tests the removal of the default_argument_skip_url setting.
 *
 * @see views_post_update_remove_default_argument_skip_url()
 *
 * @group Update
 * @group legacy
 */
class ViewsRemoveDefaultArgumentSkipUrlTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'taxonomy', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/remove_default_argument_skip_url.php',
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
   * Tests the upgrade path removing default_argument_skip_url.
   */
  public function testViewsPostUpdateFixRevisionId(): void {
    $this->expectDeprecation('The default_argument_skip_url attribute for view "remove_default_argument_skip_url" is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Profile, module and theme provided configuration should be updated. See https://www.drupal.org/node/3382316');
    $view = View::load('remove_default_argument_skip_url');
    $data = $view->toArray();
    $this->assertArrayHasKey('default_argument_skip_url', $data['display']['default']['display_options']['arguments']['tid']);

    $this->runUpdates();

    $view = View::load('remove_default_argument_skip_url');
    $data = $view->toArray();

    $this->assertArrayNotHasKey('default_argument_skip_url', $data['display']['default']['display_options']['arguments']['tid']);
  }

}
