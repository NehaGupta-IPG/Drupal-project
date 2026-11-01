<?php

declare(strict_types=1);

namespace Drupal\action_bulk_test\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\ProcessedCountActionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test action that skips items with the title "Skip" and reports the rest.
 */
#[Action(
  id: 'test_processed_count_action',
  label: new TranslatableMarkup('Test processed count action'),
  type: 'node',
)]
class TestProcessedCountAction extends ActionBase implements ProcessedCountActionInterface {

  /**
   * The number of items processed.
   */
  protected int $processedCount = 0;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $objects): void {
    $this->processedCount = 0;
    parent::executeMultiple($objects);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    if ($object->label() !== 'Skip') {
      $this->processedCount++;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedCount(): int {
    return $this->processedCount;
  }

}
