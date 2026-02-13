<?php

namespace Drupal\big_pipe\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for big_pipe.
 */
class BigPipeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.big_pipe':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The BigPipe module sends pages with dynamic content in a way that allows browsers to show them much faster. For more information, see the <a href=":big_pipe-documentation">online documentation for the BigPipe module</a>.', [
          ':big_pipe-documentation' => 'https://www.drupal.org/documentation/modules/big_pipe',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Speeding up your site') . '</dt>';
        $output .= '<dd>' . $this->t('The module requires no configuration. Every part of the page contains metadata that allows BigPipe to figure this out on its own.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return [
      'big_pipe_interface_preview' => [
        'variables' => [
          'callback' => NULL,
          'arguments' => NULL,
          'preview' => NULL,
        ],
      ],
    ];
  }

}
