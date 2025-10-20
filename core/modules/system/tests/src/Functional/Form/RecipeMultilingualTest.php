<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

// cspell:ignore Büikuchen Betreibers Unfug erlauben Rezept anwenden Feld ist
// cspell:ignore erforderlich finde solltest keine besitzen Revier

use Drupal\Core\Recipe\RecipeInputFormTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests multilingual recipes.
 */
#[Group('system')]
#[CoversTrait(RecipeInputFormTrait::class)]
#[RunTestsInSeparateProcesses]
class RecipeMultilingualTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests collecting recipe input via a form.
   */
  public function testRecipeInputViaForm(): void {

    $this->drupalGet('/form-test/recipe-input');

    $assert_session = $this->assertSession();
    // There should only be two nested input elements on the page: the one
    // defined by the input_test recipe and the other defined by its dependency,
    // the create_node_type recipe.
    $assert_session->elementsCount('css', 'input[name*="["]', 2);
    // The default value and description should be visible.
    $assert_session->fieldValueEquals('input_test[owner]', 'Dries Büikuchen');
    $assert_session->pageTextContains('Der Name des Website-Betreibers.');
    $this->assertSame('checkbox', $assert_session->fieldExists('Unfug erlauben')->getAttribute('type'));
    // All recipe inputs are required, except for checkboxes, for which that
    // behavior makes no sense.
    $this->submitForm(['input_test[owner]' => ''], 'Rezept anwenden');
    $assert_session->statusCodeEquals(200);
    $assert_session->statusMessageContains("Das Feld Name des Website-Betreibers ist erforderlich.", 'error');
    $assert_session->statusMessageNotContains('Das Feld Unfug erlauben ist erforderlich.', 'error');
    // All inputs should be validated with their own constraints.
    $this->submitForm(['input_test[owner]' => 'Hacker Joe'], 'Rezept anwenden');
    $assert_session->statusMessageContains("Ich finde, du solltest keine Websites besitzen.", 'error');
    // The correct element should be flagged as invalid.
    $assert_session->elementAttributeExists('named', ['field', 'input_test[owner]'], 'aria-invalid');
    // Submit the form with a valid value and apply the recipe, to prove that
    // it was passed through correctly.
    $this->submitForm(['input_test[owner]' => 'Legitimate Human'], 'Rezept anwenden');
    // This also tests that config actions are run using the correct language
    // and can be translated.
    $this->assertSame("Legitimate Human's Revier", $this->config('system.site')->get('name'));
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters() {
    $parameters = parent::installParameters();
    // Install in German. This will ensure the language and locale modules are
    // installed.
    $parameters['parameters']['langcode'] = 'de';
    // Create a po file so we don't attempt to download one from
    // localize.drupal.org and to have a test translation that will not change.
    \Drupal::service('file_system')->mkdir($this->publicFilesDirectory . '/translations', NULL, TRUE);
    $contents = <<<PO
msgid ""
msgstr ""

msgid "The name of the site owner."
msgstr "Der Name des Website-Betreibers."

msgid "I don't think you should be owning sites."
msgstr "Ich finde, du solltest keine Websites besitzen."

msgid "Site owner's name"
msgstr "Name des Website-Betreibers"

msgid "Dries Buytaert"
msgstr "Dries Büikuchen"

msgid "Should mischief be allowed on this site? Not recommended."
msgstr "Sollte Unfug auf dieser Website erlaubt sein? Nicht empfehlenswert."

msgid "Allow mischief"
msgstr "Unfug erlauben"

msgid "Apply recipe"
msgstr "Rezept anwenden"

msgid "\${owner}'s Turf"
msgstr "\${owner}'s Revier"

msgid "@name field is required."
msgstr "Das Feld @name ist erforderlich."

PO;
    $version = explode('.', \Drupal::VERSION)[0] . '.0.0';
    file_put_contents($this->publicFilesDirectory . "/translations/drupal-{$version}.de.po", $contents);
    return $parameters;
  }

}
