<?php

namespace Drupal\text_with_summary\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;

/**
 * Plugin implementation of the 'text_summary_or_trimmed' formatter.
 */
#[FieldFormatter(
  id: 'text_summary_or_trimmed',
  label: new TranslatableMarkup('Summary or trimmed'),
  field_types: [
    'text_with_summary',
  ],
)]
class TextSummaryOrTrimmedFormatter extends TextTrimmedFormatter {}
