<?php

declare(strict_types=1);

namespace Drupal\treegrid_test\Plugin\TreegridColumn;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\treegrid\Attribute\TreegridColumn;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnBase;

/**
 * A test column, sorting after TestColumnA.
 */
#[TreegridColumn(
  id: 'treegrid_test_b',
  title: new TranslatableMarkup('Test B'),
  weight: 10,
)]
final class TestColumnB extends TreegridColumnBase {

  /**
   * {@inheritdoc}
   */
  public function getCellData(object $context): array {
    return ['type' => 'markup', 'html' => 'B'];
  }

}
