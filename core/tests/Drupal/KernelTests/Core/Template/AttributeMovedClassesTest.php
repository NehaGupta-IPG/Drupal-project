<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Template;

use Drupal\Component\Utility\HtmlAttribute\AttributeArray;
use Drupal\Component\Utility\HtmlAttribute\AttributeBoolean;
use Drupal\Component\Utility\HtmlAttribute\AttributeString;
use Drupal\Component\Utility\HtmlAttribute\AttributeValueBase;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the deprecated names of the classes moved to HtmlAttribute*.
 */
#[Group('Template')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class AttributeMovedClassesTest extends KernelTestBase {

  /**
   * Tests the deprecated names of the Attribute* classes.
   *
   * @param string $old_class
   *   The deprecated class name.
   * @param class-string $new_class
   *   The class that replaces it.
   */
  #[DataProvider('providerMovedClasses')]
  public function testMovedClasses(string $old_class, string $new_class): void {
    $deprecation_message = "Class $old_class is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0, use $new_class instead. See https://www.drupal.org/node/3070485";
    $this->expectUserDeprecationMessage($deprecation_message);
    $this->assertTrue(class_exists($old_class));
    // The deprecated name is an alias, so reflection reports the new name.
    $this->assertSame($new_class, (new \ReflectionClass($old_class))->getName());
  }

  /**
   * Data provider for testMovedClasses().
   *
   * @return array<string, array{string, class-string}>
   *   The test cases.
   */
  public static function providerMovedClasses(): array {
    return [
      'AttributeArray' => [
        'Drupal\Core\Template\AttributeArray',
        AttributeArray::class,
      ],
      'AttributeBoolean' => [
        'Drupal\Core\Template\AttributeBoolean',
        AttributeBoolean::class,
      ],
      'AttributeString' => [
        'Drupal\Core\Template\AttributeString',
        AttributeString::class,
      ],
      'AttributeValueBase' => [
        'Drupal\Core\Template\AttributeValueBase',
        AttributeValueBase::class,
      ],
    ];
  }

}
