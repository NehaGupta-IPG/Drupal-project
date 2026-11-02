<?php

declare(strict_types=1);

namespace Drupal\node_preview_embed_test\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\Url;
use Drupal\node\Form\NodePreviewForm;
use Drupal\node\NodeInterface;

/**
 * Embeds a node preview in a page that also contains unrelated links.
 */
class NodePreviewEmbedTestController extends ControllerBase {

  /**
   * The ID of the wrapper the preview is inserted into.
   */
  public const string WRAPPER_ID = 'node-preview-embed-wrapper';

  /**
   * Builds the host page, standing in for the node form.
   *
   * @param \Drupal\node\NodeInterface $node_preview
   *   The node being previewed.
   *
   * @return array
   *   A render array.
   */
  #[Route(
    path: '/node-preview-embed-test/{node_preview}',
    name: 'node_preview_embed_test.host',
    requirements: ['_node_preview_access' => '{node_preview}'],
    options: [
      'no_cache' => TRUE,
      'parameters' => [
        'node_preview' => ['type' => 'node_preview'],
      ],
    ],
  )]
  public function host(NodeInterface $node_preview): array {
    return [
      // Clicking these must not be treated as leaving the preview.
      'outside_link' => [
        '#markup' => '<a href="/outside">Outside link</a>',
      ],
      'trigger' => [
        '#type' => 'link',
        '#title' => 'Embed preview',
        '#url' => Url::fromRoute('node_preview_embed_test.embed', [
          'node_preview' => $node_preview->uuid(),
        ]),
        '#attributes' => ['class' => ['use-ajax']],
      ],
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => ['id' => self::WRAPPER_ID],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.ajax',
          'node/drupal.node.preview',
        ],
      ],
    ];
  }

  /**
   * Replaces the wrapper with the preview.
   *
   * @param \Drupal\node\NodeInterface $node_preview
   *   The node being previewed.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response inserting the preview into the host page.
   */
  #[Route(
    path: '/node-preview-embed-test/{node_preview}/embed',
    name: 'node_preview_embed_test.embed',
    requirements: ['_node_preview_access' => '{node_preview}'],
    options: [
      'no_cache' => TRUE,
      'parameters' => [
        'node_preview' => ['type' => 'node_preview'],
      ],
    ],
  )]
  public function embed(NodeInterface $node_preview): AjaxResponse {
    $node_preview->preview_view_mode = 'full';

    $build = [
      '#type' => 'container',
      '#attributes' => ['id' => self::WRAPPER_ID],
      'preview' => $this->entityTypeManager()
        ->getViewBuilder('node')
        ->view($node_preview),
      // NodePreviewController adds this container through
      // #attached['page_top'], which is not rendered for an AJAX response, so
      // build it here. It is position: fixed, so it goes after the preview to
      // avoid covering the links in it.
      // @see \Drupal\node\Controller\NodePreviewController::view()
      'preview_container' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['node-preview-container', 'container-inline'],
        ],
        'view_mode' => $this->formBuilder()->getForm(NodePreviewForm::class, $node_preview),
      ],
    ];
    // Don't render cache previews.
    unset($build['preview']['#cache']);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . self::WRAPPER_ID, $build));
    return $response;
  }

}
