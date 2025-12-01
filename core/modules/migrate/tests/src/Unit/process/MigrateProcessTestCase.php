<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit\process;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Base class for the Migrate module migrate process unit tests.
 */
abstract class MigrateProcessTestCase extends MigrateTestCase {

  /**
   * The migration process plugin.
   *
   * @var \Drupal\migrate\Plugin\MigrateProcessInterface
   */
  protected $plugin;

  /**
   * A mock of a process row.
   *
   * @var \Drupal\migrate\Row|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $row;

  /**
   * The migration executable or a mock.
   *
   * @var \Drupal\migrate\MigrateExecutable|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $migrateExecutable;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->row = $this->getMockBuilder(Row::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->migrateExecutable = $this->getMockBuilder(MigrateExecutable::class)
      ->disableOriginalConstructor()
      ->getMock();

    parent::setUp();
  }

}
