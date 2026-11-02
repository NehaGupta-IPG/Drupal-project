<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Annotation;

use Drupal\Core\Annotation\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinition as PluginContextDefinition;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Annotation\ContextDefinition.
 *
 * @internal
 */
#[CoversClass(ContextDefinition::class)]
#[Group('Plugin')]
class ContextDefinitionTest extends UnitTestCase {

  /**
   * Tests default and explicitly specified context definition classes.
   */
  #[DataProvider('providerValidClasses')]
  public function testValidClass(array $class_definition, string $expected_class): void {
    $definition = (new ContextDefinition(['value' => 'string'] + $class_definition))->get();
    $this->assertInstanceOf($expected_class, $definition);
    $this->assertSame('string', $definition->getDataType());
  }

  /**
   * Provides default and valid explicit context definition classes.
   */
  public static function providerValidClasses(): array {
    return [
      'default class' => [[], PluginContextDefinition::class],
      'NULL uses default' => [['class' => NULL], PluginContextDefinition::class],
      'explicit class' => [['class' => PluginContextDefinition::class], PluginContextDefinition::class],
      'inherited interface' => [['class' => InheritedContextDefinition::class], InheritedContextDefinition::class],
    ];
  }

  /**
   * Tests that invalid classes produce the context validation exception.
   */
  #[DataProvider('providerInvalidClasses')]
  public function testInvalidClass(mixed $class): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessageIs('ContextDefinition class must implement \Drupal\Core\Plugin\Context\ContextDefinitionInterface.');
    new ContextDefinition(['value' => 'string', 'class' => $class]);
  }

  /**
   * Provides invalid context definition classes.
   */
  public static function providerInvalidClasses(): array {
    return [
      'unknown class' => ['UnknownContextDefinition'],
      'empty class' => [''],
      'wrong interface' => [\stdClass::class],
      'interface instead of class' => [ContextDefinitionInterface::class],
      'array instead of class name' => [[]],
      'integer instead of class name' => [42],
    ];
  }

}

/**
 * Defines a context class inheriting the context definition interface.
 */
class InheritedContextDefinition extends PluginContextDefinition {}
