<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Tests for Label render element and form_element_label theme hook.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormElementLabelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Ensures that attributes can be placed for form element label.
   */
  public function testAttributes(): void {
    $render_array = [
      '#type' => 'label',
      '#attributes' => ['class' => ['kitten']],
      '#title' => 'Kittens',
      '#title_display' => 'above',
    ];
    $css_selector_converter = new CssSelectorConverter();
    $this->render($render_array);
    $elements = $this->getSimpleXmlElementsByXpath($css_selector_converter->toXPath('.kitten'));
    $this->assertCount(1, $elements);

    // Add label attributes to a form element.
    $render_array = [
      '#type' => 'textfield',
      '#label_attributes' => ['class' => ['meow']],
      '#title' => 'Kitten sounds',
    ];
    $this->render($render_array);
    $elements = $this->getSimpleXmlElementsByXpath($css_selector_converter->toXPath('label.meow'));
    $this->assertCount(1, $elements);
  }

  /**
   * Ensures that " (required)" is added to required Label element.
   */
  public function testRequiredFieldIndicator(): void {
    $render_array = [
      '#type' => 'label',
      '#title' => 'Label for required',
      '#required' => TRUE,
      '#title_display' => 'above',
    ];
    $this->render($render_array);
    $elements = $this->xpath('//label[@class="js-form-required form-required"]/span[@aria-hidden="true" and @class="hidden" and @data-drupal-required and contains(text(), " (required)")]');
    $this->assertCount(1, $elements);
  }

}
