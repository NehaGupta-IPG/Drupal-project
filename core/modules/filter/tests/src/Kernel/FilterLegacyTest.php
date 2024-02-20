<?php

namespace Drupal\Tests\filter\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests legacy filter behaviors.
 *
 * @group legacy
 * @group filter
 */
class FilterLegacyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'filter_test',
    'filter_test_legacy',
    'system',
    'user',
  ];

  /**
   * Tests update of roles during installation of default formats.
   *
   * @group legacy
   */
  public function testInstallRoles() {
    $this->expectDeprecation('Specifying roles in text formats is deprecated in drupal:10.3.0 and will not be supported starting in drupal:11.0.0. See https://www.drupal.org/node/3168851');
    // Install filter_test_legacy module, which ships with the
    // filter_test_legacy text format, which declares roles in configuration.
    $this->installConfig(['user', 'filter_test_legacy']);
    $format = FilterFormat::load('filter_test_legacy');

    // Verify that the loaded format does not contain any roles.
    $this->assertNull($format->get('roles'));
    // Verify that the roles property is not part of the configuration export.
    $this->assertFalse(isset($format->toArray()['roles']));
    // Verify that the defined roles in the default config have been processed.
    $this->assertEquals([
      RoleInterface::ANONYMOUS_ID,
      RoleInterface::AUTHENTICATED_ID,
    ], array_keys(filter_get_roles_by_format($format)));
  }

  /**
   * Tests that changes to FilterFormat::$roles do not have an effect.
   *
   * @group legacy
   */
  public function testUpdateRoles() {
    $this->expectDeprecation('The \'roles\' property of text formats is deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. See https://www.drupal.org/node/3168851.');
    // Install filter_test module, which ships with the filter_test format.
    $this->installConfig(['user', 'filter_test']);
    $format = FilterFormat::load('filter_test');
    /* @see filter_test_filter_format_insert() */
    $this->assertEquals([
      RoleInterface::ANONYMOUS_ID,
      RoleInterface::AUTHENTICATED_ID,
    ], array_keys(filter_get_roles_by_format($format)));

    // Change the roles property.
    $format->set('roles', [RoleInterface::AUTHENTICATED_ID]);
    $format->save();
    // Verify that the roles property is not part of the configuration export.
    $this->assertFalse(isset($format->toArray()['roles']));

    // Update the role configuration directly to trigger the configuration
    // schema deprecation.
    $config = $this->config('filter.format.filter_test');
    $config->set('roles', [RoleInterface::AUTHENTICATED_ID])->save();

    // Verify that roles have not been updated.
    $format = FilterFormat::load('filter_test');
    $this->assertEquals([
      RoleInterface::ANONYMOUS_ID,
      RoleInterface::AUTHENTICATED_ID,
    ], array_keys(filter_get_roles_by_format($format)));
  }

}
