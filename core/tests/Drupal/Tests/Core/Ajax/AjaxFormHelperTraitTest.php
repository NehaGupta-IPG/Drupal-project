<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Ajax;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests error, rebuild, and success responses for AJAX forms.
 */
#[Group('Ajax')]
class AjaxFormHelperTraitTest extends UnitTestCase {

  /**
   * Tests that errors and rebuilds display the form without running success actions.
   */
  #[DataProvider('submissionProvider')]
  public function testSubmission(bool $error, bool $rebuild, ?string $wrapper, array $expected_commands, ?string $expected_selector): void {
    $container = new ContainerBuilder();
    $renderer = $this->createStub(RendererInterface::class);
    $renderer->method('renderRoot')->willReturn(Markup::create('Rendered form'));
    $container->set('renderer', $renderer);
    \Drupal::setContainer($container);

    $state = new FormState();
    $state->clearErrors();
    if ($error) {
      $state->setErrorByName('name', 'Name is required.');
    }
    $state->setRebuild($rebuild);
    if ($wrapper !== NULL) {
      $state->setTriggeringElement(['#ajax' => ['wrapper' => $wrapper]]);
    }
    $form = [
      '#attributes' => ['data-drupal-selector' => 'example-form'],
      '#attached' => [],
    ];
    $helper = new class() {

      use AjaxFormHelperTrait;

      protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        return (new AjaxResponse())->addCommand(new InvokeCommand('#result', 'addClass', ['saved']));
      }

    };
    $response = $helper->ajaxSubmit($form, $state);
    $commands = $response->getCommands();
    $this->assertSame($expected_commands, array_column($commands, 'command'));
    if ($error || $rebuild) {
      $this->assertSame($expected_selector, $commands[0]['selector']);
      $this->assertSame('replaceWith', $commands[0]['method']);
    }
    else {
      $this->assertSame([['saved']], array_column($commands, 'args'));
    }
  }

  /**
   * Provides form processing outcomes.
   */
  public static function submissionProvider(): array {
    $form_selector = '[data-drupal-selector="example-form"]';
    return [
      'validation error' => [TRUE, FALSE, 'original-form', ['insert'], NULL],
      'validation error without wrapper' => [TRUE, FALSE, NULL, ['insert'], $form_selector],
      'validation error with empty wrapper' => [TRUE, FALSE, '', ['insert'], $form_selector],
      'validation error with numeric wrapper ID' => [TRUE, FALSE, '0', ['insert'], NULL],
      'form rebuild' => [FALSE, TRUE, 'original-form', ['insert'], NULL],
      'successful submission' => [FALSE, FALSE, 'original-form', ['invoke'], NULL],
    ];
  }

}
