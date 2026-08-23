<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Discovery;

use Drupal\Core\Discovery\AttributeScanner;
use Drupal\router_test\Controller\TestAttributes;
use Drupal\router_test\Controller\TestClassAttribute;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tests \Drupal\Core\Discovery\AttributeScanner.
 */
#[CoversClass(AttributeScanner::class)]
#[Group('Discovery')]
class AttributeScannerTest extends UnitTestCase {

  /**
   * Scans the router_test fixtures and returns the discovered class names.
   *
   * @return list<string>
   *   The names of the classes yielded by the scanner.
   */
  protected function scanFixtures(): array {
    $namespaces = new \ArrayObject([
      'Drupal\router_test' => $this->root . '/core/modules/system/tests/modules/router_test_directory/src',
    ]);
    $scanner = new AttributeScanner($namespaces);
    $classes = [];
    foreach ($scanner->scan(Route::class, 'Controller') as $reflection) {
      $classes[] = $reflection->getName();
    }
    return $classes;
  }

  /**
   * Tests that classes referencing the attribute are discovered.
   */
  public function testScanFindsAttributedClasses(): void {
    $classes = $this->scanFixtures();
    $this->assertContains(TestAttributes::class, $classes);
    $this->assertContains(TestClassAttribute::class, $classes);
  }

  /**
   * Tests that a namespace can have multiple source directories.
   */
  public function testScanSupportsMultipleDirectories(): void {
    $namespaces = new \ArrayObject([
      'Drupal\\router_test' => [
        $this->root . '/core/modules/system/tests/modules/router_test_directory/src',
        $this->root . '/core/modules/system/tests/modules/router_test_directory/nonexistent',
      ],
    ]);
    $scanner = new AttributeScanner($namespaces);
    $classes = [];
    foreach ($scanner->scan(Route::class, 'Controller') as $reflection) {
      $classes[] = $reflection->getName();
    }

    $this->assertContains(TestAttributes::class, $classes);
  }

  /**
   * Tests that an array of namespaces can be scanned.
   */
  public function testScanSupportsNamespaceArrays(): void {
    $scanner = new AttributeScanner([
      'Drupal\\router_test' => $this->root . '/core/modules/system/tests/modules/router_test_directory/src',
    ]);
    $classes = [];
    foreach ($scanner->scan(Route::class, 'Controller') as $reflection) {
      $classes[] = $reflection->getName();
    }

    $this->assertContains(TestAttributes::class, $classes);
  }

  /**
   * Tests that non-instantiable and non-loadable classes are skipped.
   */
  public function testScanSkipsNonLoadableClasses(): void {
    $classes = $this->scanFixtures();

    // A class that cannot be autoloaded because it extends a class from a
    // module that is not installed passes the filter but errors on
    // reflection, so it is skipped.
    $this->assertNotContains('Drupal\router_test\Controller\TestMissingDependency', $classes);

    // An abstract class carrying the attribute is skipped: it can never be
    // instantiated.
    $this->assertNotContains('Drupal\router_test\Controller\TestAbstractController', $classes);

    // A class with no reference to the attribute never passes the filter.
    $this->assertNotContains('Drupal\router_test\Controller\TestInvalidController', $classes);
  }

  /**
   * Tests that every yielded value is an instantiable reflection class.
   */
  public function testScanYieldsConcreteReflections(): void {
    $namespaces = new \ArrayObject([
      'Drupal\router_test' => $this->root . '/core/modules/system/tests/modules/router_test_directory/src',
    ]);
    $scanner = new AttributeScanner($namespaces);
    foreach ($scanner->scan(Route::class, 'Controller') as $reflection) {
      $this->assertInstanceOf(\ReflectionClass::class, $reflection);
      $this->assertFalse($reflection->isAbstract());
    }
  }

}
