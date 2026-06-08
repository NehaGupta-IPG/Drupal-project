<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\views\ManyToOneHelper;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the many to one helper.
 */
#[CoversClass(ManyToOneHelper::class)]
#[Group('views')]
class ManyToOneHelperTest extends UnitTestCase
{

    /**
     * Creates a mocked filter handler usable by the helper.
     *
     * @param mixed $join
     *   The value that the handler's getJoin() should return. Use NULL to
     *   simulate a handler whose table is not reached through a relationship and
     *   therefore has no join definition.
     *
     * @return \Drupal\views\Plugin\views\filter\ManyToOne|\PHPUnit\Framework\MockObject\MockObject
     *   The mocked handler.
     */
    protected function createHandler($join)
    {
        $handler = $this->getMockBuilder(ManyToOne::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getJoin'])
            ->getMock();
        $handler->method('getJoin')->willReturn($join);

        $handler->table = 'test_table';
        $handler->field = 'status_id';
        $handler->realField = 'status_id';
        $handler->relationship = null;
        $handler->operator = 'or';
        $handler->options = [];
        $handler->value = ['1' => '1', '2' => '2'];
        $handler->definition = ['numeric' => true];
        $handler->tableAlias = null;

        $handler->view = $this->getMockBuilder(ViewExecutable::class)
            ->disableOriginalConstructor()
            ->getMock();
        $handler->view->many_to_one_tables = [];

        return $handler;
    }

    /**
     * The lookup key the helper builds for a handler.
     *
     * @see \Drupal\views\ManyToOneHelper::ensureMyTable()
     */
    protected function fieldKey($handler): string
    {
        return $handler->relationship . '_' . $handler->table . '.' . $handler->field;
    }

    /**
     * Tests that an already-resolved table alias is returned untouched.
     *
     * @legacy-covers ::ensureMyTable
     */
    public function testEnsureMyTableReturnsExistingAlias(): void
    {
        $handler = $this->createHandler(null);
        $handler->tableAlias = 'existing_alias';
        // The query should never be consulted when an alias already exists.
        $handler->query = $this->createMock(Sql::class);
        $handler->query->expects($this->never())->method('ensureTable');

        $helper = new ManyToOneHelper($handler);
        $this->assertSame('existing_alias', $helper->ensureMyTable());
    }

    /**
     * Tests the first filter on a field: a simple INNER join is added.
     *
     * @legacy-covers ::ensureMyTable
     */
    public function testEnsureMyTableFirstFilterAddsInnerJoin(): void
    {
        $join = $this->getMockBuilder(JoinPluginBase::class)
            ->disableOriginalConstructor()
            ->getMock();

        $handler = $this->createHandler($join);
        $handler->query = $this->createMock(Sql::class);
        $handler->query->expects($this->once())
            ->method('ensureTable')
            ->with('test_table', null, $join)
            ->willReturn('test_table');

        $helper = new ManyToOneHelper($handler);
        $this->assertSame('test_table', $helper->ensureMyTable());
        // The join is converted to INNER and the used values are recorded.
        $this->assertSame('INNER', $join->type);
        $this->assertSame($handler->value, $handler->view->many_to_one_tables[$this->fieldKey($handler)]);
    }

    /**
     * Tests a second filter on a field whose table has no join definition.
     *
     * This is the regression test for the bug where a view has two "is one of"
     * filters on the same many-to-one field (for example a fixed filter plus an
     * exposed group filter). The first filter populates
     * $view->many_to_one_tables for the field, which pushes the second filter
     * into the "else" branch of ensureMyTable(). When the field's table is not
     * reached via a relationship, getJoin() returns NULL and the old code
     * crashed while trying to operate on the missing join. The handler must now
     * fall back to ensureTable() instead.
     *
     * @legacy-covers ::ensureMyTable
     */
    public function testEnsureMyTableSecondFilterWithoutJoin(): void
    {
        $handler = $this->createHandler(null);
        // Simulate the first filter having already registered the field.
        $handler->view->many_to_one_tables[$this->fieldKey($handler)] = ['9' => '9'];

        $handler->query = $this->createMock(Sql::class);
        $handler->query->expects($this->once())
            ->method('ensureTable')
            ->with('test_table', null, null)
            ->willReturn('test_table');

        $helper = new ManyToOneHelper($handler);
        // Must not throw and must resolve to the table alias.
        $this->assertSame('test_table', $helper->ensureMyTable());
        // The handler records its own values for the field key.
        $this->assertSame($handler->value, $handler->view->many_to_one_tables[$this->fieldKey($handler)]);
    }

    /**
     * Tests the "add_table" option with a table that has no join definition.
     *
     * The "add_table" option also routes ensureMyTable() into the "else" branch.
     * Combined with a NULL join it must fall back to ensureTable() rather than
     * fail.
     *
     * @legacy-covers ::ensureMyTable
     */
    public function testEnsureMyTableAddTableWithoutJoin(): void
    {
        $handler = $this->createHandler(null);
        $handler->options = ['add_table' => true];

        $handler->query = $this->createMock(Sql::class);
        $handler->query->expects($this->once())
            ->method('ensureTable')
            ->with('test_table', null, null)
            ->willReturn('test_table');

        $helper = new ManyToOneHelper($handler);
        $this->assertSame('test_table', $helper->ensureMyTable());
        $this->assertSame($handler->value, $handler->view->many_to_one_tables[$this->fieldKey($handler)]);
    }

}
