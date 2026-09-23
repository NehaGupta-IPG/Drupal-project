<?php

namespace Drupal\views\Ajax;

use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom JSON response object for an ajax view response.
 *
 * We use a special response object to be able to fire a proper alter hook.
 */
class ViewAjaxResponse extends CacheableAjaxResponse {

  /**
   * The view executed on this ajax request.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * Sets the executed view of this response.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The View executed on this ajax request.
   */
  public function setView(ViewExecutable $view) {
    $this->view = $view;
  }

  /**
   * Gets the executed view of this response.
   *
   * @return \Drupal\views\ViewExecutable
   *   The View executed on this ajax request.
   */
  public function getView() {
    return $this->view;
  }

  /**
   * Serialize the ViewAjaxResponse object.
   */
  public function __serialize(): array {
    // Get all properties.
    $data = get_object_vars($this);

    // Replace the ViewExecutable by values allowing to rebuild it when
    // unserializing.
    unset($data['view']);
    $data['request'] = serialize($this->view->getRequest());
    $data['view_id'] = $this->view->id();
    $data['display_id'] = $this->view->current_display;

    return $data;
  }

  /**
   * Unserialize the ViewAjaxResponse object.
   */
  public function __unserialize(array $data): void {
    // Rebuild ViewExecutable instance from serialized values.
    \Drupal::requestStack()->push(unserialize($data['request']));
    $this->view = Views::getView($data['view_id']);
    $this->view->setDisplay($data['display_id']);
    \Drupal::requestStack()->pop();

    // Restore other properties.
    unset($data['request'], $data['view_id'], $data['display_id']);

    // Symfony 8.1 deprecated assigning the headers property directly.
    // Restore it through the constructor, which is the recommended approach.
    Response::__construct($data['content'], $data['statusCode'], $data['headers']);
    unset($data['headers']);

    foreach ($data as $key => $value) {
      $this->{$key} = $value;
    }
  }

}
