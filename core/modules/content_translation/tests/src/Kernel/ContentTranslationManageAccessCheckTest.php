<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Kernel;

use Drupal\content_translation\Access\ContentTranslationManageAccessCheck;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ContentTranslationManageAccessCheck.
 */
#[CoversClass(ContentTranslationManageAccessCheck::class)]
#[Group('content_translation')]
#[RunTestsInSeparateProcesses]
class ContentTranslationManageAccessCheckTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'entity_test',
    'language',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mul');
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mul', 'entity_test_mul', TRUE);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests create translation access when source and/or target params are NULL.
   *
   * @legacy-covers ::access
   */
  public function testCreateAccessWithNullSourceOrTarget(): void {
    $entity = EntityTestMul::create(['langcode' => 'en']);
    $entity->save();

    $route_name = 'entity.entity_test_mul.content_translation_add';
    $route = $this->container->get('router.route_provider')->getRouteByName($route_name);
    $route_match = new RouteMatch($route_name, $route, ['entity_test_mul' => $entity]);
    $account = new AnonymousUserSession();
    $check = $this->container->get('content_translation.manage_access');

    // Source set, target absent (e.g. breadcrumb checking /translations/add/en).
    $this->assertFalse($check->access($route, $route_match, $account, 'en', NULL, NULL, 'entity_test_mul')->isAllowed());
    // Both absent (e.g. breadcrumb checking /translations/add).
    $this->assertFalse($check->access($route, $route_match, $account, NULL, NULL, NULL, 'entity_test_mul')->isAllowed());
  }

}
