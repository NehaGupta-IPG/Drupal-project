<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update path for the entity view mode description value from '' to NULL.
 *
 * @group contact
 */
class EntityViewModeUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../../../system/tests/fixtures/update/remove-description-from-node-full-view-mode.php',
    ];
  }

  /**
   * Tests update path for the entity view mode description value from '' to NULL.
   */
  public function testRunUpdates(): void {
    $view_mode_type = EntityViewMode::load('node.full');
    $this->assertInstanceOf(EntityViewMode::class, $view_mode_type);
    $this->assertSame("\n", $view_mode_type->get('description'));
    $this->runUpdates();

    $view_mode_type = EntityViewMode::load('node.full');
    $this->assertInstanceOf(EntityViewMode::class, $view_mode_type);

    $this->assertNull($view_mode_type->get('description'));
    $this->assertSame('', $view_mode_type->getDescription());
  }

}
