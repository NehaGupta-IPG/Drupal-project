<?php

namespace Drupal\Core\Shutdown;

use Drupal\Core\Utility\Error;

/**
 * Drupal shutdown handler utility class.
 *
 * Wrapper for register_shutdown_function() that catches thrown exceptions to
 * avoid "Exception thrown without a stack frame in Unknown".
 */
class ShutdownHandler {

  /**
   * Instance storage.
   *
   * @var null|self
   */
  protected static ?self $instance = NULL;

  /**
   * Flag marks the shutdown function registered.
   *
   * Exists to avoid multiple callback stack execution after reset.
   *
   * @var bool
   */
  protected bool $isRegistered = FALSE;

  /**
   * Not allow direct object initialization.
   *
   * @param \Drupal\Core\Shutdown\CallbackStack $callbackStack
   *   Callback stack registered for execution on shutdown.
   *   We cannot use drupal_static() here because the static cache is reset
   *   during batch processing, which breaks batch handling.
   */
  protected function __construct(
    protected CallbackStack $callbackStack = new CallbackStack()
  ) {}

  /**
   * Cloning and unserialization are not permitted for ShutdownHandler.
   */
  protected function __clone() {}

  /**
   * Unserialization are not permitted for ShutdownHandler.
   */
  public function __wakeup(): void {
    throw new \RuntimeException("Cannot unserialize ShutdownHandler");
  }

  /**
   * Instance getter.
   */
  public static function getInstance(): self {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Getter for functions registered for execution on shutdown.
   *
   * @return \Drupal\Core\Shutdown\CallbackStack
   *   Array of shutdown functions to be executed.
   */
  final public function get(): CallbackStack {
    return $this->callbackStack;
  }

  /**
   * Registers a function for execution on shutdown.
   *
   * @param callable $callback
   *   The shutdown function to register.
   * @param array $args
   *   Additional arguments to pass to the shutdown function.
   */
  final public function set(callable $callback, ...$args): void {
    $this->register();
    $this->callbackStack[] = ['callback' => $callback, 'arguments' => $args];
  }

  /**
   * Register internal shutdown function.
   *
   * Only register the internal shutdown function once.
   *
   * @return void
   */
  protected function register(): void {
    if (!$this->isRegistered) {
      $this->isRegistered = TRUE;
      register_shutdown_function([$this, 'shutdown']);
    }
  }

  /**
   * Executes registered shutdown functions.
   * @internal
   */
  public function shutdown(): void {
    // Set the CWD to DRUPAL_ROOT as it is not guaranteed to be the same as it
    // was in the normal context of execution.
    chdir(DRUPAL_ROOT);

    try {
      $this->callbackStack->rewind();
      // Do not use foreach() here because it is possible that the callback will
      // add to the $callbacks array via ShutdownHandler::set().
      while ($callback = $this->callbackStack->current()) {
        call_user_func_array($callback['callback'], $callback['arguments']);
        $this->callbackStack->next();
      }
    }
    // Catch \Throwable, which covers both Error and Exception throwables.
    catch (\Throwable $error) {
      Error::shutdownExceptionHandler('Uncaught exception thrown in shutdown function.', $error);
    }
  }

  /**
   * Reset callback stack.
   *
   * @param \Drupal\Core\Shutdown\CallbackStack|null $callbackStack
   *   New callback stack to set.
   *
   * @return \Drupal\Core\Shutdown\CallbackStack
   *   Callback stack before reset.
   */
  final public function reset(?CallbackStack $callbackStack = NULL): CallbackStack {
    $callbacks = $this->callbackStack;
    $this->callbackStack = $callbackStack ?? new CallbackStack();
    return $callbacks;
  }

}
