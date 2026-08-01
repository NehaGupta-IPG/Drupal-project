<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Confirms that Claro renders a #type 'item' title without a label element.
 */
#[Group('Theme')]
#[RunTestsInSeparateProcesses]
class ClaroItemLabelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Confirms a #type 'item' renders its title in a span, not a label.
   */
  public function testItemLabel(): void {
    // Enable the Claro theme.
    \Drupal::service('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('default', 'claro')->save();

    $form = [
      '#parents' => ['parent'],
      '#array_parents' => [],
      '#tree' => TRUE,
    ];
    $form['item'] = [
      '#type' => 'item',
      '#title' => 'Item title',
      '#markup' => 'Item markup.',
    ];

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_state = new FormState();
    $form_builder->doBuildForm('form', $form, $form_state);
    $this->render($form);

    // An item's #id is on the wrapping div, which is not a labelable element.
    // The wrapper has a '-wrapper' id and references the title through
    // aria-describedby, which points to a span with a '-label' id.
    $wrappers = $this->cssSelect('div[aria-describedby]');
    $this->assertCount(1, $wrappers);
    $wrapper = $wrappers[0];
    $this->assertStringEndsWith('-wrapper', (string) $wrapper['id']);
    $label_id = (string) $wrapper['aria-describedby'];
    $this->assertStringEndsWith('-label', $label_id);
    $this->assertCount(0, $wrapper->xpath('.//label'));
    $spans = $wrapper->xpath('.//span[@id="' . $label_id . '"]');
    $this->assertCount(1, $spans);
    $this->assertStringContainsString('form-item__label', (string) $spans[0]['class']);
    $this->assertSame('Item title', trim((string) $spans[0]));
  }

}
