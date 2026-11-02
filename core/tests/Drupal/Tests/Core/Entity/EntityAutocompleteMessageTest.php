<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests autocomplete error presentation and original input preservation.
 */
#[Group('Entity')]
final class EntityAutocompleteMessageTest extends UnitTestCase {

  /**
   * Provides test data.
   */
  public static function cases(): iterable {
    $cases = [
      'empty resubmission clears old input' => ['input' => '', 'stale' => TRUE, 'kind' => 'none'],
      'preprocessed ID does not inherit old input' => [
        'submitted' => [['target_id' => 42]],
        'stale' => TRUE,
        'kind' => 'selected',
      ],
      'fresh input replaces old input' => ['input' => 'Example (42)', 'stale' => TRUE],
      'normalized value retains raw input' => [
        'input' => 'Example (42)',
        'submitted' => [['target_id' => 42]],
        'raw' => 'Example (42)',
      ],
      'rejected new target retains its original label' => ['autocreate' => TRUE, 'new_label' => 'Other label'],
      'two ambiguous matches' => ['matches' => 2, 'kind' => 'multiple'],
      'only five of six matches are offered' => ['matches' => 6, 'kind' => 'multiple'],
      'HTML labels are decoded and escaped once' => [
        'match_label' => '<em>Example &amp; Co</em>',
        'examples' => ['Example &amp; Co (42)', 'Example &amp; Co (43)'],
      ],
      'commas use a single pair of input quotes' => [
        'match_label' => 'Example, One',
        'examples' => ['&quot;Example, One (42)&quot;', '&quot;Example, One (43)&quot;'],
      ],
      'quotes remain valid input syntax' => [
        'match_label' => 'Example "One"',
        'examples' => [
          '&quot;Example &quot;&quot;One&quot;&quot; (42)&quot;',
          '&quot;Example &quot;&quot;One&quot;&quot; (43)&quot;',
        ],
      ],
      'new targets retain their input deltas among existing targets' => [
        'input' => 'Existing (42), Example',
        'tags' => TRUE,
        'autocreate' => TRUE,
      ],
      'example whitespace matches autocomplete normalization' => [
        'match_label' => "<em> Example  </em>\n&amp; Co ",
        'examples' => ['Example &amp; Co (42)', 'Example &amp; Co (43)'],
      ],
      'input is escaped' => ['input' => '<script>'],
      'zero is valid text input' => ['input' => '0'],
    ];
    foreach ($cases as $name => $case) {
      if (isset($case['examples'])) {
        $case += ['matches' => 2, 'kind' => 'multiple'];
      }
      yield $name => [$case];
    }
  }

  /**
   * Tests autocomplete messages and input preservation.
   */
  #[DataProvider('cases')]
  public function testAutocompleteMessages(array $case): void {
    $case += [
      'input' => 'Example',
      'autocreate' => FALSE,
      'new_label' => 'Example',
      'match_label' => 'Example',
      'matches' => 0,
      'kind' => 'input',
      'tags' => FALSE,
      'stale' => FALSE,
    ];
    $new_entity = $this->createStub(EntityInterface::class);
    $new_entity->method('isNew')->willReturn(TRUE);
    $new_entity->method('label')->willReturn($case['new_label']);
    $handler = $case['tags']
      ? $this->createMock(DefaultSelection::class)
      : $this->createStub(DefaultSelection::class);
    $options = [];
    for ($i = 0; $i < $case['matches']; $i++) {
      $options[42 + $i] = $case['match_label'];
    }
    $handler->method('getReferenceableEntities')->willReturn($options ? ['article' => $options] : []);
    $handler->method('validateReferenceableEntities')->willReturnCallback(static fn(array $ids) => $case['tags'] ? $ids : []);
    $new_validation = $case['tags']
      ? $handler->expects(self::once())->method('validateReferenceableNewEntities')->with([1 => $new_entity])
      : $handler->method('validateReferenceableNewEntities');
    $new_validation->willReturn([]);
    $handler->method('createNewEntity')->willReturn($new_entity);
    $selection_manager = $this->createStub(SelectionPluginManagerInterface::class);
    $selection_manager->method('getInstance')->willReturn($handler);
    $selection_manager->method('getSelectionHandler')->willReturn($handler);
    $entity_type = $this->createStub(EntityTypeInterface::class);
    $entity_type->method('getSingularLabel')->willReturn('content item');
    $entity_type->method('getPluralLabel')->willReturn('content items');
    $manager = $this->createStub(EntityTypeManagerInterface::class);
    $manager->method('getDefinition')->willReturn($entity_type);
    $rendered_message = NULL;
    $renderer = $this->createStub(RendererInterface::class);
    $renderer->method('renderInIsolation')->willReturnCallback(static function (array &$build) use (&$rendered_message) {
      $rendered_message = $build;
      return Markup::create('Rendered ambiguity message');
    });
    $container = new ContainerBuilder();
    $container->set('renderer', $renderer);
    $container->set('plugin.manager.entity_reference_selection', $selection_manager);
    $container->set('entity_type.manager', $manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $input = $case['input'];
    $state = new FormState();
    $state->clearErrors();
    if (isset($case['raw'])) {
      $state->setUserInput(['reference' => [0 => ['target_id' => $case['raw']]]]);
    }
    $form = ['#parents' => [], 'reference' => ['#parents' => ['reference']]];
    $form['reference'][0]['target_id'] = [
      '#parents' => ['reference', 0, 'target_id'],
      '#id' => 'edit-reference-0-target-id',
      '#value' => $case['submitted'] ?? $input,
      '#tags' => $case['tags'],
      '#selection_settings' => [],
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#autocreate' => $case['autocreate'] ? ['bundle' => 'article', 'uid' => 1] : FALSE,
      '#validate_reference' => TRUE,
    ];
    if ($case['stale']) {
      $form['reference'][0]['target_id']['#entity_reference_input_values'] = ['Obsolete input (7)'];
    }
    EntityAutocomplete::validateEntityAutocomplete($form['reference'][0]['target_id'], $state, $form);
    $messages = array_map(static fn($message) => (string) $message, array_values($state->getErrors()));
    if ($case['kind'] === 'none') {
      self::assertSame([], $messages);
    }
    elseif ($case['kind'] === 'selected') {
      self::assertSame(['The selected content item cannot be referenced.'], $messages);
    }
    elseif ($case['kind'] === 'input') {
      $expected_input = $case['tags'] ? 'Example' : $input;
      self::assertSame(['No content item matching <em class="placeholder">' . Html::escape($expected_input) . '</em> can be referenced.'], $messages);
    }
    else {
      $expected_examples = $case['examples'] ?? array_map(static fn($id) => "Example ($id)", range(42, 41 + min($case['matches'], 5)));
      self::assertSame(['Rendered ambiguity message'], $messages);
      self::assertSame('edit-reference-0-target-id', $rendered_message['#attributes']['data-drupal-autocomplete-message']);
      self::assertSame('Multiple content items match <em class="placeholder">Example</em>. To select a specific match, append its ID in parentheses, as in:', (string) $rendered_message['full']['instruction']['#markup']);
      self::assertSame('item_list', $rendered_message['full']['examples']['#theme']);
      self::assertSame($expected_examples, array_column($rendered_message['full']['examples']['#items'], '#value'));
      $matches = (new EntityAutocompleteMatcher($selection_manager))->getMatches('node', 'default', [], 'Example');
      self::assertSame(array_map([Html::class, 'decodeEntities'], $expected_examples), array_slice(array_column($matches, 'value'), 0, 5));
      foreach ($rendered_message['full']['examples']['#items'] as $example) {
        self::assertSame('code', $example['#tag']);
      }
      self::assertSame('Multiple content items match <em class="placeholder">Example</em>. Select a match from the autocomplete suggestions.', (string) $rendered_message['short']['#value']);
      self::assertContains('js-hide', $rendered_message['full']['#attributes']['class']);
      self::assertContains('js-show', $rendered_message['short']['#attributes']['class']);
      self::assertArrayNotHasKey('hidden', $rendered_message['short']['#attributes']);
      self::assertSame('true', $rendered_message['short']['#attributes']['aria-hidden']);
    }
  }

  /**
   * Tests formatting a rendered label with a string entity ID.
   */
  public function testAutocompleteInputWithStringId(): void {
    $input = EntityAutocomplete::formatAutocompleteInput(Markup::create('<em>Example &amp; Co</em>'), 'section-a');
    self::assertSame('Example & Co (section-a)', $input);
    self::assertSame('section-a', EntityAutocomplete::extractEntityIdFromAutocompleteInput($input));
  }

}
