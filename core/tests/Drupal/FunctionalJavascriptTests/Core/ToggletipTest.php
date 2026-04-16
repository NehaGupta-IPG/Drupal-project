<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Core\Render\Element\Toggletip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the toggletip element.
 */
#[CoversClass(Toggletip::class)]
#[Group('Render')]
#[RunTestsInSeparateProcesses]
class ToggletipTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['toggletip_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser(['access content']);
    $this->drupalLogin($user);
    $this->drupalGet('/toggletip-test/toggletips');
  }

  /**
   * Tests toggletip functionality.
   *
   * @param string $element_selector
   *   Selector of element the toggle tip is for.
   * @param string $text
   *   Text inside the toggle tip.
   * @param string $at_description
   *   Text of atDescription property.
   */
  #[DataProvider('toggletipContent')]
  public function testToggletip(string $element_selector, string $text, string $at_description = 'This tests the atDescription property'): void {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $element_selector);
    $toggle_button = $element->find('css', 'button.toggletip');
    $at_description_span = $toggle_button->find('css', 'span.visually-hidden');
    if ($at_description_span) {
      $this->assertEquals($at_description, $at_description_span->getText());
    }
    // Get tip id from button.
    $tip_id = $toggle_button->getAttribute('popovertarget');
    $popover_element = $page->find('css', "#$tip_id");
    // Confirm popover is not visible.
    $this->assertFalse($popover_element->isVisible(), "Popover for $element_selector should not be visible before click");
    $toggle_button->click();
    // Wait for popover to open.
    $this->getSession()->wait(500);
    // Confirm popover is visible.
    $this->assertTrue($popover_element->isVisible(), "Popover $element_selector should be visible after click");
    // Check for text.
    $this->assertStringContainsString($text, $popover_element->getText());
    // Click to close popover.
    $toggle_button->click();
    // Confirm popover is not visible anymore.
    $this->assertFalse($popover_element->isVisible(), "Popover for $element_selector should not be visible after clicking off it");
  }

  /**
   * Data provider for toggletip test.
   *
   * @return array
   *   Array of selectors and text content.
   */
  public static function toggletipContent() {
    return [
      [
        'summary[aria-controls="edit-a-details"]',
        "Details: Many of the jokes are of a cruel physical nature, "
        . "involving a hairpiece worn by the uncle's boss, or face-lifts, "
        . "or phony bomb threats. What they boil down to is, little "
        . "Clifford is mean, vindictive, spiteful and cruel. So hateful "
        . "that if a real little boy had played him, the movie would be "
        . "like \"The Omen\" filtered through \"The Good Son\" and a "
        . "particularly bad evening of \"Saturday Night Live.\" But "
        . "Martin Short is clearly not a little boy. He is a curious "
        . "adult pretending to be a little boy, with odd verbal "
        . "mannerisms And then there is the \"climax,\" in which Uncle "
        . "Martin finally does take little Clifford to the Dinosaur Park. "
        . "The movie treats the sequence as a bravura set piece, but "
        . "actually it's an embarrassing assembly of shabby special "
        . "effects, resulting in absolutely no comic output. At one "
        . "point the movie sets up an out-of-control thrill ride, and we "
        . "in the audience think we know how the laughs will build, but "
        . "we're wrong. They don't.",
      ],
      [
        '.form-item-textfield',
        "TEXTFIELD: A movie like this is a deep mystery. It asks the "
        . "question: What went wrong? \"Clifford\" is not bad on the "
        . "acting, directing or even writing levels. It fails on a deeper "
        . "level still, the level of the underlying conception. Something "
        . "about the material itself is profoundly not funny. Irredeemably "
        . "not funny, so that it doesn't matter what the actors do, because "
        . "they are in a movie that should never have been made. The story "
        . "opens in the year 2050, when a kindly old priest is trying to "
        . "reason with a rebellious kid in a home for troubled kids. The "
        . "priest (Short) tells the kid that he was once a troubled kid, "
        . "himself. That sets up three flashbacks that make up most of the "
        . "movie. To deal with the 2050 scenes right up front: They are "
        . "completely unnecessary. Their only apparent function is to show "
        . "Martin Short made up as an old man.",
      ],
      [
        '.form-item-textarea',
        "TEXTAREA: A movie like this is a deep mystery. It asks the "
        . "question: What went wrong? \"Clifford\" is not bad on the "
        . "acting, directing or even writing levels. It fails on a deeper "
        . "level still, the level of the underlying conception. Something "
        . "about the material itself is profoundly not funny. Irredeemably "
        . "not funny, so that it doesn't matter what the actors do, because "
        . "they are in a movie that should never have been made. The story "
        . "opens in the year 2050, when a kindly old priest is trying to "
        . "reason with a rebellious kid in a home for troubled kids. The "
        . "priest (Short) tells the kid that he was once a troubled kid, "
        . "himself. That sets up three flashbacks that make up most of the "
        . "movie. To deal with the 2050 scenes right up front: They are "
        . "completely unnecessary. Their only apparent function is to show "
        . "Martin Short made up as an old man.",
      ],
    ];

  }

}
