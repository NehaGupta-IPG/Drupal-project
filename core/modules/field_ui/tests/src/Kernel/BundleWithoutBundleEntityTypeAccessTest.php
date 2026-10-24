<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests Field UI access checks for bundles without a bundle entity type.
 *
 * The entity_test entity type has a bundle key but no bundle entity type, so
 * its Field UI routes have no bundle entity route parameter.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class BundleWithoutBundleEntityTypeAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'field_ui',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests the access checks without a bundle route parameter.
   */
  public function testAccessWithoutBundleParameter(): void {
    $this->assertNull($this->container->get('entity_type.manager')->getDefinition('entity_test')->getBundleEntityType());

    // The field reuse check only allows access when a reusable field exists.
    FieldStorageConfig::create([
      'field_name' => 'field_reusable',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();

    $account = $this->createUser([
      'administer entity_test display',
      'administer entity_test form display',
      'administer entity_test fields',
    ]);

    $checks = [
      'access_check.field_ui.view_mode' => '_field_ui_view_mode_access',
      'access_check.field_ui.form_mode' => '_field_ui_form_mode_access',
      'access_check.field_ui.field_reuse' => '_field_ui_field_reuse_access',
    ];
    $permissions = [
      '_field_ui_view_mode_access' => 'administer entity_test display',
      '_field_ui_form_mode_access' => 'administer entity_test form display',
      '_field_ui_field_reuse_access' => 'administer entity_test fields',
    ];
    foreach ($checks as $service => $requirement) {
      // Field UI sets the bundle default to an empty string on routes of
      // entity types without a bundle entity type.
      $route = new Route('/entity_test/display', ['entity_type_id' => 'entity_test', 'bundle' => ''], [$requirement => $permissions[$requirement]]);
      $route_match = new RouteMatch('test', $route);
      $access = $this->container->get($service)->access($route, $route_match, $account);
      $this->assertTrue($access->isAllowed(), $service);
    }
  }

}
