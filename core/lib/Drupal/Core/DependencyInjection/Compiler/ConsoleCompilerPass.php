<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Compiler;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
    foreach ($this->getClasses($container->getParameter('container.namespaces')) as $className) {
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

      // Forward the parts of the #[AsCommand] attribute that
      // \Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass
      // does not read from the attribute itself. It falls back to the
      // attribute for the command name, aliases and description, but only
      // honors the help text and usage examples when they are supplied as
      // service tag attributes. For invokable commands - which are wrapped in
      // a plain Command via setCode() rather than constructed from their own
      // class - this means attribute help and usages are otherwise silently
      // dropped. Command subclasses apply these from the attribute themselves
      // in Command::__construct(), so they must not be forwarded here too or
      // they would be added twice.
      $tag = [];
      if (!is_subclass_of($className, Command::class)) {
        $attribute = (new \ReflectionClass($className))->getAttributes(AsCommand::class)[0]->newInstance();
        if ($attribute->help !== NULL) {
          $tag['help'] = $attribute->help;
        }
        if ($attribute->usages) {
          $tag['usages'] = $attribute->usages;
        }
      }

      $definition = new Definition($className);
      $definition
        ->setAutowired(TRUE)
        ->setPublic(TRUE)
        ->setTags(['console.command' => [$tag]]);

      $container->setDefinition($className, $definition);
    }
  }

  /**
   * Gets command classes for the provided namespaces.
   *
   * @param array<class-string, string> $namespaces
   *   An array of namespaces with keys as class strings and values as
   *   paths.
   *
   * @return \Generator<class-string>
   *   Generates class strings.
   *
   * @throws \ReflectionException
   */
  protected function getClasses(array $namespaces): \Generator {
    foreach ($namespaces as $namespace => $dirs) {
      $dirs = (array) $dirs;
      foreach ($dirs as $dir) {
        $dir .= '/Command';
        if (!file_exists($dir)) {
          continue;
        }
        $namespace .= '\\Command';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        /** @var \SplFileInfo $fileinfo */
        foreach ($iterator as $fileinfo) {
          if ($fileinfo->getExtension() !== 'php') {
            continue;
          }

          /** @var \RecursiveDirectoryIterator|null $subDir */
          $subDir = $iterator->getSubIterator();
          if (NULL === $subDir) {
            continue;
          }

          $subDir = $subDir->getSubPath();
          $subDir = $subDir !== '' ? str_replace(DIRECTORY_SEPARATOR, '\\', $subDir) . '\\' : '';
          /** @var class-string $class */
          $class = $namespace . '\\' . $subDir . $fileinfo->getBasename('.php');

          try {
            $reflectionClass = new \ReflectionClass($class);
          }
          catch (\Error) {
            // Skip commands where the hierarchy is unresolvable due to
            // optional dependencies.
            continue;
          }

          if (count($reflectionClass->getAttributes(AsCommand::class)) > 0) {
            yield $class;
          }
        }
      }
    }
  }

}
