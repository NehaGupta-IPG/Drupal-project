<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

/**
 * Trait with helpers for testing block_content bulk operations.
 */
trait BlockContentTestBulkOperationsTrait {

  /**
   * Unpublishes a block using the unpublish action.
   */
  protected function unpublishUsingBulkAction(): void {
    $edit = [];
    $edit['action'] = 'block_content_unpublish_action';
    $edit["block_content_bulk_form[0]"] = TRUE;
    $this->drupalGet('admin/content/block');
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->pageTextContains('Unpublish block content was applied to 1 item.');
  }

  /**
   * Publishes a block using the publish action.
   */
  protected function publishUsingBulkAction(): void {
    $edit = [];
    $edit['action'] = 'block_content_publish_action';
    $edit['block_content_bulk_form[0]'] = TRUE;
    $this->drupalGet('admin/content/block');
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->pageTextContains('Publish block content was applied to 1 item.');
  }

  /**
   * Asserts the Published table column displays a block as published.
   *
   * @param bool $published
   *   Whether or not the block should display as published.
   */
  protected function assertBlockStatusDisplayedAs(bool $published): void {
    $elements = $this->xpath('//form[@id="views-form-block-content-page-1"]//table/tbody/tr/td');
    $this->assertSame($elements[4]->getText(), $published ? 'Yes' : 'No', 'Block displayed as ' . $published ? 'published' : 'unpublished');
  }

}
