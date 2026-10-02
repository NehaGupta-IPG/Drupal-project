<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\HandlerQueryInterface;
use Drupal\views\Plugin\views\query\SqlQueryInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewsData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * Tests Drupal\views\Plugin\views\HandlerBase.
 */
#[CoversClass(HandlerBase::class)]
#[Group('Views')]
class HandlerBaseTest extends UnitTestCase {

  /**
   * Tests get entity type for field on base table.
   */
  public function testGetEntityTypeForFieldOnBaseTable(): void {
    $view = $this->createMock(View::class);
    $executable = $this->createStub(ViewExecutable::class);
    $executable->storage = $view;
    $viewsData = $this->createMock(ViewsData::class);

    $handler = new TestHandler([], 'test_handler', []);
    $handler->init($executable, $this->createStub(DisplayPluginBase::class));

    $view->expects($this->once())
      ->method('get')
      ->with('base_table')
      ->willReturn('test_entity_type_table');
    $viewsData->expects($this->once())
      ->method('get')
      ->with('test_entity_type_table')
      ->willReturn([
        'table' => ['entity type' => 'test_entity_type'],
      ]);
    $handler->setViewsData($viewsData);

    $this->assertEquals('test_entity_type', $handler->getEntityType());
  }

  /**
   * Tests get entity type for field with relationship.
   */
  public function testGetEntityTypeForFieldWithRelationship(): void {
    $display = $this->createMock(DisplayPluginBase::class);
    $viewsData = $this->createMock(ViewsData::class);
    $handler = new TestHandler([], 'test_handler', []);

    $options = ['relationship' => 'test_relationship'];
    $handler->init($this->createStub(ViewExecutable::class), $display, $options);

    $display->expects($this->atLeastOnce())
      ->method('getOption')
      ->with('relationships')
      ->willReturn([
        'test_relationship' => [
          'table' => 'test_entity_type_table',
          'id' => 'test_relationship',
          'field' => 'test_relationship',
        ],
      ]);

    $viewsData->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap([
        [
          'test_entity_type_table',
          [
            'table' => ['entity type' => 'test_entity_type'],
            'test_relationship' => [
              'relationship' => [
                'base' => 'test_other_entity_type_table',
                'base field' => 'id',
              ],
            ],
          ],
        ],
        [
          'test_other_entity_type_table',
          ['table' => ['entity type' => 'test_other_entity_type']],
        ],
      ]);
    $handler->setViewsData($viewsData);

    $this->assertEquals('test_other_entity_type', $handler->getEntityType());
  }

  /**
   * Tests explicit backend-neutral handler query access.
   */
  public function testHandlerQueryContract(): void {
    $query = new class([], 'test_query', []) extends QueryPluginBase implements HandlerQueryInterface {

      /** {@inheritdoc} */
      public function addField($table, $field, $alias = '', array $params = []) {
        return $alias ?: $field;
      }

      /** {@inheritdoc} */
      public function addWhere($group, $field, $value = NULL, $operator = NULL) {
      }

      /** {@inheritdoc} */
      public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', array $params = []) {
      }

    };
    $handler = $this->createHandlerWithQuery($query);
    $this->assertSame($query, $handler->getHandlerQueryContract());
  }

  /**
   * Tests legacy duck-typed handler query methods remain supported.
   */
  #[IgnoreDeprecations]
  public function testLegacyHandlerQueryContract(): void {
    $query = new class([], 'test_query', []) extends QueryPluginBase {
      /**
       * Whether the legacy method was called.
       */
      /**
       * Whether the legacy method was called.
       */
      public bool $called = FALSE;

      /**
       * Records a legacy handler-query call.
       */
      public function addWhere($group, $field, $value = NULL, $operator = NULL): void {
        $this->called = TRUE;
      }

    };
    $handler = $this->createHandlerWithQuery($query);

    $this->expectUserDeprecationMessage('Duck-typed support for addWhere() on Views query plugins is deprecated in drupal:12.0.0 and is removed from drupal:14.0.0. Implement Drupal\\views\\Plugin\\views\\query\\HandlerQueryInterface instead. See https://www.drupal.org/node/2484565');
    $handler->getHandlerQueryContract()->addWhere(0, 'field', 'value');
    $this->assertTrue($query->called);
  }

  /**
   * Tests unsupported backend-neutral operations fail explicitly.
   */
  public function testUnsupportedHandlerQueryOperation(): void {
    $handler = $this->createHandlerWithQuery(new class([], 'test_query', []) extends QueryPluginBase {
    });

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('does not support addWhere().');
    $handler->getHandlerQueryContract()->addWhere(0, 'field', 'value');
  }

  /**
   * Tests legacy SQL query methods remain supported.
   */
  #[IgnoreDeprecations]
  public function testLegacySqlQueryContract(): void {
    $query = new class([], 'test_query', []) extends QueryPluginBase {
      /**
       * Whether the legacy method was called.
       */
      public bool $called = FALSE;

      /**
       * Records a legacy SQL-query call.
       */
      public function addWhereExpression($group, $snippet, $args = []): void {
        $this->called = TRUE;
      }

    };
    $handler = $this->createHandlerWithQuery($query);

    $this->expectUserDeprecationMessage('Duck-typed support for SQL operation addWhereExpression() on Views query plugins is deprecated in drupal:12.0.0 and is removed from drupal:14.0.0. Implement Drupal\\views\\Plugin\\views\\query\\SqlQueryInterface when the query supports SQL semantics. See https://www.drupal.org/node/2484565');
    $handler->getSqlQueryContract()->addWhereExpression(0, '1 = 1');
    $this->assertTrue($query->called);
  }

  /**
   * Tests unsupported SQL operations fail explicitly.
   */
  public function testUnsupportedSqlQueryOperation(): void {
    $handler = $this->createHandlerWithQuery(new class([], 'test_query', []) extends QueryPluginBase {
    });

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('does not support SQL operation addWhereExpression().');
    $handler->getSqlQueryContract()->addWhereExpression(0, '1 = 1');
  }

  /**
   * Tests legacy ensureTable() implementations remain supported.
   */
  #[IgnoreDeprecations]
  public function testLegacyEnsureMyTableCapability(): void {
    $query = new class([], 'test_query', []) extends QueryPluginBase {

      /**
       * Emulates a legacy ensureTable() implementation.
       */
      public function ensureTable($table, $relationship = NULL, $join = NULL): string {
        return 'legacy_alias';
      }

    };
    $handler = $this->createHandlerWithQuery($query);
    $handler->table = 'ignored_table';

    $this->expectUserDeprecationMessage('Duck-typed support for SQL operation ensureTable() on Views query plugins is deprecated in drupal:12.0.0 and is removed from drupal:14.0.0. Implement Drupal\\views\\Plugin\\views\\query\\SqlQueryInterface when the query supports SQL semantics. See https://www.drupal.org/node/2484565');
    $this->assertSame('legacy_alias', $handler->ensureMyTable());
  }

  /**
   * Tests queries without table semantics do not need ensureTable().
   */
  public function testEnsureMyTableWithoutTableCapability(): void {
    $handler = $this->createHandlerWithQuery(new class([], 'test_query', []) extends QueryPluginBase {
    });
    $handler->table = 'ignored_table';
    $this->assertSame('', $handler->ensureMyTable());
  }

  /**
   * Creates a test handler with the supplied query plugin.
   */
  private function createHandlerWithQuery(QueryPluginBase $query): TestHandler {
    $executable = $this->createStub(ViewExecutable::class);
    $executable->query = $query;
    $handler = new TestHandler([], 'test_handler', []);
    $handler->init($executable, $this->createStub(DisplayPluginBase::class));
    return $handler;
  }

  /**
   * Tests that access throws AccessException when access callback is set.
   */
  public function testAccessThrowsExceptionWithAccessCallback(): void {
    $definition = ['access callback' => 'test_callback'];
    $handler = new TestHandler([], 'test_handler', $definition);

    $this->expectException(AccessException::class);
    $this->expectExceptionMessage("The 'access callback' key in Views handler definitions is no longer supported in drupal:12.0.0. Use a custom access method on the handler instead.");
    $handler->access($this->createStub(AccountInterface::class));
  }

}

/**
 * Allow testing base handler implementation by extending the abstract class.
 */
class TestHandler extends HandlerBase {

  /**
   * Exposes backend-neutral handler query operations for testing.
   */
  public function getHandlerQueryContract(): HandlerQueryInterface {
    return $this->handlerQuery();
  }

  /**
   * Exposes SQL-specific handler query operations for testing.
   */
  public function getSqlQueryContract(): SqlQueryInterface {
    return $this->sqlQuery();
  }

}
