<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Component\DependencyInjection\ExpressionLanguage;

/**
 * Autowire properties.
 */
trait AutowirePropertyTrait {

  /**
   * Autowire properties.
   */
  public function autowireProperties(): void {
    $class = new \ReflectionClass($this);

    foreach ($class->getProperties() as $property) {
      // Only autowire properties that have not been initialized yet and have
      // the AutowireProperty attribute.
      $attr = $property->getAttributes(AutowireProperty::class);
      if ($property->isInitialized($this) || !$attr) {
        continue;
      }
      if (!\property_exists($this, 'container') || !$this->container instanceof ContainerInterface) {
        throw new \RuntimeException(sprintf('Cannot autowire properties of class "%s" as there is no container available.', static::class));
      }
      $args = $attr[0]->getArguments();
      if (count($args) !== 0) {
        if (isset($args['service'])) {
          $service = $args['service'];
        }
        elseif (isset($args['param'])) {
          if (!$this->container->hasParameter($args['param'])) {
            throw new AutowiringFailedException($args['param'], sprintf('Cannot autowire parameter "%s": property "$%s" of class "%s".', $args['param'], $property->getName(), static::class));
          }
          $property->setValue($this, $this->container->getParameter($args['param']));
          continue;
        }
        elseif (isset($args['expression'])) {
          $expressionLanguage = new ExpressionLanguage();
          $eval = $expressionLanguage->evaluate($args['expression'], ['container' => $this->container]);
          $property->setValue($this, $eval);
          continue;
        }
      }
      else {
        // When no arguments in the attribute, try finding out from the property
        // type.
        if (!$property->hasType()) {
          continue;
        }
        $service = ltrim((string) $property->getType(), '?');
      }
      if (!isset($service)) {
        throw new AutowiringFailedException('', sprintf('Cannot find a service definition for property "$%s" of class "%s".', $property->getName(), static::class));
      }
      if (!$this->container->has($service)) {
        throw new AutowiringFailedException($service, sprintf('Cannot autowire service "%s": property "$%s" of class "%s".', $service, $property->getName(), static::class));
      }
      // Set the property to the service from the container.
      $property->setValue($this, $this->container->get($service));
    }
  }

}
