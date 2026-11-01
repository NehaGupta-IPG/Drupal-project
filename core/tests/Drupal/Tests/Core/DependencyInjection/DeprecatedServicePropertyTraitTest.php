<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Tests\Core\DependencyInjection\Fixture\DeprecatedServicePropertyClass;
use Drupal\Tests\Core\DependencyInjection\Fixture\DeprecatedServicePropertyCustomMessageClass;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait
 * @group DependencyInjection
 */
class DeprecatedServicePropertyTraitTest extends UnitTestCase {

  /**
   * @group legacy
   */
  public function testDeprecatedServicePropertyTrait(): void {
    $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
    $container->expects($this->any())
      ->method('get')
      ->with('bar')
      ->willReturn($this);
    \Drupal::setContainer($container);

    $next_major_version = intval(ceil((float) \Drupal::VERSION)) . '.0.0';
    $this->expectDeprecation("The property foo (bar service) is deprecated in Drupal\Tests\Core\DependencyInjection\Fixture\DeprecatedServicePropertyClass and will be removed before Drupal $next_major_version.");
    $class = new DeprecatedServicePropertyClass();
    $this->assertInstanceOf(static::class, $class->foo);
  }

  /**
   * @group legacy
   */
  public function testDeprecatedServicePropertyTraitWithCustomMessage(): void {
    $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');
    $container->expects($this->any())
      ->method('get')
      ->with('bar')
      ->willReturn($this);
    \Drupal::setContainer($container);

    $this->expectDeprecation("Custom message");
    $class = new DeprecatedServicePropertyCustomMessageClass();
    $this->assertInstanceOf(static::class, $class->foo);
  }

  public function testDeprecatedServicePropertyTraitException(): void {
    $class = new class() {
      use DeprecatedServicePropertyTrait;

      /**
       * Deprecated service properties.
       *
       * @var string[]
       */
      protected $deprecatedProperties = [
        'foo' => ['bar'],
      ];
    };

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('The deprecatedProperties property must be a string or an array with service and message keys to use this trait.');
    $this->assertInstanceOf(static::class, $class->foo);
  }

}
