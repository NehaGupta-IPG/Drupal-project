<?php

declare(strict_types=1);

namespace Drupal\views;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Provides reusable code to be shared by Views argument validation.
 *
 * Because invalid arguments can lead to either 403 or 404 responses, but both
 * need to be enforced in different places (access check and route enhancer), we
 * try to keep the shared code running the checks here.
 */
abstract class ValidArgumentBase {

  /**
   * The loaded view executables, keyed by view ID and display ID.
   *
   * @var array<string, array<string, \Drupal\views\ViewExecutable>>
   */
  protected array $viewExecutables;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ViewExecutableFactory $executableFactory,
  ) {}

  /**
   * Checks whether a NULL argument will return a 403 or 404 response.
   *
   * @param array $options
   *   The argument handler options.
   *
   * @return bool
   *   Whether a NULL argument will return a 403 or 404 response.
   */
  abstract public static function requiresValue(array $options): bool;

  /**
   * Checks whether an invalid argument will return a 403 or 404 response.
   *
   * @param array $options
   *   The argument handler options.
   *
   * @return bool
   *   Whether an invalid argument will return a 403 or 404 response.
   */
  abstract public static function requiresValidation(array $options): bool;

  /**
   * Checks whether a views argument value is valid.
   *
   * @param \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument
   *   The views argument plugin.
   * @param mixed $value
   *   The views argument value.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheable metadata to add to.
   *
   * @return bool
   *   Whether the views argument value is valid.
   */
  protected function argumentValueIsValid(ArgumentPluginBase $argument, mixed $value, RefinableCacheableDependencyInterface $cacheability): bool {
    $argument_options = $argument->options ?? [];
    $requires_value = static::requiresValue($argument_options);
    $requires_validation = static::requiresValidation($argument_options);
    if (!$requires_value && !$requires_validation) {
      return TRUE;
    }

    if ($requires_value && $value === NULL) {
      return FALSE;
    }

    assert($argument instanceof ArgumentPluginBase);
    if ($argument->broken() || $argument->isException($value)) {
      return TRUE;
    }

    if ($requires_validation && !$argument->getPlugin('argument_validator')->validateArgument($value, $cacheability)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Loads a view executable with a display.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view executable with the display set.
   */
  protected function loadViewExecutable(string $view_id, string $display_id): ViewExecutable {
    if (!isset($this->viewExecutables[$view_id][$display_id])) {
      $view = $this->entityTypeManager->getStorage('view')->load($view_id);
      assert($view instanceof ViewEntityInterface);
      $executable = $this->executableFactory->get($view);
      assert($executable instanceof ViewExecutable);
      $executable->setDisplay($display_id);
      $this->viewExecutables[$view_id][$display_id] = $executable;
    }
    return $this->viewExecutables[$view_id][$display_id];
  }

}
