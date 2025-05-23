<?php
namespace Drupal\Core\Action;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContextProviderPluginInterface;
use Drupal\Core\Executable\ExecutableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an interface for an Action plugin.
 *
 * @todo Decide if these are still necessary.
 *  - Instead of action implementations saving entities, support for marking
 *    required context as to be saved by the execution manager will be added as
 *    part of https://www.drupal.org/node/2347017.
 *  - Actions will receive a data processing API that allows for token
 *    replacements to happen outside of the action plugin implementations,
 *    see https://www.drupal.org/node/2347023.
 *
 * @see \Drupal\Core\Annotation\Action
 * @see \Drupal\Core\Action\ActionManager
 * @see \Drupal\Core\Action\ActionBase
 * @see plugin_api
 */
interface ActionInterface extends ContextProviderPluginInterface, ExecutableInterface, PluginInspectionInterface {

  /**
   * Checks object access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user for which to check access, or NULL to check access
   *   for the current user. Defaults to NULL.
   * @param bool $return_as_object
   *   (optional) Defaults to FALSE.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result. Returns a boolean if $return_as_object is FALSE (this
   *   is the default) and otherwise an AccessResultInterface object.
   *   When a boolean is returned, the result of AccessInterface::isAllowed() is
   *   returned, i.e. TRUE means access is explicitly allowed, FALSE means
   *   access is either explicitly forbidden or "no opinion".
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE);

  /**
   * Checks if the action is executable.
   *
   * @Todo Determine if necessary.
   *
   * @return bool
   *   TRUE if the action is executable, FALSE otherwise.
   */
  public function isExecutable(): bool;

  /**
   * Returns human-readable messages about the result of the action.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The executed actions result message.
   *
   * @throws \BadMethodCallException
   *   Thrown when the action has not been executed yet.
   */
  public function getResultMessage() : TranslatableMarkup;

  /**
   * Returns the status of the action.
   *
   * @return bool
   *   TRUE if the action was successful, FALSE otherwise.
   *
   * @throws \BadMethodCallException
   *   Thrown when the action has not been executed yet.
   */
  public function getResultStatus() : bool;

}
