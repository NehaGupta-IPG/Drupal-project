<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Functional;

use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Views UI workflows without JavaScript.
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class NoJsTest extends UITestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var string[]
   */
  public static $testViews = ['test_view_empty'];

  /**
   * Tests configuring multiple fields through standalone forms.
   */
  public function testAddMultipleFields(): void {
    $this->drupalGet('admin/structure/views/view/test_view_empty');
    $this->getSession()->getPage()->clickLink('views-add-field');
    $this->assertSession()->responseContains('/core/modules/views_ui/css/views_ui.admin.theme.css');
    $this->submitForm([
      'name[views_test_data.name]' => TRUE,
      'name[views_test_data.age]' => TRUE,
    ], 'Add and configure fields');

    $labels = ['First no-JS field', 'Second no-JS field'];
    foreach ($labels as $label) {
      $this->submitForm([
        'options[custom_label]' => TRUE,
        'options[label]' => $label,
      ], 'Apply');
    }
    $this->submitForm([], 'Save');

    $fields = View::load('test_view_empty')->getDisplay('default')['display_options']['fields'];
    $this->assertEqualsCanonicalizing(['id', 'name', 'age'], array_keys($fields));
    $this->assertEqualsCanonicalizing($labels, [$fields['name']['label'], $fields['age']['label']]);
  }

}
