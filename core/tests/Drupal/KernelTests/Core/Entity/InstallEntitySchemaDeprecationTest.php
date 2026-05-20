<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deprecation of installEntitySchema() for config entities.
 */
#[Group('entity')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class InstallEntitySchemaDeprecationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user'];

  /**
   * Tests that installEntitySchema() triggers deprecation for config entities.
   */
  public function testInstallEntitySchemaConfigDeprecation(): void {
    $this->expectUserDeprecationMessage('installEntitySchema() is deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/project/drupal/issues/3343518');
    @$this->installEntitySchema('user_role');
  }

}
