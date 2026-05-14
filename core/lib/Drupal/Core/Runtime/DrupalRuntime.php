<?php

declare(strict_types=1);

namespace Drupal\Core\Runtime;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Runtime\Internal\MissingDotenv;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * The custom Drupal framework runtime.
 *
 * This runtime understands how to provide the arguments that Drupal
 * front-controllers need to instantiate the DrupalKernel.
 */
class DrupalRuntime extends SymfonyRuntime {

  /**
   * The project directory containing the vendor/ directory.
   */
  private ?string $projectDir = NULL;

  public function __construct(array $options = []) {
    // Unless explicitly opted-in, we disable Symfony's runtime handler.
    // Drupal has its own error handlers.
    $options['error_handler'] ??= FALSE;
    // We disable dotenv by default. Drupal applications may already have this
    // as dependency in their project for other reasons but may not be expecting
    // Drupal to read this file.
    $options['disable_dotenv'] ??= TRUE;

    // Although accessing the autoloader through globals is discouraged. It is
    // possible at the time of introducing symfony/runtime, so we must keep this
    // available for backwards compatibility.
    $GLOBALS['autoloader'] = new DeprecatedAutoloadAccess();

    // Symfony's default runtime is `dev`, but Drupal expects this to be `prod`
    // if nothing is set so we must overwrite it.
    $envKey = $options['env_var_name'] ??= 'APP_ENV';
    // If people opted into dotenv then this is not explicitly set by Symfony.
    if ($options['disable_dotenv'] || class_exists(MissingDotenv::class, FALSE)) {
      $_SERVER[$envKey] ??= $_ENV[$envKey] ?? 'prod';
    }

    // If the application included the runtime through Symfony's generated
    // autoload_runtime.php then the project_dir option contains the path to the
    // parent of the vendor directory, which is our app root.
    if (isset($options['project_dir'])) {
      $this->projectDir = $options['project_dir'];
    }

    parent::__construct($options);
  }

  /**
   * Resolves an argument requested by the front-controller closure.
   *
   * @param \ReflectionParameter $parameter
   *   The reflection information of the parameter that's requested (such
   *   as the name).
   * @param string|null $type
   *   The parameter's type declaration. NULL if the argument is not strictly
   *   typed.
   *
   * @return mixed
   *   The value that the runtime provides for the requested argument.
   *
   * @throws \InvalidArgumentException
   *   In case the requested parameter and type combination aren't supported by
   *   the runtime and cannot be resolved to a valid value.
   */
  protected function getArgument(\ReflectionParameter $parameter, ?string $type): mixed {
    if ($type === 'string' && $parameter->name === 'environment') {
      // Fetch the environment from the SERVER variables. SymfonyRuntime
      // promotes $_ENV to $_SERVER, making the latter the highest in the chain.
      // Default to prod to match __construct and preserve Drupal's habit of
      // defaulting to production config unless instructed otherwise.
      return $_SERVER[$this->options['env_var_name']] ?? 'prod';
    }

    // In Symfony 8.1 and later SymfonyRuntime::getArgument will call
    // resolveType for us, before that we need to do it ourselves.
    if (!method_exists(SymfonyRuntime::class, 'resolveType')) {
      return $this->resolveType($type) ?? parent::getArgument($parameter, $type);
    }
    return parent::getArgument($parameter, $type);
  }

  /**
   * Resolves for the value of a specific type.
   *
   * Ensures that types that may be provided by getArgument but also used in the
   * runtime can be instantiated only once, without having a lot of individual
   * getter methods or duplicated static var with elvis patterns.
   *
   * @param string|null $type
   *   The type of the variable to retrieve.
   *
   * @return mixed
   *   The value for the resolved type or NULL if the type has no associated
   *   value.
   */
  protected function resolveType(?string $type) {
    return match ($type) {
      ClassLoader::class => require $this->getProjectDir() . "/vendor/autoload.php",
      // Until https://github.com/phpstan/phpstan/issues/9592 is fixed.
      // @phpstan-ignore-next-line staticMethod.notFound
      default => method_exists(SymfonyRuntime::class, 'resolveType') ? parent::resolveType($type) : NULL,
    };
  }

  /**
   * Get the application root.
   *
   * @return string
   *   The application root.
   */
  protected function getProjectDir() : string {
    if (!$this->projectDir) {
      // If the project_dir was not provided in the constructor, we detect it
      // by reflecting Composer's ClassLoader, which is always located at
      // vendor/composer/ClassLoader.php. Two dirname() calls reach vendor/,
      // one more reaches the project root containing vendor/.
      $r = new \ReflectionClass(ClassLoader::class);
      $this->projectDir = \dirname($r->getFileName(), 3);
    }

    return $this->projectDir;
  }

}
