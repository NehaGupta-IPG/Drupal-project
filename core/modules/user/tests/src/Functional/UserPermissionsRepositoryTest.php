<?php

namespace Drupal\Tests\user\Functional;

use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\Service\PermissionsRepositoryFactoryInterface;
use Drupal\user\Service\PermissionsRepositoryReturnStyle;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests extended permissions.
 */
#[RunTestsInSeparateProcesses]
class UserPermissionsRepositoryTest extends BrowserTestBase {

  /**
   * The modules we require.
   *
   * @var array<string>
   */
  protected static $modules = [
    'taxonomy',
    'user',
    'user_permissions_repository_test',
  ];

  /**
   * The theme we use.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The legacy permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $legacyPermissionHandler;

  /**
   * The new permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $newPermissionHandler;

  /**
   * The permission repository factory.
   *
   * @var \Drupal\user\Service\PermissionsRepositoryFactoryInterface
   */
  protected $permissionsRepositoryFactory;

  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'view the administration theme',
      'access content',
      'access content overview',
    ]);

    $this->drupalLogin($this->adminUser);
    $this->installModulesFromClassProperty($this->container);

    $this->legacyPermissionHandler = $this->container->get('user.legacy_permissions');
    $this->newPermissionHandler = $this->container->get('user.permissions');
    $this->permissionsRepositoryFactory = $this->container->get('user_permissions_parser.repository_factory');
  }

  /**
   * Do the legacy and current handlers provide the same permissions?
   */
  public function testSamePermissionsFromLegacyHandlerAsFromCurrentHandler() {
    $legacyPermissions = $this->legacyPermissionHandler->getPermissions();
    $newPermissions = $this->newPermissionHandler->getPermissions();

    $normalizedLegacyPermissions = $this->normalizePermissions($legacyPermissions);
    $normalizedNewPermissions = $this->normalizePermissions($newPermissions);

    $diff1 = DiffArray::diffAssocRecursive($normalizedLegacyPermissions, $normalizedNewPermissions);
    $diff2 = DiffArray::diffAssocRecursive($normalizedNewPermissions, $normalizedLegacyPermissions);

    $this->assertEquals($normalizedLegacyPermissions, $normalizedLegacyPermissions, 'permissions are not equal');
    $this->assertEmpty($diff1, 'normalizedNewPermissions differs from normalizedLegacyPermissions');
    $this->assertEmpty($diff2, 'normalizedLegacyPermissions differs from normalizedNewPermissions');
  }

  /**
   * Do we see the section added by user_permissions_repository_test.permissions.yml?
   */
  public function testSectionFromYMLFile() {
    $newPermissions = $this->newPermissionHandler->getPermissions();

    $sectionTitle = $newPermissions['access_something']['section']['title'] ?? NULL;
    $sectionHelp = $newPermissions['access_something']['section']['extended_help'] ?? NULL;
    $this->assertEquals('users', $sectionTitle, 'section title is empty or wrong: ' . $sectionTitle);
    $this->assertStringContainsString('another thing', $sectionHelp, 'section help is empty or wrong: ' . $sectionHelp);
  }

  /**
   * Do we see the permissions added by the system module?
   */
  public function testParsingPermissionFiles() {
    $repo = $this->permissionsRepositoryFactory->createPermissionsRepositoryFromPermissionFiles();

    $providersList = $repo->getProviders();
    $this->assertContains('system', $providersList);

    $providerData = $repo->getProviderData('system');

    $title = $this->getNestedArrayValue($providerData, [
      'permissions',
      'administer modules',
      'title',
    ], 'administer modules title is missing');

    $this->assertNotEmpty($title, 'administer modules title is empty');
  }

  /**
   * Do we see the section added by TestProvider?
   *
   * @see \Drupal\user_permissions_repository_test\TestProvider
   */
  public function testSectionFromTestProvider() {
    $newPermissions = $this->newPermissionHandler->getPermissions();

    $sectionTitle = $this->getNestedArrayValue($newPermissions, [
      'perm_from_testprovider',
      'section',
      'title',
    ], 'TestProvider section title is missing');

    $sectionHelp = $this->getNestedArrayValue($newPermissions, [
      'perm_from_testprovider',
      'section',
      'extended_help',
    ], 'TestProvider section extended_help is missing');

    $this->assertEquals('Section from TestProvider', $sectionTitle, 'TestProvider section title is empty or wrong: ' . $sectionTitle);
    $this->assertStringContainsString('This is just for testing', $sectionHelp, 'TestProvider section help is empty or wrong: ' . $sectionHelp);
  }

  protected function normalizePermissions(array $perms) {
    ksort($perms);

    foreach ($perms as $k => $data) {
      ksort($data);
      unset($data['extended_help']);
      unset($data['key']);
      unset($data['section']);
      unset($data['weight']);
      unset($data['overall_weight']);
      foreach ($data as $k2 => $data2) {
        if ($data2 instanceof TranslatableMarkup) {
          $data[$k2] = '' . $data2;
        }
      }
      $perms[$k] = $data;
    }

    return $perms;
  }

  protected function getNestedArrayValue(mixed $input, array $keys, string $message): mixed {
    $value = $input;
    foreach ($keys as $key) {
      $this->assertArrayHasKey($key, $value, $message . ': ' . $key . ', input=' . json_encode($input));
      $value = $value[$key];
    }

    return $value;
  }

}
