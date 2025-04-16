<?php

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'entity reference label' formatter.
 */
#[FieldFormatter(
  id: 'entity_reference_label',
  label: new TranslatableMarkup('Label'),
  description: new TranslatableMarkup('Display the label of the referenced entities.'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceLabelFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['link'] = [
      '#title' => $this->t('Link label to the referenced entity'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('link'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('link') ? $this->t('Link to the referenced entity') : $this->t('No link');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $fibers = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $attributes = $items[$delta]->_attributes ?? NULL;
      // Unset field item attributes since they will be included in the
      // formatter output and shouldn't be rendered in the field template.
      unset($items[$delta]->_attributes);

      $fibers[$delta] = new \Fiber(function() use ($entity, $attributes) {
        return $this->buildEntityElement($entity, $attributes);
      });
    }

	  $iterations = 0;
		while (count($fibers) > 0) {
			foreach ($fibers as $key => $fiber) {
				if (!$fiber->isStarted()) {
					$fiber->start();
				}
				elseif ($fiber->isSuspended()) {
					$fiber->resume();
				}
				// If the Fiber hasn't terminated by this point, move onto the
				// next placeholder, we'll resume this Fiber again when we get
				// back here.
				if (!$fiber->isTerminated()) {
					// If we've gone through the placeholders once already, and
					// they're still not finished, then start to allow code higher
					// up the stack to get on with something else.
					if ($iterations) {
						$fiber = \Fiber::getCurrent();
						if ($fiber !== NULL) {
							$fiber->suspend();
						}
					}
					continue;
				}
				$elements[$key] = $fiber->getReturn();
				unset($fibers[$key]);
			}
    }

    return $elements;
  }

  /**
   * Build the render array for a specific entity.
   */
  protected function buildEntityElement(EntityInterface $entity): array {
    $output_as_link = $this->getSetting('link');
    $build = ['#entity' => $entity];
    $label = $entity->label();
    $cacheability = CacheableMetadata::createFromObject($entity);
    // If the link is to be displayed and the entity has a uri, display a
    // link.
    if ($output_as_link && !$entity->isNew()) {
      try {
        $uri = $entity->toUrl();
      }
      catch (UndefinedLinkTemplateException) {
        // This exception is thrown by
        // \Drupal\Core\Entity\EntityInterface::toUrl() and it means that the
        // entity type doesn't have a link template nor a valid
        // "uri_callback", so don't bother trying to output a link for the
        // rest of the referenced entities.
        $build['#plain_text'] = $label;
        $cacheability->applyTo($build);
        return $build;
      }

      $uri_access = $uri->access(return_as_object: TRUE);
      $cacheability->addCacheableDependency($uri_access);
      if ($uri_access->isAllowed()) {
        $build += [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

      }
      else {
        $build['#plain_text'] = $label;
      }
    }
    else {
      $build['#plain_text'] = $label;
    }

    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }

}
