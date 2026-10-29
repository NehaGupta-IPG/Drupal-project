<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Unit\Hook;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\Hook\NodeEntityHooks;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests node entity hooks.
 */
#[CoversClass(NodeEntityHooks::class)]
#[Group('node')]
final class NodeEntityHooksTest extends UnitTestCase {

  private NodeEntityHooks $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // AccessResult::getCacheContexts() asserts tokens via 'cache_contexts_manager'.
    $ccm = $this->createMock(CacheContextsManager::class);
    $ccm->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $ccm);
    \Drupal::setContainer($container);
    $this->sut = new NodeEntityHooks();
  }

  /**
   * Tests node access hook.
   *
   * @param string $op
   *   The node operation.
   * @param string $bundle
   *   The bundle.
   * @param int $ownerUid
   *   The owner ID.
   * @param int $accountUid
   *   The account ID.
   * @param array $grantedPermissions
   *   The permissions of the account.
   * @param string $expectedResult
   *   The expected access result.
   * @param bool $expectUserCacheContext
   *   The expected cache context.
   * @param bool $expectAddCacheableDependency
   *   The expected cachable dependency.
   */
  #[DataProvider('providerNodeAccess')]
  public function testNodeAccess(
    string $op,
    string $bundle,
    int $ownerUid,
    int $accountUid,
    array $grantedPermissions,
    string $expectedResult,
    bool $expectUserCacheContext,
    bool $expectAddCacheableDependency
  ): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('getOwnerId')->willReturn($ownerUid);

    // If we expect addCacheableDependency($node), ensure the node provides tags.
    if ($expectAddCacheableDependency) {
      $node->method('getCacheTags')->willReturn(['node:99']);
      $node->method('getCacheContexts')->willReturn([]);
      $node->method('getCacheMaxAge')->willReturn(-1);
    }

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($accountUid);
    $account->method('hasPermission')->willReturnCallback(
      static function (string $perm) use ($grantedPermissions): bool {
        return in_array($perm, $grantedPermissions, TRUE);
      }
    );

    $result = $this->sut->nodeAccess($node, $op, $account);

    // Result expectation.
    switch ($expectedResult) {
      case 'allowed':
        self::assertTrue($result->isAllowed(), 'Expected allowed access.');
        break;
      case 'neutral':
        self::assertTrue($result->isNeutral(), 'Expected neutral access.');
        break;
      case 'forbidden':
        self::assertTrue($result->isForbidden(), 'Expected forbidden access.');
        break;
      default:
        self::fail('Invalid expectedResult in data provider.');
    }

    // Cacheability checks: own-* path should add 'user' context and dependency.
    $contexts = $result->getCacheContexts();
    if ($expectUserCacheContext) {
      self::assertContains('user', $contexts, 'Expected cachePerUser() to add "user" cache context.');
    } else {
      self::assertNotContains('user', $contexts, 'Did not expect "user" cache context.');
    }

    // If addCacheableDependency($node) was expected, the node tag should be present.
    $tags = $result->getCacheTags();
    if ($expectAddCacheableDependency) {
      self::assertContains('node:99', $tags, 'Expected node cache tag added via addCacheableDependency($node).');
    } else {
      self::assertNotContains('node:99', $tags, 'Did not expect node cache tag.');
    }
  }

  public static function providerNodeAccess(): array {
    $bundle = 'article';
    return [
      // update: edit any => allowed.
      'update any allowed' => [
        'op' => 'update',
        'bundle' => $bundle,
        'ownerUid' => 5,
        'accountUid' => 7,
        'grantedPermissions' => ["edit any {$bundle} content"],
        'expectedResult' => 'allowed',
        'expectUserCacheContext' => FALSE,
        'expectAddCacheableDependency'=> FALSE,
      ],
      // update: edit own + owner => allowed + user context + dependency.
      'update own allowed as owner' => [
        'op' => 'update',
        'bundle' => $bundle,
        'ownerUid' => 7,
        'accountUid' => 7,
        'grantedPermissions' => ["edit own {$bundle} content"],
        'expectedResult' => 'allowed',
        'expectUserCacheContext' => TRUE,
        'expectAddCacheableDependency' => TRUE,
      ],
      // delete: delete any => allowed.
      'delete any allowed' => [
        'op' => 'delete',
        'bundle' => $bundle,
        'ownerUid' => 9,
        'accountUid' => 2,
        'grantedPermissions' => ["delete any {$bundle} content"],
        'expectedResult' => 'allowed',
        'expectUserCacheContext' => FALSE,
        'expectAddCacheableDependency'=> FALSE,
      ],
      // delete: delete own + owner => allowed + user context + dependency.
      'delete own allowed as owner' => [
        'op' => 'delete',
        'bundle' => $bundle,
        'ownerUid' => 2,
        'accountUid' => 2,
        'grantedPermissions' => ["delete own {$bundle} content"],
        'expectedResult' => 'allowed',
        'expectUserCacheContext' => TRUE,
        'expectAddCacheableDependency'=> TRUE,
      ],
      // default op (e.g. view) => neutral.
      'view neutral by default' => [
        'op' => 'view',
        'bundle' => $bundle,
        'ownerUid' => 1,
        'accountUid' => 1,
        'grantedPermissions' => [],
        'expectedResult' => 'neutral',
        'expectUserCacheContext' => FALSE,
        'expectAddCacheableDependency'=> FALSE,
      ],
    ];
  }

}
