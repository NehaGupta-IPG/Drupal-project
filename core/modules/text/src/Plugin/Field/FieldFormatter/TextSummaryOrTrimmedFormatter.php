<?php

namespace Drupal\text\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'text_summary_or_trimmed' formatter.
 *
 * @deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Download
 *   the contrib module text_with_summary.
 *
 * @see https://www.drupal.org/node/3568381
 */
#[FieldFormatter(
  id: 'text_summary_or_trimmed',
  label: new TranslatableMarkup('Summary or trimmed'),
  field_types: [
    'text_with_summary',
  ],
)]
class TextSummaryOrTrimmedFormatter extends TextTrimmedFormatter {}
