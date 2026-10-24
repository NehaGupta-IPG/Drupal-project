<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update path for the comment subject field size set from 64 to 255.
 *
 * @group search
 */
class CommentSubjectFieldUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['comment'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests update path for the comment subject field size set from 64 to 255.
   */
  public function testRunUpdates(): void {
    $connection = $this->container->get('database');

    // Check the previous field size.
    $fields = [];
    foreach ($connection->query('DESC {comment_field_data}')->fetchAll() as $field) {
      $fields[$field->Field] = $field;
    }
    $this->assertEquals('varchar(64)', $fields['subject']->Type);

    $this->runUpdates();

    // Check that the subject field has been updated.
    $fields = [];
    foreach ($connection->query('DESC {comment_field_data}')->fetchAll() as $field) {
      $fields[$field->Field] = $field;
    }
    $this->assertEquals('varchar(255)', $fields['subject']->Type);
  }

}
