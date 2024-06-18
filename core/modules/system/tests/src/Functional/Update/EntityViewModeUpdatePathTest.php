<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update path for the entity view mode description value from '' to NULL.
 *
 * @group system
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
    $view_mode = EntityViewMode::load('node.full');
    $this->assertInstanceOf(EntityViewMode::class, $view_mode);
    $this->assertSame("\n", $view_mode->get('description'));
    $this->assertSame("\n", $view_mode->getDescription());
    $this->runUpdates();

    $view_mode = EntityViewMode::load('node.full');
    $this->assertInstanceOf(EntityViewMode::class, $view_mode);

    $this->assertNull($view_mode->get('description'));
    // Assert backward compatibility of EntityViewMode::getDescription().
    $this->assertSame('', $view_mode->getDescription());
  }

}
