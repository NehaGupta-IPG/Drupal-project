<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\Compiler\RuntimeParametersPass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * Tests the RuntimeParametersPass.
 */
#[CoversClass(RuntimeParametersPass::class)]
#[Group('DependencyInjection')]
class RuntimeParametersPassTest extends UnitTestCase {

  /**
   * Tests that only exact runtime parameter values are replaced.
   */
  public function testProcess(): void {
    $container = new ContainerBuilder();
    $container->setParameter('app.root', '/var/www/drupal');
    $container->setParameter('site.path', '');
    $container->addCompilerPass(new RuntimeParametersPass(['app.root', 'site.path', 'missing']), PassConfig::TYPE_AFTER_REMOVING, -1000);
    $container->register('service', \ArrayObject::class)
      ->setPublic(TRUE)
      ->addArgument([
        'root' => '%app.root%',
        // A value that only contains a runtime parameter value is kept.
        'path' => '%app.root%/core',
        // An empty runtime parameter value is never replaced.
        'site' => '%site.path%',
      ]);
    $container->compile();

    $this->assertEquals([[
      'root' => new Parameter('app.root'),
      'path' => '/var/www/drupal/core',
      'site' => '',
    ],
    ], $container->getDefinition('service')->getArguments());

    // The container builder resolves the references when it is used directly.
    $this->assertSame('/var/www/drupal', $container->get('service')['root']);
  }

}
