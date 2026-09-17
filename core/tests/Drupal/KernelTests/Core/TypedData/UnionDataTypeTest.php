<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\Email;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\Plugin\DataType\Union;
use Drupal\Core\TypedData\Plugin\DataType\Uri;
use Drupal\Core\TypedData\UnionDataDefinition;
use Drupal\Core\TypedData\UnionDataDefinitionInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UnionMemberConstraint;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Tests the union data type.
 *
 * Each test covers one rung of the resolution ladder documented on
 * \Drupal\Core\TypedData\Plugin\DataType\Union, plus the behavior of the
 * UnionMember constraint that reports a value matching no member.
 *
 * @see \Drupal\Core\TypedData\Plugin\DataType\Union
 * @see \Drupal\Core\TypedData\UnionDataDefinition
 * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\UnionMemberConstraintValidator
 */
#[Group('TypedData')]
#[RunTestsInSeparateProcesses]
class UnionDataTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedDataManager = $this->container->get('typed_data_manager');
  }

  /**
   * Creates a union typed data object.
   *
   * @param \Drupal\Core\TypedData\UnionDataDefinitionInterface $definition
   *   The union definition.
   * @param mixed $value
   *   The value to set.
   *
   * @return \Drupal\Core\TypedData\Plugin\DataType\Union
   *   The union typed data object.
   */
  protected function createUnion(UnionDataDefinitionInterface $definition, $value): Union {
    $union = $this->typedDataManager->create($definition, $value);
    $this->assertInstanceOf(Union::class, $union);
    return $union;
  }

  /**
   * Returns a map definition describing a link.
   *
   * @return \Drupal\Core\TypedData\MapDataDefinition
   *   A map of a 'uri' and a 'title' property.
   */
  protected function linkMapDefinition(): MapDataDefinition {
    $map = MapDataDefinition::create();
    $map->setPropertyDefinition('uri', DataDefinition::create('uri'));
    $map->setPropertyDefinition('title', DataDefinition::create('string'));
    return $map;
  }

  /**
   * Returns the messages of a violation list.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violations.
   *
   * @return string[]
   *   The violation messages, as strings.
   */
  protected function violationMessages($violations): array {
    $messages = [];
    foreach ($violations as $violation) {
      $messages[] = (string) $violation->getMessage();
    }
    return $messages;
  }

  /**
   * Tests that a single structural match resolves without any validation.
   *
   * Rung (c) of the ladder. A string can only be the 'uri' member of a
   * uri-or-link-map union, so it resolves to it. That the resolution really
   * skipped validation is visible from the second value: it is not a URI at
   * all, yet it still resolves to the 'uri' member.
   */
  public function testSingleStructuralMatchResolvesWithoutValidation(): void {
    $uri = DataDefinition::create('uri');
    $link = $this->linkMapDefinition();
    $definition = UnionDataDefinition::create($uri, $link);

    $union = $this->createUnion($definition, 'https://example.com/page');
    $this->assertSame($uri, $union->getResolvedDefinition());
    $this->assertInstanceOf(Uri::class, $union->getResolvedData());
    $this->assertSame('https://example.com/page', $union->getValue());
    $this->assertCount(0, $union->validate());

    // A string that is not a URI still resolves to the only member that could
    // hold a string. Validation then reports the mismatch, but resolution
    // never ran it.
    $invalid = $this->createUnion($definition, 'not a uri at all');
    $this->assertSame($uri, $invalid->getResolvedDefinition());
    $violations = $invalid->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('This value should be of the correct primitive type.', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests that an associative array resolves to a complex data member.
   *
   * The resolved delegate is a real Map, so the properties of the member can
   * be traversed through it.
   */
  public function testAssociativeArrayResolvesComplexMember(): void {
    $uri = DataDefinition::create('uri');
    $link = $this->linkMapDefinition();
    $definition = UnionDataDefinition::create($uri, $link);

    $union = $this->createUnion($definition, [
      'uri' => 'https://example.com/page',
      'title' => 'Example',
    ]);
    $this->assertSame($link, $union->getResolvedDefinition());
    $resolved = $union->getResolvedData();
    $this->assertInstanceOf(Map::class, $resolved);
    $this->assertSame('Example', $resolved->get('title')->getValue());
    $this->assertInstanceOf(Uri::class, $resolved->get('uri'));
    $this->assertSame('https://example.com/page', $resolved->get('uri')->getValue());
    $this->assertCount(0, $union->validate());
  }

  /**
   * Tests that overlapping members are probed in declaration order.
   *
   * Rung (d) of the ladder. Both members can hold a string, so both are probed
   * and the first one that validates wins.
   */
  public function testOverlappingMembersAreProbedInOrder(): void {
    $email = DataDefinition::create('email');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($email, $string);

    // 'not-an-email' fails the email member, so the probe falls through to the
    // string member.
    $union = $this->createUnion($definition, 'not-an-email');
    $this->assertSame($string, $union->getResolvedDefinition());
    $this->assertInstanceOf(StringData::class, $union->getResolvedData());
    $this->assertCount(0, $union->validate());

    // A valid address passes the first member, so the probe stops there.
    $union = $this->createUnion($definition, 'user@example.com');
    $this->assertSame($email, $union->getResolvedDefinition());
    $this->assertInstanceOf(Email::class, $union->getResolvedData());
  }

  /**
   * Tests that the declaration order of overlapping members is significant.
   *
   * The same value and the same two members, declared the other way around,
   * resolve to the other member.
   */
  public function testDeclarationOrderIsSignificant(): void {
    $email = DataDefinition::create('email');
    $string = DataDefinition::create('string');

    $email_first = UnionDataDefinition::create($email, $string);
    $this->assertSame($email, $this->createUnion($email_first, 'user@example.com')->getResolvedDefinition());

    $string_first = UnionDataDefinition::create($string, $email);
    $this->assertSame($string, $this->createUnion($string_first, 'user@example.com')->getResolvedDefinition());
  }

  /**
   * Tests that the structural filter does not coerce.
   *
   * The string '5' is not an integer, and the integer 5 is not a string.
   */
  public function testStructuralFilterDoesNotCoerce(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($integer, $string);

    $union = $this->createUnion($definition, '5');
    $this->assertSame($string, $union->getResolvedDefinition());
    $this->assertInstanceOf(StringData::class, $union->getResolvedData());
    $this->assertSame('5', $union->getValue());

    $union = $this->createUnion($definition, 5);
    $this->assertSame($integer, $union->getResolvedDefinition());
    $this->assertInstanceOf(IntegerData::class, $union->getResolvedData());
    $this->assertSame(5, $union->getValue());
  }

  /**
   * Tests that a resolver callback wins over the structural filter.
   *
   * Rung (b) of the ladder.
   */
  public function testResolverCallbackWinsOverStructure(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($integer, $string);
    $definition->setResolverCallback(self::class . '::resolveToLastMember');

    // Structurally the integer 5 could only be the integer member, but the
    // callback decides otherwise.
    $union = $this->createUnion($definition, 5);
    $this->assertSame($string, $union->getResolvedDefinition());
    $this->assertInstanceOf(StringData::class, $union->getResolvedData());
  }

  /**
   * Tests that a resolver callback returning NULL falls through.
   */
  public function testResolverCallbackCanFallThrough(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($integer, $string);
    $definition->setResolverCallback(self::class . '::resolveNothing');

    $union = $this->createUnion($definition, 5);
    $this->assertSame($integer, $union->getResolvedDefinition());
  }

  /**
   * Tests that a value matching no member stays unresolved.
   *
   * Rung (e) of the ladder. The value is kept as it was given, and validation
   * reports both the mismatch and the violations of the closest member.
   */
  public function testUnresolvedValueIsKeptAndReported(): void {
    $integer = DataDefinition::create('integer');
    $boolean = DataDefinition::create('boolean');
    $definition = UnionDataDefinition::create($integer, $boolean);

    $union = $this->createUnion($definition, 'hello');
    $this->assertNull($union->getResolvedDefinition());
    $this->assertNull($union->getResolvedData());
    $this->assertSame('hello', $union->getValue());
    $this->assertSame('hello', $union->getString());

    $violations = $union->validate();
    $this->assertCount(2, $violations);
    $this->assertSame([
      'The value does not match any allowed type.',
      // Both members produce one violation, so the tie is broken by the
      // declaration order and the integer member is reported.
      'This value should be of the correct primitive type.',
    ], $this->violationMessages($violations));
    $this->assertInstanceOf(UnionMemberConstraint::class, $violations->get(0)->getConstraint());
  }

  /**
   * Tests that a union without members never resolves.
   */
  public function testUnionWithoutMembers(): void {
    $union = $this->createUnion(UnionDataDefinition::create(), 'anything');
    $this->assertNull($union->getResolvedDefinition());
    $violations = $union->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('The value does not match any allowed type.', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests that strict mode reports members that overlap for a value.
   */
  public function testStrictModeReportsOverlappingMembers(): void {
    $string = DataDefinition::create('string')->setLabel('Text');
    $email = DataDefinition::create('email')->setLabel('Email address');
    $definition = UnionDataDefinition::create($string, $email);
    $definition->addConstraint('UnionMember', ['strict' => TRUE]);

    $union = $this->createUnion($definition, 'user@example.com');
    // Strictness does not change which member the value resolves to.
    $this->assertSame($string, $union->getResolvedDefinition());

    $violations = $union->validate();
    $this->assertCount(1, $violations);
    $this->assertSame(
      'The value matches more than one allowed type: Text, Email address.',
      (string) $violations->get(0)->getMessage(),
    );
  }

  /**
   * Tests that strict mode is silent for members that do not overlap.
   */
  public function testStrictModeAllowsDisjointMembers(): void {
    $short = DataDefinition::create('string')->setLabel('Short text');
    $short->addConstraint('Length', ['max' => 2]);
    $email = DataDefinition::create('email')->setLabel('Email address');
    $definition = UnionDataDefinition::create($short, $email);
    $definition->addConstraint('UnionMember', ['strict' => TRUE]);

    $union = $this->createUnion($definition, 'user@example.com');
    // Only the email member holds this value, so the probe picks it up even
    // though it is declared second.
    $this->assertSame($email, $union->getResolvedDefinition());
    $this->assertCount(0, $union->validate());
  }

  /**
   * Tests that a NULL value resolves to nothing and is not a mismatch.
   */
  public function testNullValueIsNotAMismatch(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      DataDefinition::create('string'),
    );

    $union = $this->createUnion($definition, NULL);
    $this->assertNull($union->getValue());
    $this->assertNull($union->getResolvedDefinition());
    $this->assertNull($union->getResolvedData());
    $this->assertSame('', $union->getString());
    $this->assertCount(0, $union->validate());
  }

  /**
   * Tests that a required union rejects NULL through NotNull, not UnionMember.
   */
  public function testRequiredNullValue(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      DataDefinition::create('string'),
    );
    $definition->setRequired(TRUE);

    $union = $this->createUnion($definition, NULL);
    $violations = $union->validate();
    $this->assertCount(1, $violations);
    $this->assertInstanceOf(NotNull::class, $violations->get(0)->getConstraint());

    // A required union still accepts a value that matches a member.
    $union = $this->createUnion($definition, 5);
    $this->assertCount(0, $union->validate());
  }

  /**
   * Tests that setting a new value drops the memoized resolution.
   */
  public function testSetValueClearsResolution(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($integer, $string);

    $union = $this->createUnion($definition, 5);
    $this->assertSame($integer, $union->getResolvedDefinition());
    $first_delegate = $union->getResolvedData();

    $union->setValue('five');
    $this->assertSame($string, $union->getResolvedDefinition());
    $this->assertNotSame($first_delegate, $union->getResolvedData());
    $this->assertSame('five', $union->getValue());

    $union->setValue(NULL);
    $this->assertNull($union->getResolvedDefinition());
  }

  /**
   * Tests that the resolution is memoized between reads.
   */
  public function testResolutionIsMemoized(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      DataDefinition::create('string'),
    );
    $union = $this->createUnion($definition, 5);
    $this->assertSame($union->getResolvedData(), $union->getResolvedData());
  }

  /**
   * Tests that writing through the resolved member does not re-resolve.
   */
  public function testMutationThroughResolvedMemberKeepsItsMember(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $definition = UnionDataDefinition::create($integer, $string);

    $union = $this->createUnion($definition, 5);
    $this->assertSame($integer, $union->getResolvedDefinition());

    // A value that would have resolved to the string member is written through
    // the integer delegate. The union keeps the member it took.
    $union->getResolvedData()->setValue('now a string');
    $this->assertSame($integer, $union->getResolvedDefinition());
    $this->assertSame('now a string', $union->getValue());

    // Writing the same value through the union itself does re-resolve.
    $union->setValue('now a string');
    $this->assertSame($string, $union->getResolvedDefinition());
  }

  /**
   * Tests that mutating a complex delegate is visible on the union.
   */
  public function testMutationThroughComplexResolvedMember(): void {
    $link = $this->linkMapDefinition();
    $definition = UnionDataDefinition::create(DataDefinition::create('uri'), $link);

    $union = $this->createUnion($definition, [
      'uri' => 'https://example.com/page',
      'title' => 'Example',
    ]);
    $union->getResolvedData()->set('title', 'Changed');
    $this->assertSame($link, $union->getResolvedDefinition());
    $this->assertSame([
      'uri' => 'https://example.com/page',
      'title' => 'Changed',
    ], $union->getValue());
  }

  /**
   * Tests a union that has another union as a member.
   */
  public function testNestedUnionMember(): void {
    $integer = DataDefinition::create('integer');
    $boolean = DataDefinition::create('boolean');
    $inner = UnionDataDefinition::create($integer, $boolean);
    $string = DataDefinition::create('string');
    $outer = UnionDataDefinition::create($string, $inner);

    // A boolean cannot be the string member, and the union member maps to no
    // structural check, so it is the only candidate.
    $union = $this->createUnion($outer, TRUE);
    $this->assertSame($inner, $union->getResolvedDefinition());
    $nested = $union->getResolvedData();
    $this->assertInstanceOf(Union::class, $nested);
    $this->assertSame($boolean, $nested->getResolvedDefinition());
    $this->assertCount(0, $union->validate());

    // An integer travels through the nested union too.
    $union = $this->createUnion($outer, 42);
    $this->assertSame($inner, $union->getResolvedDefinition());
    $this->assertSame($integer, $union->getResolvedData()->getResolvedDefinition());

    // A string is a candidate for both members, and the probe picks the first.
    $union = $this->createUnion($outer, 'hello');
    $this->assertSame($string, $union->getResolvedDefinition());

    // A float matches neither the string member nor anything the inner union
    // offers. The inner union is still the only structural candidate of the
    // outer one, so the outer union takes it and the inner one is the level
    // that reports the mismatch.
    $union = $this->createUnion($outer, 1.5);
    $this->assertSame($inner, $union->getResolvedDefinition());
    $this->assertNull($union->getResolvedData()->getResolvedDefinition());
    $violations = $union->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertSame('The value does not match any allowed type.', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests a union used as the property definition of a map.
   */
  public function testUnionAsMapProperty(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $map = MapDataDefinition::create();
    $map->setPropertyDefinition('value', UnionDataDefinition::create($integer, $string));
    $map->setPropertyDefinition('label', DataDefinition::create('string'));

    $instance = $this->typedDataManager->create($map, ['value' => 'text', 'label' => 'A label']);
    $property = $instance->get('value');
    $this->assertInstanceOf(Union::class, $property);
    $this->assertSame($string, $property->getResolvedDefinition());
    $this->assertSame('text', $property->getValue());
    $this->assertSame('value', $property->getPropertyPath());
    $this->assertCount(0, $instance->validate());

    // Writing a different shape through the map re-resolves the union.
    $instance->set('value', 7);
    $this->assertSame($integer, $instance->get('value')->getResolvedDefinition());
    $this->assertSame(['value' => 7, 'label' => 'A label'], $instance->getValue());

    // A value matching no member is reported at the path of the property.
    $instance->set('value', ['not a scalar']);
    $violations = $instance->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertSame('value', $violations->get(0)->getPropertyPath());
    $this->assertSame('The value does not match any allowed type.', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests a union used as the item definition of a list.
   */
  public function testUnionAsListItem(): void {
    $integer = DataDefinition::create('integer');
    $string = DataDefinition::create('string');
    $list = new ListDataDefinition([], UnionDataDefinition::create($integer, $string));

    $instance = $this->typedDataManager->create($list, [5, 'text', 9]);
    $this->assertInstanceOf(ItemList::class, $instance);
    $this->assertCount(3, $instance);
    $this->assertInstanceOf(Union::class, $instance->get(0));
    $this->assertSame($integer, $instance->get(0)->getResolvedDefinition());
    $this->assertSame($string, $instance->get(1)->getResolvedDefinition());
    $this->assertSame($integer, $instance->get(2)->getResolvedDefinition());
    $this->assertSame([5, 'text', 9], $instance->getValue());
    $this->assertCount(0, $instance->validate());

    // An item matching no member is reported at the delta of the item.
    $instance->set(1, ['not a scalar']);
    $violations = $instance->validate();
    $this->assertGreaterThan(0, count($violations));
    $this->assertSame('1', $violations->get(0)->getPropertyPath());
  }

  /**
   * Tests an empty array, which is counted as complex data and not as a list.
   *
   * An empty array is the canonical empty value of a map, so the structural
   * filter matches it against complex data members only. A list member can
   * still take it, but only through the probe.
   */
  public function testEmptyArrayResolution(): void {
    $map = MapDataDefinition::create();
    $list = ListDataDefinition::create('string');

    $map_first = UnionDataDefinition::create($map, $list);
    $this->assertSame($map, $this->createUnion($map_first, [])->getResolvedDefinition());

    // Declared the other way around the answer does not change: the structural
    // filter, not the order, decides here.
    $list_first = UnionDataDefinition::create($list, $map);
    $this->assertSame($map, $this->createUnion($list_first, [])->getResolvedDefinition());

    // Without a complex data member, the probe lets the list take it.
    $list_only = UnionDataDefinition::create($list, DataDefinition::create('string'));
    $this->assertSame($list, $this->createUnion($list_only, [])->getResolvedDefinition());

    // A non-empty list is matched structurally.
    $this->assertSame($list, $this->createUnion($map_first, ['a', 'b'])->getResolvedDefinition());
  }

  /**
   * Tests that a cloned union does not share its delegate with the original.
   */
  public function testCloneKeepsDelegatesApart(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      DataDefinition::create('string'),
    );
    $union = $this->createUnion($definition, 5);
    $this->assertNotNull($union->getResolvedData());

    $clone = clone $union;
    $this->assertNotSame($union->getResolvedData(), $clone->getResolvedData());
    $clone->getResolvedData()->setValue(7);
    $this->assertSame(5, $union->getValue());
    $this->assertSame(7, $clone->getValue());
  }

  /**
   * Tests the string representation of a union.
   */
  public function testGetString(): void {
    $definition = UnionDataDefinition::create(
      DataDefinition::create('integer'),
      DataDefinition::create('string'),
    );
    $this->assertSame('5', $this->createUnion($definition, 5)->getString());
    $this->assertSame('text', $this->createUnion($definition, 'text')->getString());
    $this->assertSame('', $this->createUnion($definition, NULL)->getString());

    // An unresolved scalar is still cast, an unresolved array is not.
    $integers = UnionDataDefinition::create(DataDefinition::create('integer'));
    $this->assertSame('not an integer', $this->createUnion($integers, 'not an integer')->getString());
    $this->assertSame('', $this->createUnion($integers, ['a', 'b'])->getString());
  }

  /**
   * A resolver callback returning the last member of a union.
   *
   * @param mixed $value
   *   The value being resolved.
   * @param \Drupal\Core\TypedData\UnionDataDefinitionInterface $definition
   *   The union definition.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface|null
   *   The last member, or NULL if the union has no member.
   */
  public static function resolveToLastMember($value, UnionDataDefinitionInterface $definition): ?DataDefinitionInterface {
    $members = $definition->getMemberDefinitions();
    return $members === [] ? NULL : end($members);
  }

  /**
   * A resolver callback that always falls through.
   *
   * @param mixed $value
   *   The value being resolved.
   * @param \Drupal\Core\TypedData\UnionDataDefinitionInterface $definition
   *   The union definition.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface|null
   *   Always NULL.
   */
  public static function resolveNothing($value, UnionDataDefinitionInterface $definition): ?DataDefinitionInterface {
    return NULL;
  }

}
