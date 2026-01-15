<?php

namespace Drupal\Core\Plugin;

use Drupal\Component\Plugin\Context\ContextDefinitionInterface;
use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Plugin\Context\Context;
use Symfony\Component\Validator\ConstraintViolationList;

trait ContextProviderPluginTrait {

  /**
   * The data objects representing the context of this plugin.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $providedContext = [];

  /**
   * {@inheritdoc}
   */
  public function setProvidedContextValue(string $name, mixed $value): self {
    $context = Context::createFromContext($this->getProvidedContext($name), $value);
    $this->providedContext[$name] = $context;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvidedContexts(): array {
    // Make sure all context objects are initialized.
    foreach ($this->getProvidedContextDefinitions() as $name => $definition) {
      $this->getProvidedContext($name);
    }
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvidedContext(string $name): ContextInterface {
    // Check for a valid context value.
    if (!isset($this->providedContext[$name])) {
      $this->providedContext[$name] = new Context($this->getProvidedContextDefinition($name));
    }
    return $this->providedContext[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getProvidedContextValues(): array {
    $values = [];
    foreach ($this->getProvidedContextDefinitions() as $name => $definition) {
      $values[$name] = isset($this->providedContext[$name]) ? $this->providedContext[$name]->getContextValue() : NULL;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvidedContextDefinition(string $name): ContextDefinitionInterface {
    $definition = $this->getPluginDefinition();
    if (empty($definition['provides'][$name])) {
      throw new ContextException(sprintf("The provided context '%s' is not valid.", $name));
    }
    return $definition['provides'][$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getProvidedContextDefinitions(): array {
    $definition = $this->getPluginDefinition();
    return !empty($definition['provides']) ? $definition['provides'] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateProvidedContexts(): ConstraintViolationList {
    $violations = new ConstraintViolationList();
    // @todo Implement the Symfony Validator component to let the validator
    //   traverse and set property paths accordingly.
    //   See https://www.drupal.org/project/drupal/issues/3153847.
    foreach ($this->getProvidedContexts() as $context) {
      $violations->addAll($context->validate());
    }
    return $violations;
  }

}
