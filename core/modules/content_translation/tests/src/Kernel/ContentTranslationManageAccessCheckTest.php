<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Kernel;

use Drupal\content_translation\Access\ContentTranslationManageAccessCheck;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

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
  }

  /**
   * Tests create translation access when source and/or target params are NULL.
   *
   * @legacy-covers ::access
   */
  public function testCreateAccessWithNullSourceOrTarget(): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage('entity_test_mul')
      ->create(['langcode' => 'en']);
    $entity->save();

    $entity_type = $this->container->get('entity_type.manager')->getDefinition('entity_test_mul');
    $route = new Route($entity_type->getLinkTemplate('drupal:content-translation-add'));
    $route->setRequirement('_access_content_translation_manage', 'create');

    $route_match = new RouteMatch('test', $route, ['entity_test_mul' => $entity]);
    $account = new AnonymousUserSession();

    $check = $this->container->get('content_translation.manage_access');

    // Source set, target absent (e.g. breadcrumb checking /translations/add/en).
    $this->assertFalse($check->access($route, $route_match, $account, 'en', NULL, NULL, 'entity_test_mul')->isAllowed());
    // Both absent (e.g. breadcrumb checking /translations/add).
    $this->assertFalse($check->access($route, $route_match, $account, NULL, NULL, NULL, 'entity_test_mul')->isAllowed());
  }

}
