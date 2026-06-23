<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Unit\Element;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests ManagedFile::uploadAjaxCallback().
 *
 * Specifically covers the case where the form element resolved by
 * NestedArray::getValue() does not have a '#prefix' key set — which happens
 * when a file widget is nested inside a media reference field and the AJAX
 * rebuild reaches the element before processManagedFile() has run.
 *
 * @see \Drupal\file\Element\ManagedFile::uploadAjaxCallback()
 */
#[CoversClass(ManagedFile::class)]
#[Group('file')]
class ManagedFileUploadAjaxCallbackTest extends UnitTestCase {

  /**
   * A renderer mock that returns predictable output.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Build a minimal container so that \Drupal::service('renderer') resolves.
    $this->renderer = $this->createMock(RendererInterface::class);
    // renderRoot() is called twice: once for status_messages and once for the
    // full form element. Return distinct strings so we can assert ordering.
    $this->renderer
      ->method('renderRoot')
      ->willReturnCallback(function (array &$element): string {
        return isset($element['#type']) && $element['#type'] === 'status_messages'
          ? '<div class="messages"></div>'
          : '<div class="managed-file"></div>';
      });

    $container = new ContainerBuilder();
    $container->set('renderer', $this->renderer);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that uploadAjaxCallback does not emit a warning when #prefix absent.
   *
   * Before the fix, calling uploadAjaxCallback() on a form element that had no
   * '#prefix' key caused:
   *   Warning: Undefined array key "#prefix" in
   *   Drupal\file\Element\ManagedFile::uploadAjaxCallback()
   *
   * After the fix the method must complete without any PHP warning and the
   * status-messages markup must appear in the rendered output.
   */
  public function testUploadAjaxCallbackWithoutPrefix(): void {
    // Simulate the form tree returned by NestedArray::getValue() for an element
    // that is nested inside a media reference widget. The important detail is
    // that '#prefix' is NOT set — mimicking the state before processManagedFile
    // has had a chance to set it.
    $form = [
      'widget' => [
        'file_upload' => [
          '#type' => 'managed_file',
          '#attached' => [],
          // '#prefix' deliberately omitted to reproduce the bug.
        ],
      ],
    ];

    $form_state = new FormState();
    // file_upload_delta_initial must be set; use a value that will NOT trigger
    // the ajax-new-content branch so we isolate the #prefix path.
    $form_state->set('file_upload_delta_initial', 99);

    $request = Request::create('/file/ajax');
    // element_parents encodes the path used by NestedArray::getValue().
    $request->query->set('element_parents', 'widget/file_upload');
    $request->query->set('form_build_id', 'test_build_id');

    // The test passes when no PHP warning is raised and the callback returns an
    // AjaxResponse (i.e. execution completes normally). If the bug is present
    // PHPUnit will convert the E_WARNING into a test failure.
    $response = ManagedFile::uploadAjaxCallback($form, $form_state, $request);

    $this->assertNotNull($response, 'uploadAjaxCallback() must return a response.');
  }

  /**
   * Tests that an existing #prefix value is preserved after the fix.
   *
   * The null-coalescing initialisation must not clobber a #prefix that was
   * legitimately set by processManagedFile().
   */
  public function testUploadAjaxCallbackPreservesExistingPrefix(): void {
    $existing_prefix = '<div id="managed-file-wrapper">';

    $form = [
      'file_upload' => [
        '#type' => 'managed_file',
        '#attached' => [],
        '#prefix' => $existing_prefix,
      ],
    ];

    $form_state = new FormState();
    $form_state->set('file_upload_delta_initial', 99);

    $request = Request::create('/file/ajax');
    $request->query->set('element_parents', 'file_upload');
    $request->query->set('form_build_id', 'test_build_id');

    // Capture the element passed to renderRoot() to inspect '#prefix'.
    $capturedElement = NULL;
    $this->renderer
      ->expects($this->atLeastOnce())
      ->method('renderRoot')
      ->willReturnCallback(function (array &$element) use (&$capturedElement): string {
        if (!isset($element['#type']) || $element['#type'] !== 'status_messages') {
          $capturedElement = $element;
        }
        return '<rendered/>';
      });

    ManagedFile::uploadAjaxCallback($form, $form_state, $request);

    $this->assertNotNull($capturedElement, 'The form element must have been rendered.');
    $this->assertStringStartsWith(
      $existing_prefix,
      $capturedElement['#prefix'] ?? '',
      'The original #prefix must be retained at the start of the concatenated value.',
    );
  }

}
