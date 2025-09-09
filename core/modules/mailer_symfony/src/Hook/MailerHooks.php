<?php

namespace Drupal\mailer_symfony\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for mailer_symfony.
 */
class MailerHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) : ?string {
    switch ($route_name) {
      case 'help.page.mailer_symfony':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Experimental Mailer module provides an experimental API to build and deliver email messages based on Symfony mailer component. For more information, see the <a href=":mailer">online documentation for the Experimental Mailer module</a>.', [
          ':mailer' => 'https://www.drupal.org/docs/core-modules-and-themes/experimental-extensions/experimental-modules/mailer',
        ]) . '</p>';
        return $output;

      default:
        return NULL;
    }
  }

}
