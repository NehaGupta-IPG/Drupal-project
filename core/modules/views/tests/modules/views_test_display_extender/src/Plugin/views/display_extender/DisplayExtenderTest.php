<?php

declare(strict_types=1);

namespace Drupal\views_test_display_extender\Plugin\views\display_extender;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsDisplayExtender;
use Drupal\views_test_data\Plugin\views\display_extender\DisplayExtenderTest as DisplayExtenderTestBase;

/**
 * Defines display extender test plugin.
 */
#[ViewsDisplayExtender(
    id: 'views_test_display_extender_display_extender_test',
    title: new TranslatableMarkup('Display extender in views_test_display_extender'),
)]
class DisplayExtenderTest extends DisplayExtenderTestBase {

}
