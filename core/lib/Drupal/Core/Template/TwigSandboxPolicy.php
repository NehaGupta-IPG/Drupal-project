<?php

namespace Drupal\Core\Template;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\Core\Site\Settings;
use Drupal\Core\Template\Attribute\TwigAllowed;
use Twig\Extra\Html\Cva;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Default sandbox policy for Twig templates.
 *
 * Twig's sandbox extension is usually used to evaluate untrusted code by
 * limiting access to potentially unsafe properties or methods. Since we do not
 * use ViewModels when passing objects to Twig templates, we limit what those
 * objects can do by only loading certain classes, method names, and method
 * names with an allowed prefix. All object properties may be accessed.
 */
class TwigSandboxPolicy implements SecurityPolicyInterface {

  /**
   * An array of allowed methods in the form of methodName => TRUE.
   *
   * @var array
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  protected $allowed_methods = [];

  /**
   * Allowed method prefixes.
   *
   * Any method starting with one of these prefixes will be allowed.
   *
   * @var array
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  protected $allowed_prefixes;

  /**
   * An array of class names for which any method calls are allowed.
   *
   * @var array
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  protected $allowed_classes;

  /**
   * Constructs a new TwigSandboxPolicy object.
   */
  public function __construct() {
    // Allow settings.php to override our default allowed classes, methods, and
    // prefixes.
    $allowed_classes = Settings::get('twig_sandbox_allowed_classes', [
      // Allow any operations on the Attribute object as it is intended to be
      // changed from a Twig template, for example calling addClass().
      'Drupal\Core\Template\Attribute',
      // The CVA class's only public method is meant to be called by templates.
      Cva::class,
    ]);
    // Flip the array so we can check using isset().
    $this->allowed_classes = array_flip($allowed_classes);

    $this->allowed_methods = static::getMethodsAllowedOnAllObjects();

    $this->allowed_prefixes = Settings::get('twig_sandbox_allowed_prefixes', [
      'get',
      'has',
      'is',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function checkSecurity($tags, $filters, $functions): void {}

  /**
   * {@inheritdoc}
   */
  public function checkPropertyAllowed($obj, $property): void {}

  /**
   * {@inheritdoc}
   */
  public function checkMethodAllowed($obj, $method): void {
    foreach ($this->allowed_classes as $class => $key) {
      if ($obj instanceof $class) {
        return;
      }
    }

    // Return quickly for an exact match of the method name.
    if (isset($this->allowed_methods[$method])) {
      $allowed_classes = $this->allowed_methods[$method];
      if (empty($allowed_classes)) {
        return;
      }
      foreach ($this->allowed_methods[$method] as $class) {
        if ($obj instanceof $class) {
          return;
        }
      }
    }
    else {
      foreach ($this->allowed_prefixes as $prefix) {
        if (str_starts_with($method, $prefix)) {
          return;
        }
      }
    }

    // Allow the method if it has a TwigAllowed attribute.
    $reflectionMethod = new \ReflectionMethod($obj, $method);
    if ($reflectionMethod->getAttributes(TwigAllowed::class)) {
      return;
    }

    throw new SecurityError(sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, get_class($obj)));
  }

  /**
   * Gets the list of allowed methods on all objects.
   *
   * @return string[]
   *   The list of allowed methods on all objects.
   */
  public static function getMethodsAllowedOnAllObjects(): array {
    $allowed_methods = [];
    // Align the array, so we can check using isset() during checks.
    foreach (
      Settings::get('twig_sandbox_allowed_methods', [
        // Only allow idempotent methods.
        EntityInterface::class . '::id',
        EntityInterface::class . '::label',
        EntityInterface::class . '::bundle',
        LayoutDefinition::class . '::id',
        '::get',
        '::__toString',
        '::toString',
      ]) as $method
    ) {
      if (!str_contains($method, '::')) {
        @trigger_error('Not specifying a fully-qualified method name to twig_sandbox_allowed_methods is deprecated in drupal:11.0.0 and will throw an error in drupal:12.0.0. See https://www.drupal.org/node/3263019', E_USER_DEPRECATED);
        $method = '::' . $method;
      }
      [$class, $name] = explode('::', $method);
      if (isset($allowed_methods[$name])) {
        $allowed_methods[$name][] = $class;
      }
      elseif ($class) {
        $allowed_methods[$name] = [$class];
      }
      else {
        $allowed_methods[$name] = [];
      }
    }
    return $allowed_methods;
  }

}
