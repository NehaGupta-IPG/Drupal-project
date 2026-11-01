<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests link formatter access check behavior.
 */
#[Group('link')]
#[RunTestsInSeparateProcesses]
final class LinkFormatterAccessCheckTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['link'];

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->renderer = $this->container->get('renderer');

    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $role->grantPermission('view test entity');
    $role->save();

    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_link',
      'type' => 'link',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_link',
      'bundle' => 'entity_test',
      'settings' => ['title' => 1],
    ])->save();
  }

  /**
   * Tests that an inaccessible entity link is removed.
   */
  #[DataProvider('providerFormatters')]
  public function testEntityUri(string $formatter): void {
    $target = EntityTest::create(['name' => 'Target']);
    $target->save();
    $items = [['uri' => 'entity:entity_test/' . $target->id(), 'title' => 'View target']];
    $url = $target->toUrl()->toString();

    $this->assertStringContainsString($url, $this->renderField($items, ['access_check' => TRUE], $formatter));

    $this->revokeViewAccess();

    $this->assertSame('', $this->renderField($items, ['access_check' => TRUE], $formatter));
    // The check is off by default, so a display without settings keeps it.
    $this->assertStringContainsString($url, $this->renderField($items, [], $formatter));
  }

  /**
   * Tests that only routed URLs are access checked.
   */
  public function testUrlTypes(): void {
    // The access control handler of entity_test forbids access to entities
    // labeled 'forbid_access'.
    $target = EntityTest::create(['name' => 'forbid_access']);
    $target->save();

    // An internal path resolves to a route, so it is checked.
    $items = [['uri' => 'internal:/entity_test/' . $target->id(), 'title' => 'Internal']];
    $this->assertSame('', $this->renderField($items, ['access_check' => TRUE]));
    $this->assertStringContainsString($target->toUrl()->toString(), $this->renderField($items, ['access_check' => FALSE]));

    // An external URL has no route to check, so it is always kept.
    $items = [['uri' => 'https://example.com/', 'title' => 'External']];
    $this->assertStringContainsString('https://example.com/', $this->renderField($items, ['access_check' => TRUE]));
  }

  /**
   * Tests that only the inaccessible items of a multi-value field are removed.
   */
  public function testMultipleItems(): void {
    $accessible = EntityTest::create(['name' => 'Target']);
    $accessible->save();
    $inaccessible = EntityTest::create(['name' => 'forbid_access']);
    $inaccessible->save();
    $items = [
      ['uri' => 'entity:entity_test/' . $accessible->id(), 'title' => 'Accessible'],
      ['uri' => 'entity:entity_test/' . $inaccessible->id(), 'title' => 'Inaccessible'],
    ];

    $output = $this->renderField($items, ['access_check' => TRUE]);
    $this->assertStringContainsString($accessible->toUrl()->toString(), $output);
    $this->assertStringNotContainsString($inaccessible->toUrl()->toString(), $output);
  }

  /**
   * Tests that the access cacheability is kept on the build.
   */
  public function testAccessCacheability(): void {
    $target = EntityTest::create(['name' => 'Target']);
    $target->save();
    $items = [['uri' => 'entity:entity_test/' . $target->id(), 'title' => 'View target']];

    $build = $this->buildField($items);
    $this->assertNotContains('user.permissions', $build['#cache']['contexts'] ?? []);

    // Without this, a response cached while the link was hidden would keep
    // hiding it from users allowed to see it.
    $build = $this->buildField($items, ['access_check' => TRUE]);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    $this->revokeViewAccess();
    $build = $this->buildField($items, ['access_check' => TRUE]);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);
  }

  /**
   * Tests that a link to a deleted entity does not disable caching.
   *
   * @todo Unblock once a failed conversion can express a stable "not found".
   */
  public function testDanglingReferenceIsCacheable(): void {
    $target = EntityTest::create(['name' => 'Target']);
    $target->save();
    $items = [['uri' => 'entity:entity_test/' . $target->id(), 'title' => 'Gone']];
    $target->delete();

    // Without the check the build is cacheable, and the broken link renders.
    $build = $this->buildField($items);
    $this->assertSame(Cache::PERMANENT, $build['#cache']['max-age']);
    $this->assertStringContainsString('Gone', (string) $this->renderer->renderRoot($build));

    // With it the link is removed, and the build stays cacheable.
    $build = $this->buildField($items, ['access_check' => TRUE]);
    $this->assertSame(Cache::PERMANENT, $build['#cache']['max-age']);
    $this->assertSame('', trim((string) $this->renderer->renderRoot($build)));
  }

  /**
   * Provides the formatters that run the access check.
   */
  public static function providerFormatters(): array {
    return [
      'link' => ['link'],
      'link_separate' => ['link_separate'],
    ];
  }

  /**
   * Revokes access to all test entities.
   */
  protected function revokeViewAccess(): void {
    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $role->revokePermission('view test entity');
    $role->save();
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('entity_test')
      ->resetCache();
  }

  /**
   * Builds the link field of an entity holding the given link items.
   */
  protected function buildField(array $items, array $settings = [], string $formatter = 'link'): array {
    $entity = EntityTest::create(['field_link' => $items]);
    $entity->save();

    return $entity->get('field_link')->view([
      'type' => $formatter,
      'label' => 'hidden',
      'settings' => $settings,
    ]);
  }

  /**
   * Renders the link field of an entity holding the given link items.
   */
  protected function renderField(array $items, array $settings = [], string $formatter = 'link'): string {
    $build = $this->buildField($items, $settings, $formatter);
    return trim((string) $this->renderer->renderRoot($build));
  }

}
