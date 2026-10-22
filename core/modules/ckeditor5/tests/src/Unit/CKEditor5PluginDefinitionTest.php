<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\Unit;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\ckeditor5\Plugin\CKEditor5PluginInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition.
 *
 * @internal
 */
#[CoversClass(CKEditor5PluginDefinition::class)]
#[Group('ckeditor5')]
class CKEditor5PluginDefinitionTest extends UnitTestCase {

  /**
   * Tests invalid class values in definitions passed directly to validation.
   */
  #[DataProvider('providerInvalidClasses')]
  public function testInvalidClass(array $class_definition, string $message): void {
    $definition = [
      'id' => 'test_plugin',
      'drupal' => $class_definition + [
        'label' => 'Test plugin',
        'elements' => FALSE,
        'conditions' => FALSE,
        'admin_library' => FALSE,
      ],
    ];
    $plugin_definition = new CKEditor5PluginDefinition($definition);
    $this->expectException(InvalidPluginDefinitionException::class);
    $this->expectExceptionMessageIs($message);
    $plugin_definition->validateDrupalAspects('test_plugin', $definition);
  }

  /**
   * Provides invalid class values and their expected validation messages.
   */
  public static function providerInvalidClasses(): array {
    $missing_class_message = 'The CKEditor 5 "test_plugin" provides a plugin class: "", but it does not exist.';
    return [
      'missing class' => [[], $missing_class_message],
      'NULL class' => [['class' => NULL], $missing_class_message],
      'empty class' => [['class' => ''], $missing_class_message],
      'unknown class' => [
        ['class' => 'UnknownCKEditor5Plugin'],
        'The CKEditor 5 "test_plugin" provides a plugin class: "UnknownCKEditor5Plugin", but it does not exist.',
      ],
      'interface instead of class' => [
        ['class' => CKEditor5PluginInterface::class],
        'The CKEditor 5 "test_plugin" provides a plugin class: "Drupal\ckeditor5\Plugin\CKEditor5PluginInterface", but it does not exist.',
      ],
      'wrong interface' => [
        ['class' => \stdClass::class],
        'CKEditor 5 plugins must implement \Drupal\ckeditor5\Plugin\CKEditor5PluginInterface. "test_plugin" does not.',
      ],
    ];
  }

}
