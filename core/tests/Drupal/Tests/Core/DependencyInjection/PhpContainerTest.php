<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * Tests the PhpContainer base class for dumped containers.
 */
#[CoversClass(PhpContainer::class)]
#[Group('DependencyInjection')]
class PhpContainerTest extends UnitTestCase {

  /**
   * Tests that reset() keeps synthetic services and the request stack.
   */
  public function testReset(): void {
    $builder = new ContainerBuilder();
    $builder->register('kernel')->setSynthetic(TRUE)->setPublic(TRUE);
    $builder->register('request_stack', \stdClass::class)->setPublic(TRUE);
    $builder->register('regular', \stdClass::class)->setPublic(TRUE);
    $builder->compile();

    $class = 'PhpContainerTestContainer' . bin2hex(random_bytes(8));
    $code = (new PhpDumper($builder))->dump([
      'class' => $class,
      'base_class' => '\\' . PhpContainer::class,
    ]);
    $file = tempnam(FileSystem::getOsTemporaryDirectory(), 'drupal_php_container_test_');
    file_put_contents($file, $code);
    include $file;
    unlink($file);

    /** @var \Drupal\Core\DependencyInjection\PhpContainer $container */
    $container = new $class();
    $kernel = new \stdClass();
    $container->set('kernel', $kernel);
    $request_stack = $container->get('request_stack');
    $regular = $container->get('regular');

    $container->reset();

    $this->assertSame($kernel, $container->get('kernel'));
    $this->assertSame($request_stack, $container->get('request_stack'));
    $this->assertFalse($container->initialized('regular'));
    $this->assertNotSame($regular, $container->get('regular'));
  }

}
