<?php

declare(strict_types=1);

namespace Drupal\field\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Help hook implementations for field.
 */
class FieldHelpHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RendererInterface $renderer,
    protected readonly ModuleExtensionList $moduleExtensionList,
    #[Autowire(service: 'plugin.manager.field.widget')]
    protected readonly PluginManagerInterface $fieldWidgetManager,
    protected readonly FieldTypePluginManagerInterface $fieldTypeManager,
    #[Autowire(service: 'plugin.manager.field.formatter')]
    protected readonly PluginManagerInterface $fieldFormatterManager,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.field':
        $field_ui_url = $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', ['name' => 'field_ui'])->toString() : '#';
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Field module allows custom data fields to be defined for <em>entity</em> types (see below). The Field module takes care of storing, loading, editing, and rendering field data. Most users will not interact with the Field module directly, but will instead use the <a href=":field-ui-help">Field UI module</a> user interface. Module developers can use the Field API to make new entity types "fieldable" and thus allow fields to be attached to them. For more information, see the <a href=":field">online documentation for the Field module</a>.', [
          ':field-ui-help' => $field_ui_url,
          ':field' => 'https://www.drupal.org/documentation/modules/field',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Terminology') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Entities and entity types') . '</dt>';
        $output .= '<dd>' . $this->t("The website's content and configuration is managed using <em>entities</em>, which are grouped into <em>entity types</em>. <em>Content entity types</em> are the entity types for site content (such as the main site content, comments, content blocks, taxonomy terms, and user accounts). <em>Configuration entity types</em> are used to store configuration information for your site, such as individual views in the Views module, and settings for your main site content types.") . '</dd>';
        $output .= '<dt>' . $this->t('Entity sub-types') . '</dt>';
        $output .= '<dd>' . $this->t('Some content entity types are further grouped into sub-types (for example, you could have article and page content types within the main site content entity type, and tag and category vocabularies within the taxonomy term entity type); other entity types, such as user accounts, do not have sub-types. Programmers use the term <em>bundle</em> for entity sub-types.') . '</dd>';
        $output .= '<dt>' . $this->t('Fields and field types') . '</dt>';
        $output .= '<dd>' . $this->t('Content entity types and sub-types store most of their text, file, and other information in <em>fields</em>. Fields are grouped by <em>field type</em>; field types define what type of data can be stored in that field, such as text, images, or taxonomy term references.') . '</dd>';
        $output .= '<dt>' . $this->t('Formatters and view modes') . '</dd>';
        $output .= '<dd>' . $this->t('Content entity types and sub-types can have one or more <em>view modes</em>, used for displaying the entity items. For instance, a content item could be viewed in full content mode on its own page, teaser mode in a list, or RSS mode in a feed. In each view mode, each field can be hidden or displayed, and if it is displayed, you can choose and configure the <em>formatter</em> that is used to display the field. For instance, a long text field can be displayed trimmed or full-length, and taxonomy term reference fields can be displayed in plain text or linked to the taxonomy term page.') . '</dd>';
        $output .= '<dt>' . $this->t('Widgets and form modes') . '</dd>';
        $output .= '<dd>' . $this->t('Content entity types and sub-types can have one or more <em>form modes</em>, used for editing. For instance, a content item could be edited in a compact format with only some fields editable, or a full format that allows all fields to be edited. In each form mode, each field can be hidden or displayed, and if it is displayed, you can choose and configure the <em>widget</em> that is used to edit the field. For instance, a taxonomy term reference field can be edited using a select list, radio buttons, or an autocomplete widget.') . '</dd>';
        $output .= '</dl>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Enabling field types, widgets, and formatters') . '</dt>';
        $output .= '<dd>' . $this->t('The Field module provides the infrastructure for fields; the field types, formatters, and widgets are provided by Drupal core or additional modules. Some of the modules are required; the optional modules can be installed from the <a href=":modules">Extend administration page</a>. Additional fields, formatters, and widgets may be provided by contributed modules, which you can find in the <a href=":contrib">contributed module section of Drupal.org</a>.', [
          ':modules' => Url::fromRoute('system.modules_list')->toString(),
          ':contrib' => 'https://www.drupal.org/project/project_module',
        ]) . '</dd>';
        $output .= '<h2>' . $this->t('Field, widget, and formatter information') . '</h2>';
        // Make a list of all widget, formatter, and field modules currently
        // enabled, ordered by displayed module name (module names are not
        // translated).
        $items = [];
        $modules = $this->moduleHandler->getModuleList();
        $widgets = $this->fieldWidgetManager->getDefinitions();
        $field_types = $this->fieldTypeManager->getUiDefinitions();
        $formatters = $this->fieldFormatterManager->getDefinitions();
        $providers = [];
        foreach (array_merge($field_types, $widgets, $formatters) as $plugin) {
          $providers[] = $plugin['provider'];
        }
        $providers = array_unique($providers);
        sort($providers);
        foreach ($providers as $provider) {
          // Skip plugins provided by core components as they do not implement
          // hook_help().
          if (isset($modules[$provider])) {
            $display = $this->moduleExtensionList->getName($provider);
            if ($this->moduleHandler->hasImplementations('help', $provider)) {
              $items[] = Link::fromTextAndUrl($display, Url::fromRoute('help.page', ['name' => $provider]))->toRenderable();
            }
            else {
              $items[] = $display;
            }
          }
        }
        if ($items) {
          $output .= '<dt>' . $this->t('Provided by modules') . '</dt>';
          $output .= '<dd>' . $this->t('Here is a list of the currently installed field, formatter, and widget modules:');
          $item_list = ['#theme' => 'item_list', '#items' => $items];
          $output .= $this->renderer->renderInIsolation($item_list);
          $output .= '</dd>';
        }
        $output .= '<dt>' . $this->t('Provided by Drupal core') . '</dt>';
        $output .= '<dd>' . $this->t('As mentioned previously, some field types, widgets, and formatters are provided by Drupal core. Here are some notes on how to use some of these:');
        $output .= '<ul>';
        $output .= '<li><p>' . $this->t('<strong>Entity Reference</strong> fields allow you to create fields that contain links to other entities (such as content items, taxonomy terms, etc.) within the site. This allows you, for example, to include a link to a user within a content item. For more information, see <a href=":er_do">the online documentation for the Entity Reference module</a>.', [':er_do' => 'https://drupal.org/documentation/modules/entityreference']) . '</p>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing and displaying entity reference fields') . '</dt>';
        $output .= '<dd>' . $this->t('The <em>settings</em> and the <em>display</em> of the entity reference field can be configured separately. See the <a href=":field_ui">Field UI help</a> for more information on how to manage fields and their display.', [':field_ui' => $field_ui_url]) . '</dd>';
        $output .= '<dt>' . $this->t('Selecting reference type') . '</dt>';
        $output .= '<dd>' . $this->t('In the field settings you can select which entity type you want to create a reference to.') . '</dd>';
        $output .= '<dt>' . $this->t('Filtering and sorting reference fields') . '</dt>';
        $output .= '<dd>' . $this->t('Depending on the chosen entity type, additional filtering and sorting options are available for the list of entities that can be referred to, in the field settings. For example, the list of users can be filtered by role and sorted by name or ID.') . '</dd>';
        $output .= '<dt>' . $this->t('Displaying a reference') . '</dt>';
        $output .= '<dd>' . $this->t('An entity reference can be displayed as a simple label with or without a link to the entity. Alternatively, the referenced entity can be displayed as a teaser (or any other available view mode) inside the referencing entity.') . '</dd>';
        $output .= '<dt>' . $this->t('Configuring form displays') . '</dt>';
        $output .= '<dd>' . $this->t('Reference fields have several widgets available on the <em>Manage form display</em> page:');
        $output .= '<ul>';
        $output .= '<li>' . $this->t('The <em>Check boxes/radio buttons</em> widget displays the existing entities for the entity type as check boxes or radio buttons based on the <em>Allowed number of values</em> set for the field.') . '</li>';
        $output .= '<li>' . $this->t('The <em>Select list</em> widget displays the existing entities in a drop-down list or scrolling list box based on the <em>Allowed number of values</em> setting for the field.') . '</li>';
        $output .= '<li>' . $this->t('The <em>Autocomplete</em> widget displays text fields in which users can type entity labels based on the <em>Allowed number of values</em>. The widget can be configured to display all entities that contain the typed characters or restricted to those starting with those characters.') . '</li>';
        $output .= '<li>' . $this->t('The <em>Autocomplete (Tags style)</em> widget displays a multi-text field in which users can type in a comma-separated list of entity labels.') . '</li>';
        $output .= '</ul></dd>';
        $output .= '</dl></li>';
        $output .= '<li>' . $this->t('<strong>Number fields</strong>: When you add a number field you can choose from three types: <em>decimal</em>, <em>float</em>, and <em>integer</em>. The <em>decimal</em> number field type allows users to enter exact decimal values, with fixed numbers of decimal places. The <em>float</em> number field type allows users to enter approximate decimal values. The <em>integer</em> number field type allows users to enter whole numbers, such as years (for example, 2012) or values (for example, 1, 2, 5, 305). It does not allow decimals.') . '</li>';
        $output .= '</ul></dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

}
