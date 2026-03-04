<?php

declare(strict_types=1);

namespace Drupal\system\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Template\Event\TwigRenderTemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds template debugging information to the rendered markup.
 */
class TwigDebugSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TwigDebugSubscriber object.
   */
  public function __construct(
    protected TwigEnvironment $twig,
  ) {}

  /**
   * Adds template debugging information when Twig debug mode is enabled.
   *
   * @param \Drupal\Core\Template\Event\TwigRenderTemplateEvent $event
   *   The twig_render_template event.
   */
  public function onTwigRenderTemplate(TwigRenderTemplateEvent $event): void {
    if ($this->twig->isDebug()) {
      $template_file = $event->getTemplateFile();
      $variables = $event->getVariables();

      $output = [
        'debug_prefix' => '',
        'debug_info' => '',
        'rendered_markup' => $event->getRenderedMarkup(),
        'debug_suffix' => '',
      ];

      $output['debug_prefix'] .= "\n\n<!-- THEME DEBUG -->";
      $output['debug_prefix'] .= "\n<!-- THEME HOOK: '" . Html::escape($variables['theme_hook_original']) . "' -->";
      // If there are theme suggestions, reverse the array so more specific
      // suggestions are shown first.
      if (!empty($variables['theme_hook_suggestions'])) {
        $variables['theme_hook_suggestions'] = array_reverse($variables['theme_hook_suggestions']);
      }
      // Add debug output for directly called suggestions like
      // '#theme' => 'comment__node__article'.
      if (str_contains($variables['theme_hook_original'], '__')) {
        $derived_suggestions[] = $hook = $variables['theme_hook_original'];
        while ($pos = strrpos($hook, '__')) {
          $hook = substr($hook, 0, $pos);
          $derived_suggestions[] = $hook;
        }
        // Get the value of the base hook (last derived suggestion) and append
        // it to the end of all theme suggestions.
        $base_hook = array_pop($derived_suggestions);
        $variables['theme_hook_suggestions'] = array_merge($derived_suggestions, $variables['theme_hook_suggestions']);
        $variables['theme_hook_suggestions'][] = $base_hook;
      }
      if (!empty($variables['theme_hook_suggestions'])) {
        $extension = twig_extension();
        $current_template = basename($template_file);
        $suggestions = $variables['theme_hook_suggestions'];
        // Only add the original theme hook if it wasn't a directly called
        // suggestion.
        if (!str_contains($variables['theme_hook_original'], '__')) {
          $suggestions[] = $variables['theme_hook_original'];
        }
        $invalid_suggestions = [];
        $base_hook = $base_hook ?? $variables['theme_hook_original'];
        foreach ($suggestions as $key => &$suggestion) {
          // Valid suggestions are $base_hook, $base_hook__*, and contain no
          // hyphens.
          if (($suggestion !== $base_hook && !str_starts_with($suggestion, $base_hook . '__')) || str_contains($suggestion, '-')) {
            $invalid_suggestions[] = $suggestion;
            unset($suggestions[$key]);
            continue;
          }
          $template = strtr($suggestion, '_', '-') . $extension;
          $prefix = ($template == $current_template) ? '✅' : '▪️';

          // Add deprecated hint.
          if (isset($variables['theme_hook_suggestions__DEPRECATED'][$suggestion])) {
            $template .= ' (deprecated)';
          }
          $suggestion = $prefix . ' ' . $template;
        }
        $output['debug_info'] .= "\n<!-- FILE NAME SUGGESTIONS:\n   " . Html::escape(implode("\n   ", $suggestions)) . "\n-->";

        if (!empty($invalid_suggestions)) {
          $output['debug_info'] .= "\n<!-- INVALID FILE NAME SUGGESTIONS:";
          $output['debug_info'] .= "\n   See https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!theme.api.php/function/hook_theme_suggestions_alter";
          $output['debug_info'] .= "\n   " . Html::escape(implode("\n   ", $invalid_suggestions));
          $output['debug_info'] .= "\n-->";
        }
      }
      // Check if the template_file belongs to a custom theme.
      $template_override_status_output = "BEGIN OUTPUT";
      $template_override_suffix_output = "END OUTPUT";
      if (str_starts_with($template_file, $variables['directory'])) {
        $template_override_status_output = "💡 BEGIN CUSTOM TEMPLATE OUTPUT";
        $template_override_suffix_output = "END CUSTOM TEMPLATE OUTPUT";
      }
      $output['debug_info']   .= "\n<!-- " . $template_override_status_output . " from '" . Html::escape($template_file) . "' -->\n";
      $output['debug_suffix'] .= "\n<!-- " . $template_override_suffix_output . " from '" . Html::escape($template_file) . "' -->\n\n";
      // This output has already been rendered and is therefore considered safe.
      $event->setRenderedMarkup(implode('', $output));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TwigRenderTemplateEvent::class => ['onTwigRenderTemplate'],
    ];
  }

}
