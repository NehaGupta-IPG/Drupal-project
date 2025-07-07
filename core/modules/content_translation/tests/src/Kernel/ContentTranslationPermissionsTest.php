<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Kernel;

use Drupal\entity_test\Entity\EntityTestMulBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the content translation dynamic permissions.
 *
 * @group content_translation
 *
 * @coversDefaultClass \Drupal\content_translation\ContentTranslationPermissions
 */
class ContentTranslationPermissionsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language', 'content_translation', 'user', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('entity_test_mul_with_bundle');
    EntityTestMulBundle::create([
      'id' => 'test',
      'label' => 'Test label',
      'description' => 'My test description',
    ])->save();
  }

  /**
   * Tests that enabling translation via the API triggers schema updates.
   */
  public function testPermissions(): void {
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mul', 'entity_test_mul', TRUE);
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mul_with_bundle', 'test', TRUE);
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertEquals(['entity_test'], $permissions['translate entity_test_mul']['dependencies']['module']);
    $this->assertEquals([
      'entity_test.entity_test_mul_bundle.test',
      'language.content_settings.entity_test_mul_with_bundle.test',
    ], $permissions['translate test entity_test_mul_with_bundle']['dependencies']['config']);

    // Ensure bundle permission granularity works for bundles not based on
    // configuration.
    $this->container->get('state')->set('entity_test_mul.permission_granularity', 'bundle');
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertEquals(['entity_test'], $permissions['translate entity_test_mul entity_test_mul']['dependencies']['module']);
    $this->assertEquals(['language.content_settings.entity_test_mul.entity_test_mul'], $permissions['translate entity_test_mul entity_test_mul']['dependencies']['config']);

    // Ensure bundle permission is removed from role when content translation
    // for the bundle is disabled.
    $rid = $this->createRole(['translate test entity_test_mul_with_bundle']);
    $role = Role::load($rid);
    $this->assertEquals(['translate test entity_test_mul_with_bundle'], $role->getPermissions());
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mul_with_bundle', 'test', FALSE);
    $role = Role::load($rid);
    $this->assertEquals([], $role->getPermissions());
  }

}
