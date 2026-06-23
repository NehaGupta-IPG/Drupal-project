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
 * {@inheritdoc}
 */
#[CoversClass(ManagedFile::class)]
#[Group('file')]
class ManagedFileUploadAjaxCallbackTest extends UnitTestCase {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->renderer = $this->createMock(RendererInterface::class);
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
   * Tests uploadAjaxCallback() when no #prefix is defined.
   */
  public function testUploadAjaxCallbackWithoutPrefix(): void {
    $form = [
      'widget' => [
        'file_upload' => [
          '#type' => 'managed_file',
          '#attached' => [],
        ],
      ],
    ];

    $form_state = new FormState();
    $form_state->set('file_upload_delta_initial', 99);

    $request = Request::create('/file/ajax');
    $request->query->set('element_parents', 'widget/file_upload');
    $request->query->set('form_build_id', 'test_build_id');

    $response = ManagedFile::uploadAjaxCallback($form, $form_state, $request);

    $this->assertNotNull($response, 'uploadAjaxCallback() must return a response.');
  }

  /**
   * Tests uploadAjaxCallback() with an existing #prefix.
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
