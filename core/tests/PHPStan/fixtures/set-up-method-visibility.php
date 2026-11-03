<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\PHPStan\Fixtures;

use PHPUnit\Framework\TestCase;

class PublicSetUpTest extends TestCase {

  public function setUp(): void {
  }

}

class ProtectedSetUpTest extends TestCase {

  protected function setUp(): void {
  }

}

class NonTestClass {

  public function setUp(): void {
  }

}
