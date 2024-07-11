<?php

declare(strict_types=1);

namespace Drupal\Tests\Core;

use Drupal\Core\Result;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Result type.
 *
 * @coversDefaultClass Drupal\Core\Result
 * @group ResultTest
 */
class ResultTest extends UnitTestCase {

  /**
   * Tests that a result of type Ok is of type Ok.
   */
  public function testThatOkResultIsOk(): void {
    $this->assertTrue(Result::ok("Foo")->isOk());
  }

  /**
   * Tests that a result of type Ok is not of type Error.
   */
  public function testThatOkResultIsNotError(): void {
    $this->assertFalse(Result::ok("Foo")->isError());
  }

  /**
   * Tests that a result of type Ok containing an int returns an int.
   */
  public function testThatOkResultOfTypeIntReturnsInt(): void {
    $this->assertEquals(Result::ok(5)->getValue(), 5);
  }

  /**
   * Tests that a result of type Error is not of type Ok.
   */
  public function testThatErrorResultIsNotOk(): void {
    $this->assertFalse(Result::error("Foo")->isOk());
  }

  /**
   * Tests that a result of type Error is of type Error.
   */
  public function testThatErrorResultIsError(): void {
    $this->assertTrue(Result::error("Foo")->isError());
  }

  /**
   * Tests that a result containing a complex object returns the object.
   */
  public function testThatErrorResultWithComplexObjectReturnsComplexObject(): void {
    $complexObject = new \stdClass();
    $this->assertEquals(Result::error($complexObject)->getValue(), $complexObject);
  }

}
