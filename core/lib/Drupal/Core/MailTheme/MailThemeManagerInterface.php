<?php

declare(strict_types=1);

namespace Drupal\Core\MailTheme;

/**
 * Interface for mail theme manager implementations.
 *
 * Provides a simple helper method which switches the theme to a mail theme.
 * Doing so ensures that mails are always using the same templates, no matter
 * whether they were sent in response to a user action on the frontend or inside
 * an administrative area or even during a cron run.
 *
 * Use the following pattern when preparing or sending mails:
 *
 * @code
 * $result = $this->mailThemeManager->executeInMailTheme(new MailTemplateId($module, $key), function () {
 *   return $this->renderer->executeInRenderContext(new RenderContext(), function () {
 *     // Do stuff (e.g. replace tokens, render an entity, ...)
 *     return $result;
 *   });
 * });
 * @endcode
 */
interface MailThemeManagerInterface {

  /**
   * Switches to the theme for the given mail template ID and runs a callback.
   *
   * @param \Drupal\Core\MailTheme\MailTemplateId $templateId
   *   The mail template ID used to look up the theme.
   * @param callable(): Result $function
   *   The callback to be executed.
   *
   * @return Result
   *   The result returned by the callback.
   *
   * @template Result
   */
  public function executeInMailTheme(MailTemplateId $templateId, callable $function): mixed;

}
