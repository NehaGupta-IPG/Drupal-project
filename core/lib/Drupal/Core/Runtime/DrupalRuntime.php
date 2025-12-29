<?php

declare(strict_types=1);

namespace Drupal\Core\Runtime;

use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Runtime\GenericRuntime;
use Symfony\Component\Runtime\Runner\Symfony\ConsoleApplicationRunner;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * The custom Drupal framework runtime.
 *
 * This runtime understands how to provide the arguments that Drupal
 * front-controllers need to instantiate the DrupalKernel.
 */
class DrupalRuntime extends GenericRuntime {

  /**
   * The input to use in this application's invocation.
   */
  private ArgvInput $input;

  /**
   * The output to use in this application's invocation.
   */
  private ConsoleOutput $output;

  /**
   * {@inheritdoc}
   */
  public function getRunner(?object $application): RunnerInterface {
    if ($application instanceof DrupalKernelInterface) {
      return new DrupalKernelRunner($application, Request::createFromGlobals());
    }

    if ($application instanceof Application) {
      set_time_limit(0);
      $defaultEnv = !isset($this->options['env']) ? ($_SERVER[$this->options['env_var_name']] ?? 'dev') : NULL;
      $output = $this->output ??= new ConsoleOutput();

      return new ConsoleApplicationRunner($application, $defaultEnv, $this->getInput(), $output);
    }

    return parent::getRunner($application);
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
      return $_ENV[$this->options['env_var_name']];
    }

    return parent::getArgument($parameter, $type);
  }

  /**
   * Get the input handler to use in console applications.
   *
   * The input that should be used should be the same across the entire
   * application since it ties to the console that the user is using.
   *
   * @return \Symfony\Component\Console\Input\ArgvInput
   *   The currently configured input for this runtime.
   */
  private function getInput(): ArgvInput {
    if (isset($this->input)) {
      return $this->input;
    }

    $input = new ArgvInput();

    if (isset($this->options['env'])) {
      return $this->input = $input;
    }

    if (NULL !== $env = $input->getParameterOption(['--env', '-e'], NULL, TRUE)) {
      putenv($this->options['env_var_name'] . '=' . $_SERVER[$this->options['env_var_name']] = $_ENV[$this->options['env_var_name']] = $env);
    }

    if ($input->hasParameterOption('--no-debug', TRUE)) {
      putenv($this->options['debug_var_name'] . '=' . $_SERVER[$this->options['debug_var_name']] = $_ENV[$this->options['debug_var_name']] = '0');
    }

    return $this->input = $input;
  }

}
