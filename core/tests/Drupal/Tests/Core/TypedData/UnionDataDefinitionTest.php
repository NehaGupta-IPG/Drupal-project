<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\UnionDataDefinition;
use Drupal\Core\TypedData\UnionDataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the union data definition.
 */
#[CoversClass(UnionDataDefinition::class)]
#[Group('TypedData')]
class UnionDataDefinitionTest extends UnitTestCase {

  /**
   * Tests that create() takes its members as variadic arguments.
   */
  public function testCreateTakesVariadicMembers(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($integer, $string);

    $this->assertInstanceOf(UnionDataDefinitionInterface::class, $definition);
    $this->assertSame('union', $definition->getDataType());
    $this->assertSame([$integer, $string], $definition->getMemberDefinitions());
  }

  /**
   * Tests that the declaration order of the members is preserved.
   */
  public function testMemberOrderIsPreserved(): void {
    $members = [
      DataDefinition::create('email'),
      DataDefinition::create('uri'),
      DataDefinition::create('string'),
    ];
    $definition = UnionDataDefinition::create(...$members);

    $this->assertSame($members, $definition->getMemberDefinitions());
    $this->assertSame(
      ['email', 'uri', 'string'],
      array_map(fn (DataDefinitionInterface $member) => $member->getDataType(), $definition->getMemberDefinitions()),
    );
  }

  /**
   * Tests that a union can be created without any member.
   */
  public function testCreateWithoutMembers(): void {
    $definition = UnionDataDefinition::create();
    $this->assertSame([], $definition->getMemberDefinitions());
    $this->assertNull($definition->getResolverCallback());
  }

  /**
   * Tests that createFromDataType() yields an empty-member union.
   */
  public function testCreateFromDataType(): void {
    $definition = UnionDataDefinition::createFromDataType('union');
    $this->assertInstanceOf(UnionDataDefinition::class, $definition);
    $this->assertSame('union', $definition->getDataType());
    $this->assertSame([], $definition->getMemberDefinitions());
  }

  /**
   * Tests that createFromDataType() rejects any data type but 'union'.
   */
  public function testCreateFromDataTypeRejectsOtherTypes(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Union data definitions must be of data type "union".');
    UnionDataDefinition::createFromDataType('string');
  }

  /**
   * Tests that the data type of a union cannot be changed.
   */
  public function testSetDataTypeRejectsOtherTypes(): void {
    $definition = UnionDataDefinition::create();
    $this->assertSame($definition, $definition->setDataType('union'));

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Unions must always be of data type "union".');
    $definition->setDataType('string');
  }

  /**
   * Tests that a non-definition member is rejected.
   */
  public function testCreateRejectsNonDefinitionMembers(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Union members must be instances of \Drupal\Core\TypedData\DataDefinitionInterface.');
    // @phpstan-ignore argument.type
    UnionDataDefinition::create(DataDefinition::create('string'), 'integer');
  }

  /**
   * Tests setting and appending members after creation.
   */
  public function testSetAndAddMemberDefinitions(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $map = MapDataDefinition::create();

    $definition = UnionDataDefinition::create();
    $this->assertSame($definition, $definition->setMemberDefinitions([$integer, $string]));
    $this->assertSame([$integer, $string], $definition->getMemberDefinitions());

    $this->assertSame($definition, $definition->addMemberDefinition($map));
    $this->assertSame([$integer, $string, $map], $definition->getMemberDefinitions());

    // Setting replaces rather than appends, and the keys are renumbered.
    $definition->setMemberDefinitions([3 => $map, 7 => $integer]);
    $this->assertSame([$map, $integer], $definition->getMemberDefinitions());
  }

  /**
   * Tests that cloning a union deep-clones its members.
   */
  public function testCloneIsDeep(): void {
    $member = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($member);
    $clone = clone $definition;

    $clone_members = $clone->getMemberDefinitions();
    $this->assertCount(1, $clone_members);
    $this->assertNotSame($member, $clone_members[0]);
    $this->assertEquals($member, $clone_members[0]);

    // Changing the clone's member leaves the original member untouched.
    $clone_members[0]->setLabel('Cloned');
    $this->assertSame('Cloned', $clone->getMemberDefinitions()[0]->getLabel());
    $this->assertNull($definition->getMemberDefinitions()[0]->getLabel());

    // And the other way around.
    $member->addConstraint('Length', ['max' => 5]);
    $this->assertSame(['Length' => ['max' => 5]], $definition->getMemberDefinitions()[0]->toArray()['constraints']);
    $this->assertArrayNotHasKey('constraints', $clone->getMemberDefinitions()[0]->toArray());

    // Adding a member to the clone does not add it to the original.
    $clone->addMemberDefinition(DataDefinition::create('integer'));
    $this->assertCount(2, $clone->getMemberDefinitions());
    $this->assertCount(1, $definition->getMemberDefinitions());
  }

  /**
   * Tests that a nested union member is deep-cloned too.
   */
  public function testCloneIsDeepForNestedUnions(): void {
    $inner_member = DataDefinition::create('string');
    $inner = UnionDataDefinition::create($inner_member);
    $outer = UnionDataDefinition::create(DataDefinition::create('integer'), $inner);

    $clone = clone $outer;
    $cloned_inner = $clone->getMemberDefinitions()[1];
    $this->assertInstanceOf(UnionDataDefinition::class, $cloned_inner);
    $this->assertNotSame($inner, $cloned_inner);
    $this->assertNotSame($inner_member, $cloned_inner->getMemberDefinitions()[0]);
  }

  /**
   * Tests that only string callables are accepted as resolver callbacks.
   */
  public function testSetResolverCallbackAcceptsStrings(): void {
    $definition = UnionDataDefinition::create();
    $this->assertSame($definition, $definition->setResolverCallback('trim'));
    $this->assertSame('trim', $definition->getResolverCallback());

    $definition->setResolverCallback(self::class . '::staticResolver');
    $this->assertSame(self::class . '::staticResolver', $definition->getResolverCallback());

    $definition->setResolverCallback(NULL);
    $this->assertNull($definition->getResolverCallback());
  }

  /**
   * Tests that a closure is rejected as a resolver callback.
   */
  public function testSetResolverCallbackRejectsClosures(): void {
    $definition = UnionDataDefinition::create();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Union resolver callback must be a string, like "function_name" or "ClassName::methodName"');
    $definition->setResolverCallback(fn () => NULL);
  }

  /**
   * Tests that an array callable is rejected as a resolver callback.
   */
  public function testSetResolverCallbackRejectsArrays(): void {
    $definition = UnionDataDefinition::create();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Union resolver callback must be a string, like "function_name" or "ClassName::methodName"');
    $definition->setResolverCallback([self::class, 'staticResolver']);
  }

  /**
   * Tests that a union definition survives a serialization round trip.
   */
  public function testSerializationRoundTrip(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      DataDefinition::create('string')->setLabel('A string'),
    );
    $definition->setResolverCallback(self::class . '::staticResolver');
    $definition->setLabel('A union');
    $definition->setRequired(TRUE);

    $restored = unserialize(serialize($definition));
    $this->assertInstanceOf(UnionDataDefinition::class, $restored);
    $this->assertSame('union', $restored->getDataType());
    $this->assertSame('A union', $restored->getLabel());
    $this->assertTrue($restored->isRequired());
    $this->assertSame(self::class . '::staticResolver', $restored->getResolverCallback());

    $members = $restored->getMemberDefinitions();
    $this->assertCount(2, $members);
    $this->assertSame('integer', $members[0]->getDataType());
    $this->assertSame('string', $members[1]->getDataType());
    $this->assertSame('A string', $members[1]->getLabel());
    // The restored members are copies, not the original objects.
    $this->assertNotSame($definition->getMemberDefinitions()[0], $members[0]);
  }

  /**
   * Tests that the members are not part of the definition array.
   */
  public function testToArrayDoesNotLeakMembers(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      MapDataDefinition::create(),
    );
    $definition->setLabel('A union');
    $definition->setResolverCallback('trim');

    $this->assertSame([
      'type' => 'union',
      'label' => 'A union',
      'resolver_callback' => 'trim',
    ], $definition->toArray());
    // The members are still there, they are just held next to the array.
    $this->assertCount(2, $definition->getMemberDefinitions());
  }

  /**
   * A resolver callback used to check that string callables are accepted.
   *
   * @param mixed $value
   *   The value being resolved.
   * @param \Drupal\Core\TypedData\UnionDataDefinitionInterface $definition
   *   The union definition.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface|null
   *   Always NULL, this callback is never invoked by these tests.
   */
  public static function staticResolver($value, UnionDataDefinitionInterface $definition): ?DataDefinitionInterface {
    return NULL;
  }

}
