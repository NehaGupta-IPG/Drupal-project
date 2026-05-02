<?php

namespace Drupal\image\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image\ImageEffectManager;

/**
 * Help hook implementations for image.
 */
class ImageHelpHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ImageEffectManager $imageEffectManager,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.image':
        $field_ui_url = $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', ['name' => 'field_ui'])->toString() : '#';
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Image module allows you to create fields that contain image files and to configure <a href=":image_styles">Image styles</a> that can be used to manipulate the display of images. See the <a href=":field">Field module help</a> and the <a href=":field_ui">Field UI help</a> pages for terminology and general information on entities, fields, and how to create and manage fields. For more information, see the <a href=":image_documentation">online documentation for the Image module</a>.', [
          ':image_styles' => Url::fromRoute('entity.image_style.collection')->toString(),
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':field_ui' => $field_ui_url,
          ':image_documentation' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/image-module/working-with-images',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dt>' . $this->t('Defining image styles') . '</dt>';
        $output .= '<dd>' . $this->t('The concept of image styles is that you can upload a single image but display it in several ways; each display variation, or <em>image style</em>, is the result of applying one or more <em>effects</em> to the original image. As an example, you might upload a high-resolution image with a 4:3 aspect ratio, and display it scaled down, square cropped, or black-and-white (or any combination of these effects). The Image module provides a way to do this efficiently: you configure an image style with the desired effects on the <a href=":image">Image styles page</a>, and the first time a particular image is requested in that style, the effects are applied. The resulting image is saved, and the next time that same style is requested, the saved image is retrieved without the need to recalculate the effects. Drupal core provides several effects that you can use to define styles; others may be provided by contributed modules.', [
          ':image' => Url::fromRoute('entity.image_style.collection')->toString(),
        ]);
        $output .= '<dt>' . $this->t('Naming image styles') . '</dt>';
        $output .= '<dd>' . $this->t('When you define an image style, you will need to choose a displayed name and a machine name. The displayed name is shown in administrative pages, and the machine name is used to generate the URL for accessing an image processed in that style. There are two common approaches to naming image styles: either based on the effects being applied (for example, <em>Square 85x85</em>), or based on where you plan to use it (for example, <em>Profile picture</em>).') . '</dd>';
        $output .= '<dt>' . $this->t('Configuring image fields') . '</dt>';
        $output .= '<dd>' . $this->t('A few of the settings for image fields are defined once when you create the field and cannot be changed later; these include the choice of public or private file storage and the number of images that can be stored in the field. The rest of the settings can be edited later; these settings include the field label, help text, allowed file extensions, image dimensions restrictions, and the subdirectory in the public or private file storage where the images will be stored. The editable settings can also have different values for different entity sub-types; for instance, if your image field is used on both Page and Article content types, you can store the files in a different subdirectory for the two content types.') . '</dd>';
        $output .= '<dd>' . $this->t('For accessibility and search engine optimization, all images that convey meaning on websites should have alternate text. Drupal also allows entry of title text for images, but it can lead to confusion for screen reader users and its use is not recommended. Image fields can be configured so that alternate and title text fields are enabled or disabled; if enabled, the fields can be set to be required. The recommended setting is to enable and require alternate text and disable title text.') . '</dd>';
        $output .= '<dd>' . $this->t('When you create an image field, you will need to choose whether the uploaded images will be stored in the public or private file directory defined in your settings.php file and shown on the <a href=":file-system">File system page</a>. This choice cannot be changed later. You can also configure your field to store files in a subdirectory of the public or private directory; this setting can be changed later and can be different for each entity sub-type using the field. For more information on file storage, see the <a href=":system-help">System module help page</a>.', [
          ':file-system' => Url::fromRoute('system.file_system_settings')->toString(),
          ':system-help' => Url::fromRoute('help.page', [
            'name' => 'system',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dd>' . $this->t('The maximum file size that can be uploaded is limited by PHP settings of the server, but you can restrict it further by configuring a <em>Maximum upload size</em> in the field settings (this setting can be changed later). The maximum file size, either from PHP server settings or field configuration, is automatically displayed to users in the help text of the image field.') . '</dd>';
        $output .= '<dd>' . $this->t('You can also configure a minimum and/or maximum dimensions for uploaded images. Images that are too small will be rejected. Images that are to large will be resized. During the resizing the <a href="http://wikipedia.org/wiki/Exchangeable_image_file_format">EXIF data</a> in the image will be lost.') . '</dd>';
        $output .= '<dd>' . $this->t('You can also configure a default image that will be used if no image is uploaded in an image field. This default can be defined for all instances of the field in the field storage settings when you create a field, and the setting can be overridden for each entity sub-type that uses the field.') . '</dd>';
        $output .= '<dt>' . $this->t('Configuring displays and form displays') . '</dt>';
        $output .= '<dd>' . $this->t('On the <em>Manage display</em> page, you can choose the image formatter, which determines the image style used to display the image in each display mode and whether or not to display the image as a link. On the <em>Manage form display</em> page, you can configure the image upload widget, including setting the preview image style shown on the entity edit form.') . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'entity.image_style.collection':
        return '<p>' . $this->t('Image styles commonly provide thumbnail sizes by scaling and cropping images, but can also add various effects before an image is displayed. When an image is displayed with a style, a new file is created and the original image is left unchanged.') . '</p>';

      case 'image.effect_add_form':
        $effect = $this->imageEffectManager->getDefinition($route_match->getParameter('image_effect'));
        return isset($effect['description']) ? '<p>' . $effect['description'] . '</p>' : NULL;

      case 'image.effect_edit_form':
        $effect = $route_match->getParameter('image_style')->getEffect($route_match->getParameter('image_effect'));
        $effect_definition = $effect->getPluginDefinition();
        return isset($effect_definition['description']) ? '<p>' . $effect_definition['description'] . '</p>' : NULL;
    }
    return NULL;
  }

}
