<?php

namespace Drupal\taxonomy\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Help hook implementation for taxonomy().
 */
class TaxonomyHelpHooks {

  use StringTranslationTrait;

  public function __construct(protected ModuleHandlerInterface $moduleHandler) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      case 'help.page.taxonomy':
        $field_ui_url = $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', ['name' => 'field_ui'])->toString() : '#';
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Taxonomy module allows users who have permission to create and edit content to categorize (tag) content of that type. Users who have the <em>Administer vocabularies and terms</em> <a href=":permissions" title="Taxonomy module permissions">permission</a> can add <em>vocabularies</em> that contain a set of related <em>terms</em>. The terms in a vocabulary can either be pre-set by an administrator or built gradually as content is added and edited. Terms may be organized hierarchically if desired.', [
          ':permissions' => Url::fromRoute('user.admin_permissions.module', [
            'modules' => 'taxonomy',
          ])->toString(),
        ]) . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":taxonomy">online documentation for the Taxonomy module</a>.', [':taxonomy' => 'https://www.drupal.org/docs/8/core/modules/taxonomy']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Managing vocabularies') . '</dt>';
        $output .= '<dd>' . $this->t('Users who have the <em>Administer vocabularies and terms</em> permission can add and edit vocabularies from the <a href=":taxonomy_admin">Taxonomy administration page</a>. Vocabularies can be deleted from their <em>Edit vocabulary</em> page. Users with the <em>Taxonomy term: Administer fields</em> permission may add additional fields for terms in that vocabulary using the <a href=":field_ui">Field UI module</a>.', [
          ':taxonomy_admin' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
          ':field_ui' => $field_ui_url,
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Managing terms') . '</dt>';
        $output .= '<dd>' . $this->t('Users who have the <em>Administer vocabularies and terms</em> permission or the <em>Edit terms</em> permission for a particular vocabulary can add, edit, and organize the terms in a vocabulary from a vocabulary\'s term listing page, which can be accessed by going to the <a href=":taxonomy_admin">Taxonomy administration page</a> and clicking <em>List terms</em> in the <em>Operations</em> column. Users must have the <em>Administer vocabularies and terms</em> permission or the <em>Delete terms</em> permission for a particular vocabulary to delete terms.', [
          ':taxonomy_admin' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
        ]) . ' </dd>';
        $output .= '<dt>' . $this->t('Classifying entity content') . '</dt>';
        $output .= '<dd>' . $this->t('A user with the <em>Administer fields</em> permission for a certain entity type may add <em>Taxonomy term</em> reference fields to the entity type, which will allow entities to be classified using taxonomy terms. See the <a href=":entity_reference">Entity Reference help</a> for more information about reference fields. See the <a href=":field">Field module help</a> and the <a href=":field_ui">Field UI help</a> pages for general information on fields and how to create and manage them.', [
          ':field_ui' => $field_ui_url,
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':entity_reference' => Url::fromRoute('help.page', [
            'name' => 'entity_reference',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Adding new terms during content creation') . '</dt>';
        $output .= '<dd>' . $this->t("Allowing users to add new terms gradually builds a vocabulary as content is added and edited. Users can add new terms if either of the two <em>Autocomplete</em> widgets is chosen for the Taxonomy term reference field in the <em>Manage form display</em> page for the field. You will also need to enable the <em>Create referenced entities if they don't already exist</em> option, and restrict the field to one vocabulary.") . '</dd>';
        $output .= '<dt>' . $this->t('Configuring displays and form displays') . '</dt>';
        $output .= '<dd>' . $this->t('See the <a href=":entity_reference">Entity Reference help</a> page for the field widgets and formatters that can be configured for any reference field on the <em>Manage display</em> and <em>Manage form display</em> pages. Taxonomy additionally provides an <em>RSS category</em> formatter that displays nothing when the entity item is displayed as HTML, but displays an RSS category instead of a list when the entity item is displayed in an RSS feed.', [
          ':entity_reference' => Url::fromRoute('help.page', [
            'name' => 'entity_reference',
          ])->toString(),
        ]) . '</li>';
        $output .= '</ul>';
        $output .= '</dd>';
        $output .= '</dl>';
        return $output;

      case 'entity.taxonomy_vocabulary.collection':
        $output = '<p>' . $this->t('Taxonomy is for categorizing content. Terms are grouped into vocabularies. For example, a vocabulary called "Fruit" would contain the terms "Apple" and "Banana".') . '</p>';
        return $output;

      default:
        $output = '';
        return $output;
    }
  }

}
