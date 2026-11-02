<?php

declare(strict_types=1);

namespace Drupal\comment\Plugin\views\field;

use Drupal\comment\CommentingStatus;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display the status of a comment field.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField('comment_status')]
class CommentStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    // An unexpected stored value is shown as is, escaped by the base class.
    $status = is_numeric($value) ? CommentingStatus::tryFrom((int) $value) : NULL;
    return $this->sanitizeValue($status?->label() ?? $value);
  }

}
