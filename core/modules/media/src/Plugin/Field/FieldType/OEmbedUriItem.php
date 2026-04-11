<?php

namespace Drupal\media\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\UriItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\media\Plugin\Field\DataType\OEmbedResource;

#[FieldType(
  id: 'oembed_uri',
  label: new TranslatableMarkup("oEmbed URL"),
  description: new TranslatableMarkup("An entity field containing a URI."),
  default_widget: "oembed_textfield",
  default_formatter: "oembed",
//  no_ui: TRUE,
)]
class OEmbedUriItem extends UriItem {

  protected string $returnValue = "resource";

  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['type'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setDescription(new TranslatableMarkup('The type of the media object.'))
      ->setClass(OEmbedResource::class)
      ->setComputed(TRUE);

    $properties['resource'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('OEmbed Resource Object'))
      ->setDescription(new TranslatableMarkup('The oembed resource object.'))
      ->setClass(OEmbedResource::class)
      ->setComputed(TRUE);

    $properties['url'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('OEmbed URL'))
      ->setDescription(new TranslatableMarkup('The resource url.'))
      ->setClass(OEmbedResource::class)
      ->setComputed(TRUE);

    return $properties;
  }

  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();

    $max_length = 100;
    $min_length = min(10, $max_length);

    // The random value is generated multiple times to create a slight
    // preference towards values that are closer to the minimum length of the
    // string.
    $length = mt_rand($min_length, mt_rand($min_length, mt_rand($min_length, $max_length)));
    $values['value'] = $random->word($length);

    $suffix_length = $max_length - 7;
    foreach ($values as $key => $value) {
      $values[$key] = 'http://' . mb_substr($value, 0, $suffix_length) . ".example.com";
    }
    return $values;
  }

}
