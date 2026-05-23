<?php

declare(strict_types=1);

namespace Drupal\toggletip_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a page for testing toggletips.
 */
class ToggletipForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return [
      // USE CASE 1: Standalone toggletip render element.
      'standalone' => $this->buildStandaloneExamples(),

      // Tip placement examples.
      'tip_placement' => $this->buildTipPlacementExamples(),

      // Headings with toggletips.
      'headings' => $this->buildHeadingExamples(),

      // Button placement examples.
      'button_placement' => $this->buildButtonPlacementExamples(),

      // Form elements examples.
      'form_elements' => $this->buildFormElementsExamples(),

      'a_details' => [
        '#type' => 'details',
        '#title' => 'Toggletip on details',
        '#toggletip' => [
          'content' => '<p>Details: Many of the jokes are of a cruel physical nature, involving a hairpiece worn by the uncle\'s boss, or face-lifts, or phony bomb threats. What they boil down to is, little Clifford is mean, vindictive, spiteful and cruel. So hateful that if a real little boy had played him, the movie would be like "The Omen" filtered through "The Good Son" and a particularly bad evening of "Saturday Night Live." But Martin Short is clearly not a little boy. He is a curious adult pretending to be a little boy, with odd verbal mannerisms</p>
            <p>And then there is the "climax," in which Uncle Martin finally does take little Clifford to the Dinosaur Park. The movie treats the sequence as a bravura set piece, but actually it\'s an embarrassing assembly of shabby special effects, resulting in absolutely no comic output. At one point the movie sets up an out-of-control thrill ride, and we in the audience think we know how the laughs will build, but we\'re wrong. They don\'t.</p>',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'added_in_markup' => [
        '#type' => 'container',
        'text' => [
          '#markup' => 'JS attribute: Sometimes for placeholder text we use <span data-drupal-toggletip="{&quot;content&quot;:&quot;\u003Cp\u003EWhat we have here is a suitable case for deep cinematic analysis. I would love to hear a symposium of veteran producers, marketing guys and exhibitors discuss this film. It is not bad in any usual way. It is bad in a new way all its own. There is something extraterrestrial about it, as if it is based on the sense of humor of an alien race with a completely different relationship to the physical universe. The movie is so odd, it is almost worth seeing just because we will never see anything like it again. I hope.\u003C\/p\u003E&quot;,&quot;rendered&quot;:true}">IPSUM!</span>, and that is nice.',
        ],
      ],
      'a_trigger' => [
        '#type' => 'container',
        '#allowed_tags' => ['button'],
        '#markup' => '<button data-drupal-tip-toggle-button="Tooltip Text" type="button" popoverTarget="very_unique_id">This is tooltip trigger(can be any trigger)</button>',
      ],
      'a_trigger_with_placement' => [
        '#type' => 'container',
        '#allowed_tags' => ['button'],
        '#markup' => '<button data-drupal-tip-toggle-button="Tooltip Text" data-drupal-tip-placement="bottom" type="button" popoverTarget="very_very_unique_id">And this places tooltip on bottom</button>',
      ],
      'a_trigger_with_placement_right_end' => [
        '#type' => 'container',
        '#allowed_tags' => ['button'],
        '#markup' => '<button data-drupal-tip-toggle-button="Tooltip Text" data-drupal-tip-placement="right-end" type="button" popoverTarget="very_very_very_unique_id">And this places tooltip on right-end</button>',
      ],
      'a_trigger_with_hover' => [
        '#type' => 'container',
        '#allowed_tags' => ['button'],
        '#markup' => '<button data-drupal-tip-toggle-button="Tooltip Text" data-drupal-tip-on-hover="true" type="button" popoverTarget="very_very_very_very_unique_id">And this one also works on hover</button>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'toggletip_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Helper: Create an example with a title.
   */
  private function example(string $title, array $element): array {
    return [
      '#type' => 'container',
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $title,
      ],
      'element' => $element,
    ];
  }

  /**
   * Helper: Create a use case group with heading.
   */
  private function useCaseGroup(string $title, array $examples): array {
    return [
      '#type' => 'container',
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
      ],
    ] + $examples;
  }

  /**
   * Build standalone toggletip examples.
   *
   * USE CASE 1: Direct usage of '#type' => 'toggletip' render element.
   * Best for: Adding toggletip buttons without attaching to other elements.
   */
  private function buildStandaloneExamples(): array {
    return $this->useCaseGroup('1: Standalone Toggletip Element', [
      'basic' => $this->example('Basic standalone toggletip', [
        '#type' => 'toggletip',
        '#button_text' => 'i',
        '#content' => '<p>This is a standalone toggletip using the new render element.</p>',
        '#description' => 'Standalone toggletip help',
      ]),
      'manual_button' => $this->example('Manual button element (JS creates tip)', [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => 'i',
        '#attributes' => [
          'type' => 'button',
          'class' => ['toggletip'],
          'data-drupal-tip-toggle-button' => '<p>Button as render element, tip created by tip.js</p>',
          'popovertarget' => 'manual-tip-1',
        ],
      ]),
      'any_html_element' => $this->example('Any styled div with toggletip(hover)', [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => 'Hover or click this styled div',
        '#attributes' => [
          'class' => ['custom-styled-trigger'],
          'style' => 'display: inline-block; padding: 10px; background: #e0e0e0; cursor: pointer;',
          'data-drupal-tip-toggle-button' => '<p>This is a div element styled to trigger a toggletip!</p>',
          'data-drupal-tip-on-hover' => 'true',
          'popovertarget' => 'manual-tip-2',
        ],
      ]),
    ]);
  }

  /**
   * Build button placement examples.
   *
   * Demonstrates button positioning using CSS anchor positioning on headings.
   */
  private function buildButtonPlacementExamples(): array {
    return $this->useCaseGroup('4: Button Placement (CSS Anchor Positioning)', [
      'h2_left' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement left',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'left',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2_top' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement top',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'top',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2_bottom' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement bottom',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'bottom',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2_right' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement right',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'right',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2_start' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement start',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'start',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2_end' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement end',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'end',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2_center' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2 with button placement center',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'buttonPlace' => 'center',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
    ]);
  }

  /**
   * Build tip placement examples.
   *
   * Demonstrates different tip placements using Floating UI.
   */
  private function buildTipPlacementExamples(): array {
    return $this->useCaseGroup('2: Tip Placement (Floating UI)', [
      'top' => [
        '#type' => 'container',
        '#markup' => 'Tip placement: top',
        '#toggletip' => [
          'content' => '<p>This tip appears on top</p>',
          'place' => 'top',
          'atDescription' => 'Tip placed on top',
        ],
      ],
      'right' => [
        '#type' => 'container',
        '#markup' => 'Tip placement: right',
        '#toggletip' => [
          'content' => '<p>This tip appears on the right</p>',
          'place' => 'right',
          'atDescription' => 'Tip placed on right',
        ],
      ],
      'bottom' => [
        '#type' => 'container',
        '#markup' => 'Tip placement: bottom',
        '#toggletip' => [
          'content' => '<p>This tip appears on bottom</p>',
          'place' => 'bottom',
          'atDescription' => 'Tip placed on bottom',
        ],
      ],
      'left' => [
        '#type' => 'container',
        '#markup' => 'Tip placement: left',
        '#toggletip' => [
          'content' => '<p>This tip appears on the left</p>',
          'place' => 'left',
          'atDescription' => 'Tip placed on left',
        ],
      ],
      'right-end' => [
        '#type' => 'container',
        '#markup' => 'Tip placement: right-end',
        '#toggletip' => [
          'content' => '<p>This tip appears on the right, aligned to end</p>',
          'place' => 'right-end',
          'atDescription' => 'Tip placed on right-end',
        ],
      ],
    ]);
  }

  /**
   * Build heading examples with toggletips.
   *
   * Demonstrates toggletips on h1, h2, h3 heading elements.
   */
  private function buildHeadingExamples(): array {
    return $this->useCaseGroup('3: Headings with Toggletips', [
      'h1' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => 'Toggletip on html tag h1',
        '#toggletip' => [
          'content' => '<p>H1: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h2' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => 'Toggletip on html tag h2',
        '#toggletip' => [
          'content' => '<p>H2: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'h3' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => 'Toggletip on html tag h3',
        '#toggletip' => [
          'content' => '<p>H3: Now. Back to the main story, which takes place in the present. Martin Short stars as little Clifford, a brat, about 10 years old, I guess. Short plays him with no makeup other than a wig and little boy\'s suits, and the camera angles are selected to make him look a foot shorter than the other actors. Clifford is a little boy from hell, a sneaky practical joker, spoiled, obnoxious. We meet him with his parents on a flight to Hawaii. He wants the plane to land in Los Angeles so he can visit the Dinosaur Park amusement park.</p>
            <p>This sets up the body of the movie, in which Clifford\'s uncle Martin agrees to take the lad for a week, partly to convince his girlfriend that he does, indeed, like children. But no one could like this child, who grows enraged when his uncle won\'t take him to Dinosaur Park, and plays a series of practical jokes, beginning with filling his uncle\'s drink with Tabasco sauce, and ending with the destruction of his uncle\'s plans for the Los Angeles transportation system.</p>',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
    ]);
  }

  /**
   * Build form elements examples.
   *
   * Demonstrates toggletips on various form elements.
   */
  private function buildFormElementsExamples(): array {
    return $this->useCaseGroup('5: Form Elements with Toggletips', [
      'textfield' => [
        '#type' => 'textfield',
        '#title' => 'A Textfield',
        '#maxlength' => '254',
        '#toggletip' => [
          'content' => '<p>TEXTFIELD: A movie like this is a deep mystery. It asks the question: What went wrong? "Clifford" is not bad on the acting, directing or even writing levels. It fails on a deeper level still, the level of the underlying conception. Something about the material itself is profoundly not funny. Irredeemably not funny, so that it doesn\'t matter what the actors do, because they are in a movie that should never have been made.</p>
            <p>The story opens in the year 2050, when a kindly old priest is trying to reason with a rebellious kid in a home for troubled kids. The priest (Short) tells the kid that he was once a troubled kid, himself. That sets up three flashbacks that make up most of the movie. To deal with the 2050 scenes right up front: They are completely unnecessary. Their only apparent function is to show Martin Short made up as an old man.</p>',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'textarea' => [
        '#type' => 'textarea',
        '#title' => 'A Textarea',
        '#cols' => '80',
        '#rows' => '20',
        '#toggletip' => [
          'content' => '<p>TEXTAREA: A movie like this is a deep mystery. It asks the question: What went wrong? "Clifford" is not bad on the acting, directing or even writing levels. It fails on a deeper level still, the level of the underlying conception. Something about the material itself is profoundly not funny. Irredeemably not funny, so that it doesn\'t matter what the actors do, because they are in a movie that should never have been made.</p>
            <p>The story opens in the year 2050, when a kindly old priest is trying to reason with a rebellious kid in a home for troubled kids. The priest (Short) tells the kid that he was once a troubled kid, himself. That sets up three flashbacks that make up most of the movie. To deal with the 2050 scenes right up front: They are completely unnecessary. Their only apparent function is to show Martin Short made up as an old man.</p>',
          'atDescription' => 'This tests the atDescription property',
        ],
      ],
      'fieldset' => [
        '#type' => 'fieldset',
        '#title' => 'A Fieldset with toggletip',
        '#toggletip' => [
          'content' => '<p>FIELDSET: Use fieldsets to group related form elements together.</p>',
          'atDescription' => 'Help about fieldset',
        ],
        'nested_field' => [
          '#type' => 'textfield',
          '#title' => 'Field inside fieldset',
        ],
      ],
      'select' => [
        '#type' => 'select',
        '#title' => 'A Select dropdown',
        '#options' => [
          'option1' => 'Option 1',
          'option2' => 'Option 2',
          'option3' => 'Option 3',
        ],
        '#toggletip' => [
          'content' => '<p>SELECT: Choose one option from the dropdown list.</p>',
          'atDescription' => 'Help about select',
        ],
      ],
      'number' => [
        '#type' => 'number',
        '#title' => 'A Number field',
        '#min' => 0,
        '#max' => 100,
        '#step' => 1,
        '#toggletip' => [
          'content' => '<p>NUMBER: Enter a number between 0 and 100.</p>',
          'atDescription' => 'Help about number field',
        ],
      ],
      'email' => [
        '#type' => 'email',
        '#title' => 'An Email field',
        '#toggletip' => [
          'content' => '<p>EMAIL: Enter a valid email address like user@example.com</p>',
          'atDescription' => 'Help about email',
        ],
      ],
      'tel' => [
        '#type' => 'tel',
        '#title' => 'A Telephone field',
        '#toggletip' => [
          'content' => '<p>TEL: Enter your phone number.</p>',
          'atDescription' => 'Help about phone',
        ],
      ],
      'url_field' => [
        '#type' => 'url',
        '#title' => 'A URL field',
        '#toggletip' => [
          'content' => '<p>URL: Enter a valid URL starting with http:// or https://</p>',
          'atDescription' => 'Help about URL',
        ],
      ],
      'checkboxes' => [
        '#type' => 'checkboxes',
        '#title' => 'Checkboxes with toggletip',
        '#options' => [
          'apple' => 'Apple',
          'banana' => 'Banana',
          'orange' => 'Orange',
        ],
        '#toggletip' => [
          'content' => '<p>CHECKBOXES: Select one or more options.</p>',
          'atDescription' => 'Help about checkboxes',
        ],
      ],
      'radios' => [
        '#type' => 'radios',
        '#title' => 'Radio buttons with toggletip',
        '#options' => [
          'red' => 'Red',
          'green' => 'Green',
          'blue' => 'Blue',
        ],
        '#toggletip' => [
          'content' => '<p>RADIOS: Select one option from the list.</p>',
          'atDescription' => 'Help about radios',
        ],
      ],
    ]);
  }

}
