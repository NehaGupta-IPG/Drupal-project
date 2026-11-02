<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Compiler;

use Drupal\Core\Discovery\AttributeScanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Compiler pass for console commands registered via #[AsCommand] attributes.
 *
 * @see \Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass
 *
 * @internal
 */
class ConsoleCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // Get all classes with the #[AsCommand] attribute.
    $scanner = new AttributeScanner($container->getParameter('container.namespaces'));
    foreach ($scanner->scan(AsCommand::class, 'Command') as $reflection) {
      $className = $reflection->getName();
      // Don't create a service definition if this class is already a service.
      if ($container->hasDefinition($className) || $container->hasAlias($className)) {
        continue;
      }

      // Check the full class hierarchy exists, in case the discovered class
      // extends class of optional dependencies, like Drush or Drupal Console.
      $reflection = new \ReflectionClass($className);
      while ($parent = $reflection->getParentClass()) {
        if (!class_exists($parent->getName())) {
          continue 2;
        }
        $reflection = $parent;
      }

      $definition = new Definition($className);
      $definition
        ->setAutowired(TRUE)
        ->setPublic(TRUE)
        ->addTag('console.command');

      $container->setDefinition($className, $definition);
    }
  }

}
