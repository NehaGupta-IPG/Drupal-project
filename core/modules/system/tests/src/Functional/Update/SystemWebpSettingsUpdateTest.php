<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests system.image.gd:webp_quality and system.image.gd::webp_lossless values.
 *
 * @group system
 * @covers \system_post_update_webp_quality_default_value
 */
class SystemWebpSettingsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles[] = $this->root . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz';

  }

  /**
   * Tests updating Image GD webp configurations.
   */
  public function testUpdate(): void {
    $this->runUpdates();

    $config = $this->config('system.image.gd');
    $this->assertSame(75, $config->get('webp_quality'));
    $this->assertFalse($config->get('webp_lossless'));
  }

}
