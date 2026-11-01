<?php

declare(strict_types=1);

namespace Drupal\Core\Action;

/**
 * Provides an interface for actions that report how many items they processed.
 *
 * Bulk forms use this after executing the action to report the number of items
 * that were actually processed, which can be lower than the number of items
 * that were selected if the action skipped some of them. Actions that do not
 * implement this interface are assumed to have processed all selected items.
 *
 * @see \Drupal\views\Plugin\views\field\BulkForm::viewsFormSubmit()
 */
interface ProcessedCountActionInterface {

  /**
   * Gets the number of items processed by the last execution.
   *
   * @return int
   *   The number of items processed by the most recent call to
   *   \Drupal\Core\Action\ActionInterface::executeMultiple() or
   *   \Drupal\Core\Action\ActionInterface::execute().
   */
  public function getProcessedCount(): int;

}
