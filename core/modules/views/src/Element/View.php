<?php

namespace Drupal\views\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\views\ContextualLinksHelper;
use Drupal\views\Exception\ViewRenderElementException;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Provides a render element to display a view.
 */
#[RenderElement('view')]
class View extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderViewElement'],
      ],
      '#name' => NULL,
      '#display_id' => 'default',
      '#arguments' => [],
      '#embed' => TRUE,
      '#cache' => [],
    ];
  }

  /**
   * View element pre render callback.
   */
  public static function preRenderViewElement($element) {
    // Allow specific Views displays to explicitly perform pre-rendering, for
    // those displays that need to be able to know the fully built render array.
    if (!empty($element['#pre_rendered'])) {
      return $element;
    }

    if (!isset($element['#view'])) {
      $view = Views::getView($element['#name']);
      if (!$view) {
        throw new ViewRenderElementException("Invalid View name ({$element['#name']}) given.");
      }
    }
    else {
      $view = $element['#view'];
    }

    $element += $view->element;
    $view->element = &$element;
    // Mark the element as being prerendered, so other code like
    // \Drupal\views\ViewExecutable::setCurrentPage knows that its no longer
    // possible to manipulate the $element.
    $view->element['#pre_rendered'] = TRUE;

    // Assigned before the view is built so that anything rendered inside it,
    // the exposed form in particular, can refer to the element that htmx
    // replaces.
    // @see static::htmxScrollModifier()
    $element['#attributes']['id'] = $element['#attributes']['id'] ?? Html::getId('view-dom-id-' . $element['#name'] . '-' . $element['#display_id'] . '-wrapper');

    if (isset($element['#response'])) {
      $view->setResponse($element['#response']);
    }

    if ($view && $view->access($element['#display_id'])) {
      if (!empty($element['#embed'])) {
        $element['view_build'] = $view->preview($element['#display_id'], $element['#arguments']);
      }
      else {
        // Add contextual links to the view. We need to attach them to the dummy
        // $view_array variable, since contextual_preprocess() requires that
        // they be attached to an array (not an object) in order to process
        // them. For our purposes, it doesn't matter what we attach them to,
        // since once they are processed by contextual_preprocess() they will
        // appear in the $title_suffix variable (which we will then render in
        // views-view.html.twig).
        $view->setDisplay($element['#display_id']);
        // Add the result of the executed view as a child element so any
        // #pre_render elements for the view will get processed. A #pre_render
        // element cannot be added to the main element as this is already inside
        // a #pre_render callback.
        $element['view_build'] = $view->executeDisplay($element['#display_id'], $element['#arguments']);

        if (isset($element['view_build']['#title'])) {
          $element['#title'] = &$element['view_build']['#title'];
        }

        if (empty($view->display_handler->getPluginDefinition()['returns_response'])) {
          // \Drupal\views\ContextualLinksHelper::addLinks() needs the following
          // information to be attached to the view.
          $element['#view_id'] = $view->storage->id();
          $element['#view_display_show_admin_links'] = $view->getShowAdminLinks();
          $element['#view_display_plugin_id'] = $view->display_handler->getPluginId();
          \Drupal::service(ContextualLinksHelper::class)->addLinks($element, 'view', $view->current_display);
        }
      }
      if (empty($view->display_handler->getPluginDefinition()['returns_response'])) {
        $element['#attributes']['class'][] = 'views-element-container';
        $element['#theme_wrappers'] = ['container'];
      }
    }

    (new Htmx())
      ->swapOob(TRUE)
      ->applyTo($element);

    return $element;
  }

  /**
   * Returns an hx-swap modifier that scrolls to the top of a rendered view.
   *
   * Htmx scrolls to the top of the page after a boosted request, on the
   * reasoning that a boost stands in for an ordinary navigation. Only the view
   * is replaced here, so the page is taken to the top of the view instead,
   * which is where the Ajax implementation leaves it as well.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view being rendered.
   *
   * @return string
   *   The modifier for \Drupal\Core\Htmx\Htmx::swap(), or an empty string when
   *   the view is not wrapped by this element and so has nothing to scroll to.
   *
   * @see \Drupal\views\Controller\ViewAjaxController::ajaxView()
   * @see https://htmx.org/attributes/hx-swap/
   */
  public static function htmxScrollModifier(ViewExecutable $view): string {
    $id = $view->element['#attributes']['id'] ?? NULL;
    return $id === NULL ? '' : 'show:#' . $id . ':top';
  }

}
