<?php

namespace Drupal\system\Element;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;

/**
 * Creates status report page element.
 */
#[RenderElement('status_report_page')]
class StatusReportPage extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'status_report_page',
      '#pre_render' => [
        [static::class, 'preRenderCounters'],
        [static::class, 'preRenderGeneralInfo'],
        [static::class, 'preRenderRequirements'],
      ],
    ];
  }

  /**
   * Render API callback: Gets general info out of requirements.
   *
   * This function is assigned as a #pre_render callback.
   */
  public static function preRenderGeneralInfo($element) {
    $element['#general_info'] = [
      '#theme' => 'status_report_general_info',
    ];

    $properties = \Drupal::service('system.site')->getPropertyPlugins();
    foreach ($properties as $id => $property) {
      $element['#general_info']['#properties'][$id] = [
        'label' => $property->label(),
        'view' => $property->view(),
      ];  
    }
    return $element;
  }

  /**
   * The #pre_render callback to create counter elements.
   */
  public static function preRenderCounters($element) {
    // Count number of items with different severity for summary.
    $counters = [
      'error' => [
        'amount' => 0,
        'text' => t('Error'),
        'text_plural' => t('Errors'),
      ],
      'warning' => [
        'amount' => 0,
        'text' => t('Warning'),
        'text_plural' => t('Warnings'),
      ],
      'checked' => [
        'amount' => 0,
        'text' => t('Checked', [], ['context' => 'Examined']),
        'text_plural' => t('Checked', [], ['context' => 'Examined']),
      ],
    ];

    foreach ($element['#requirements'] as $key => &$requirement) {
      $severity = RequirementSeverity::Info;
      if (isset($requirement['severity'])) {
        $severity = $requirement['severity'];
      }
      elseif (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE == 'install') {
        $severity = RequirementSeverity::OK;
      }

      if (isset($counters[$severity->status()])) {
        $counters[$severity->status()]['amount']++;
      }
    }

    foreach ($counters as $key => $counter) {
      if ($counter['amount'] === 0) {
        continue;
      }

      $text = new PluralTranslatableMarkup($counter['amount'], $counter['text'], $counter['text_plural']);

      $element['#counters'][$key] = [
        '#theme' => 'status_report_counter',
        '#amount' => $counter['amount'],
        '#text' => $text,
        '#severity' => $key,
      ];
    }

    return $element;
  }

  /**
   * Render API callback: Create status report requirements.
   *
   * This function is assigned as a #pre_render callback.
   */
  public static function preRenderRequirements($element) {
    $element['#requirements'] = [
      '#type' => 'status_report',
      '#requirements' => $element['#requirements'],
      '#attached' => [
        'library' => [
          'system/status.report',
        ],
      ],
    ];

    return $element;
  }

}
