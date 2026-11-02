<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Attribute used to identify a class property as a service closure.
 *
 * Example usage:
 * @code
 * use Drupal\Core\DependencyInjection\Attribute\ServiceClosure;
 *
 * class ExampleForm extends FormBase {
 *
 *   public function __construct(
 *     #[ServiceClosure('example.service')]
 *     protected \Closure $exampleServiceClosure,
 *   ) {}
 *
 *   public static function create(ContainerInterface $container): static {
 *     return new static(
 *       static fn(): ExampleServiceInterface => $container->get('example.service'),
 *     );
 *   }
 *   ...
 * }
 * @endcode
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class ServiceClosure extends AutowireServiceClosure {}
