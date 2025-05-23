<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Context\ContextDefinitionInterface;
use Drupal\Component\Plugin\Context\ContextInterface;
use Symfony\Component\Validator\ConstraintViolationList;

interface ContextProviderPluginInterface {

  /**
   * Sets the value for a provided context.
   *
   * @param string $name
   *   The name of the context in the plugin definition.
   * @param mixed $value
   *   The value to set the context to. The value has to validate against the
   *   provided context definition.
   *
   * @return $this
   *   A context aware plugin object for chaining.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   If the value does not pass validation.
   */
  public function setProvidedContextValue(string $name, mixed $value): self;

  /**
   * Gets a defined provided context.
   *
   * @param string $name
   *   The name of the provided context in the plugin definition.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface
   *   The context object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the requested provided context is not set.
   */
  public function getProvidedContext(string $name): ContextInterface;

  /**
   * Gets the contexts provided by the plugin.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   The array of context definitions, keyed by context name.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   If contexts are defined but not set.
   */
  public function getProvidedContexts(): array;


  /**
   * Gets the values for all provided contexts.
   *
   * @return array
   *   An array of provided context values, keyed by context name. If a context
   *   is unset its value is returned as NULL.
   */
  public function getProvidedContextValues(): array;

  /**
   * Gets a specific provided context definition of the plugin.
   *
   * @param string $name
   *   The name of the provided context in the plugin definition.
   *
   * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface
   *   The definition against which the context value must validate.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the requested provided context is not defined.
   */
  public function getProvidedContextDefinition(string $name): ContextDefinitionInterface;

  /**
   * Gets the provided context definitions of the plugin.
   *
   * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface[]
   *   The array of provided context definitions, keyed by context name.
   */
  public function getProvidedContextDefiitions(): array;

  /**
   * Validates the provided contexts.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationList
   *   A constraint violations list object.
   */
  public function validateProvidedContexts(): ConstraintViolationList;

}
