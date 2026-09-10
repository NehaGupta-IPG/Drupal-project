<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for form grouping elements.
 */
#[Group('form')]
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class FormGroupingElementsTest extends WebDriverTestBase {

  /**
   * Required modules.
   *
   * @var array
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * Tests that vertical tab children become visible.
   *
   * Makes sure that a child element of a vertical tab that is not visible,
   * becomes visible when the tab is clicked, a fragment link to the child is
   * clicked or when the URI fragment pointing to that child changes.
   *
   * Repeats the test in
   * core/tests/Drupal/FunctionalJavascriptTests/Core/Form/FormGroupingElementsTest.php
   * to ensure that the vertical tabs theme function doesn't break standard tab
   * functionality.
   */
  public function testVerticalTabChildVisibility(): void {
    $session = $this->getSession();
    $web_assert = $this->assertSession();

    // Request the group vertical tabs testing page with a fragment identifier
    // to the second element.
    $this->drupalGet('form-test/group-vertical-tabs', ['fragment' => 'edit-element-2']);

    $page = $session->getPage();

    $tab_link_1 = $page->find('css', '.vertical-tabs__menu-item > a');

    $child_1_selector = '#edit-element';
    $child_1 = $page->find('css', $child_1_selector);

    $child_2_selector = '#edit-element-2';
    $child_2 = $page->find('css', $child_2_selector);

    // Assert that the child in the second vertical tab becomes visible.
    // It should be visible after initial load due to the fragment in the URI.
    $this->assertTrue($child_2->isVisible(), 'Child 2 is visible due to a URI fragment');

    // Click on a fragment link pointing to an invisible child inside an
    // inactive vertical tab.
    $session->executeScript("jQuery('<a href=\"$child_1_selector\"></a>').insertAfter('h1')[0].click()");

    // Assert that the child in the first vertical tab becomes visible.
    $web_assert->waitForElementVisible('css', $child_1_selector, 50);

    // Trigger a URI fragment change (hashchange) to show the second vertical
    // tab again.
    $session->executeScript("location.replace('$child_2_selector')");

    // Assert that the child in the second vertical tab becomes visible again.
    $web_assert->waitForElementVisible('css', $child_2_selector, 50);

    $tab_link_1->click();

    // Assert that the child in the first vertical tab is visible again after
    // a click on the first tab.
    $this->assertTrue($child_1->isVisible(), 'Child 1 is visible after clicking the parent tab');
  }

  /**
   * Confirms that there are indicators on tabs with child errors.
   */
  public function testVerticalTabsIndicateTabChildErrors(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('form-test/group-vertical-tabs');
    $page->clickLink('Second group element');
    $input_field = $assert_session->waitForField('element_2');
    $this->assertNotNull($input_field);

    // Enter a value that will trigger a validation error.
    $input_field->setValue('bad');

    // Submit the form.
    $page->pressButton('Save');

    // Confirm the tab containing the field with error indicates child errors.
    $title_element = $assert_session->waitForElement('css', ':nth-child(2).vertical-tabs__menu-item.error');
    $this->assertStringNotContainsString($title_element->getText(), '(contains error)');

    // Confirm '(contains error)' text is in title and has
    // 'visually-hidden' class.
    $error_indicator_element = $title_element->find('css', '.child-error-indicator');
    $this->assertTrue($error_indicator_element->hasClass('visually-hidden'));
    $this->assertEquals('(contains error)', $error_indicator_element->getText());
  }

}
