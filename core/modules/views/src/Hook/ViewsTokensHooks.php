<?php

namespace Drupal\views\Hook;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for views.
 */
class ViewsTokensHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info['types']['view'] = [
      'name' => $this->t('View', [], [
        'context' => 'View entity type',
      ]),
      'description' => $this->t('Tokens related to views.'),
      'needs-data' => 'view',
    ];
    $info['tokens']['view']['label'] = [
      'name' => $this->t('Label'),
      'description' => $this->t('The label of the view.'),
    ];
    $info['tokens']['view']['description'] = [
      'name' => $this->t('Description'),
      'description' => $this->t('The description of the view.'),
    ];
    $info['tokens']['view']['id'] = [
      'name' => $this->t('ID'),
      'description' => $this->t('The machine-readable ID of the view.'),
    ];
    $info['tokens']['view']['title'] = [
      'name' => $this->t('Title'),
      'description' => $this->t('The title of current display of the view.'),
    ];
    $info['tokens']['view']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the view.'),
      'type' => 'url',
    ];
    $info['tokens']['view']['base-table'] = [
      'name' => $this->t('Base table'),
      'description' => $this->t('The base table used for this view.'),
    ];
    $info['tokens']['view']['base-field'] = [
      'name' => $this->t('Base field'),
      'description' => $this->t('The base field used for this view.'),
    ];
    $info['tokens']['view']['total-rows'] = [
      'name' => $this->t('Total rows'),
      'description' => $this->t('The total amount of results returned from the view. The current display will be used.'),
    ];
    $info['tokens']['view']['items-per-page'] = [
      'name' => $this->t('Items per page'),
      'description' => $this->t('The number of items per page.'),
    ];
    $info['tokens']['view']['current-page'] = [
      'name' => $this->t('Current page'),
      'description' => $this->t('The current page of results the view is on.'),
    ];
    $info['tokens']['view']['page-count'] = [
      'name' => $this->t('Page count'),
      'description' => $this->t('The total page count.'),
    ];
    $info['tokens']['view']['start'] = [
      'name' => $this->t('Start'),
      'description' => $this->t('The initial record number in the set.'),
    ];
    $info['tokens']['view']['end'] = [
      'name' => $this->t('End'),
      'description' => $this->t('The last record number in the set.'),
    ];
    $info['tokens']['view']['current_record_count'] = [
      'name' => $this->t('Current record count'),
      'description' => $this->t('The current page record count.'),
    ];
    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $url_options = ['absolute' => TRUE];
    if (isset($options['language'])) {
      $url_options['language'] = $options['language'];
    }
    $replacements = [];
    if ($type == 'view' && !empty($data['view'])) {
      /** @var \Drupal\views\ViewExecutable $view */
      $view = $data['view'];
      $bubbleable_metadata->addCacheableDependency($view->storage);
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'label':
            $replacements[$original] = $view->storage->label();
            break;

          case 'description':
            $replacements[$original] = $view->storage->get('description');
            break;

          case 'id':
            $replacements[$original] = $view->storage->id();
            break;

          case 'title':
            $title = $view->getTitle();
            $replacements[$original] = $title;
            break;

          case 'url':
            try {
              if ($url = $view->getUrl()) {
                $replacements[$original] = $url->setOptions($url_options)->toString();
              }
            }
            catch (\InvalidArgumentException) {
              // The view has no URL so we leave the value empty.
              $replacements[$original] = '';
            }
            break;

          case 'base-table':
            $replacements[$original] = $view->storage->get('base_table');
            break;

          case 'base-field':
            $replacements[$original] = $view->storage->get('base_field');
            break;

          case 'total-rows':
            $replacements[$original] = (int) $view->total_rows;
            break;

          case 'items-per-page':
            $replacements[$original] = (int) $view->getItemsPerPage();
            break;

          case 'current-page':
            $replacements[$original] = (int) $view->getCurrentPage() + 1;
            break;

          case 'page-count':
            // If there are no items per page, set this to 1 for the division.
            $per_page = $view->getItemsPerPage() ?: 1;
            $replacements[$original] = max(1, (int) ceil($view->total_rows / $per_page));
            break;

          case 'start':
            $per_page = (int) $view->getItemsPerPage();
            $total = $view->total_rows ?? count($view->result);
            $start_offset = empty($total) ? 0 : 1;
            if ($per_page === 0) {
              $replacements[$original] = $start_offset;
            }
            else {
              $current_page = (int) $view->getCurrentPage() + 1;
              $replacements[$original] = ($current_page - 1) * $per_page + $start_offset;
            }
            break;

          case 'end':
            // Calculate the page totals.
            $current_page = (int) $view->getCurrentPage() + 1;
            $per_page = (int) $view->getItemsPerPage();
            $total = $view->total_rows ?? count($view->result);
            if ($per_page === 0) {
              $replacements[$original] = $total;
            }
            else {
              $total_count = $current_page * $per_page;
              if ($total_count > $total) {
                $total_count = $total;
              }
              $replacements[$original] = $total_count;
            }
            break;

          case 'current_record_count':
            // Calculate the page totals.
            $current_page = (int) $view->getCurrentPage() + 1;
            $per_page = (int) $view->getItemsPerPage();
            $total = $view->total_rows ?? count($view->result);
            $start_offset = empty($total) ? 0 : 1;
            if ($per_page === 0) {
              $start = $start_offset;
              $end = $total;
            }
            else {
              $total_count = $current_page * $per_page;
              if ($total_count > $total) {
                $total_count = $total;
              }
              $start = ($current_page - 1) * $per_page + $start_offset;
              $end = $total_count;
            }
            $replacements[$original] = ($end - $start) + $start_offset;

            break;
        }
      }
    }
    return $replacements;
  }

}
