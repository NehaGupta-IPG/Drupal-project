<?php

declare(strict_types=1);

namespace Drupal\treegrid_test\Plugin\TreegridColumn;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\treegrid\Attribute\TreegridColumn;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnBase;

/**
 * A test column, sorting ahead of TestColumnB.
 */
#[TreegridColumn(
  id: 'treegrid_test_a',
  title: new TranslatableMarkup('Test A'),
  weight: 5,
)]
final class TestColumnA extends TreegridColumnBase {

  /**
   * {@inheritdoc}
   */
  public function getCellData(object $context): array {
    return ['type' => 'markup', 'html' => 'A'];
  }

}
