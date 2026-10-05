<?php

declare(strict_types=1);

namespace Drupal\comment\Plugin\Field\FieldFormatter;

use Drupal\comment\CommentingStatus;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'comment_status' formatter.
 */
#[FieldFormatter(
  id: 'comment_status',
  label: new TranslatableMarkup('Comment status'),
  field_types: [
    'comment',
  ],
)]
class CommentStatusFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Comment fields hold a single item. Accessing delta 0 applies the field
    // default for entities that have no value stored for the field.
    $value = $items->get(0)->status;
    $status = is_numeric($value) ? CommentingStatus::tryFrom((int) $value) : NULL;
    $elements = [
      0 => ['#plain_text' => $status?->label() ?? $this->t('N/A')],
    ];
    return $elements;
  }

}
