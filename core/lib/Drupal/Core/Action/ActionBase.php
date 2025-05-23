<?php

namespace Drupal\Core\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Executable\ExecutablePluginBase;
use Drupal\Core\Plugin\ContextProviderPluginTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a base implementation for an Action plugin.
 *
 * @see \Drupal\Core\Annotation\Action
 * @see \Drupal\Core\Action\ActionManager
 * @see \Drupal\Core\Action\ActionInterface
 * @see plugin_api
 */
abstract class ActionBase extends ExecutablePluginBase implements ActionInterface {

  use ContextProviderPluginTrait;

  protected ?ActionResult $result;

  /**
   * {@inheritdoc}
   */
  public function execute() : void {
    $this->result = $this->doExecute($this->getContextValues());
    // If the action provides context values, we need to set the values.
    if ($provided_definitions = $this->getProvidedContextDefiitions()) {
      $this->providedContext = [];
      foreach ($this->result->getContextValues() as $context_name => $value) {
        if (isset($provided_definitions[$context_name])) {
          $this->setProvidedContextValue($context_name, $value);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isExecutable() : bool {
    return $this->validateContexts()->count() === 0;
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getResultMessage() : TranslatableMarkup {
    if ($this->result === NULL) {
      throw new \BadMethodCallException('The action message cannot be retrieved until the action ::execute() method has been called.');
    }
    return $this->result->getMessage();
  }

  /**
   * {@inheritdoc}
   */
  public function getResultStatus() : bool {
    if ($this->result === NULL) {
      throw new \BadMethodCallException('The action status cannot be retrieved until the action ::execute() method has been called.');
    }
    return $this->result->isSuccess();
  }

  /**
   * Executes the action.
   *
   * @param array $values
   *   An array of context values.
   *
   * @return \Drupal\Core\Action\ActionResult
   */
  abstract protected function doExecute(array $values) : ActionResult;

}
