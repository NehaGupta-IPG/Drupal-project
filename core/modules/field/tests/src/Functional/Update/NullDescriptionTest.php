<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\Update;

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for making content types' help and description NULL.
 *
 * @group node
 */
class NullDescriptionTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests the upgrade path for updating empty help and description to NULL.
   */
  public function testRunUpdates(): void {
    $field_config = FieldConfig::load('node.article.body');
    $this->assertInstanceOf(FieldConfig::class, $field_config);

    $this->assertSame('', $field_config->get('description'));
    $this->runUpdates();

    $field_config = FieldConfig::load('node.article.body');
    $this->assertInstanceOf(FieldConfig::class, $field_config);

    $this->assertNull($field_config->get('description'));
    $this->assertSame('', $field_config->getDescription());
  }

}
