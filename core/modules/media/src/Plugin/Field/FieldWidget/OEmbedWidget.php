<?php

namespace Drupal\media\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\ElementInterface;
use Drupal\Core\Render\Element\Form;
use Drupal\Core\Render\Element\Wrapper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\Entity\MediaType;
use Drupal\media\Plugin\media\Source\OEmbedInterface;

/**
 * Plugin implementation of the 'oembed_textfield' widget.
 *
 * @internal
 *   This is an internal part of the oEmbed system and should only be used by
 *   oEmbed-related code in Drupal core.
 */
#[FieldWidget(
  id: 'oembed_textfield',
  label: new TranslatableMarkup('oEmbed URL'),
  field_types: ['string'],
)]
class OEmbedWidget extends StringTextfieldWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['value']['#description'] = $this->getValueDescription($items, $element['value']['#description'] ?? NULL);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementObject(FieldItemListInterface $items, $delta, Wrapper $wrapper, Form $form, FormStateInterface $form_state): ElementInterface {
    $wrapper = parent::singleElementObject($items, $delta, $wrapper, $form, $form_state);
    $value = $wrapper->getChild('value');
    $value->description = $this->getValueDescription($items, $value->description);
    return $wrapper;
  }

  /**
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   FieldItemList containing the values to be edited.
   * @param scalar|\Stringable|\Drupal\Core\Render\RenderableInterface|array $description
   *   The description on the form element.
   *
   * @return string|array
   *   The description on the value child.
   */
  protected function getValueDescription(FieldItemListInterface $items, mixed $description): string|array {
    /** @var \Drupal\media\Plugin\media\Source\OEmbedInterface $source */
    $source = $items->getEntity()->getSource();
    $message = $this->t('You can link to media from the following services: @providers', ['@providers' => implode(', ', $source->getProviders())]);
    if ($description) {
      return [
        '#theme' => 'item_list',
        '#items' => [$description, $message],
      ];
    }
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $target_bundle = $field_definition->getTargetBundle();

    if (!parent::isApplicable($field_definition) || $field_definition->getTargetEntityTypeId() !== 'media' || !$target_bundle) {
      return FALSE;
    }
    return MediaType::load($target_bundle)->getSource() instanceof OEmbedInterface;
  }

}
