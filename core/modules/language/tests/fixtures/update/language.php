<?php

/**
 * @file
 * Test fixture to enable language and content translation modules.
 *
 * After system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz is
 * loaded, this file will alter the database to the state of a fresh install of
 * Drupal 9.4.0 with language and content translation modules enabled, including
 * node entity type relevant configuration.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set the schema versions.
$connection->merge('key_value')
  ->fields(['value' => 'i:8001;'])
  ->condition('collection', 'system.schema')
  ->condition('name', 'language')
  ->execute();

// Update core.extension.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();
$extensions = unserialize($extensions);
$extensions['module']['language'] = 0;
$extensions['module']['content_translation'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Add all the removed updates as existing updates.
require_once __DIR__ . '/../../../language.post_update.php';
$existing_updates = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute()
  ->fetchField();
$existing_updates = unserialize($existing_updates);
$existing_updates = array_merge(
  $existing_updates,
  array_keys(language_removed_post_updates())
);
$connection->update('key_value')
  ->fields(['value' => serialize($existing_updates)])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute();

// Update the key/value state.
$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'config.entity.key_store.configurable_language',
    'name' => 'uuid:3cbf115c-f946-41e5-8e04-c0b69a3528dd',
    'value' => 'a:1:{i:0;s:18:"language.entity.en";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.configurable_language',
    'name' => 'uuid:480e2aed-08e8-407f-a1ce-4538747e819b',
    'value' => 'a:1:{i:0;s:19:"language.entity.zxx";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.configurable_language',
    'name' => 'uuid:880c449f-c3d7-476c-ac7a-24ebc1a77142',
    'value' => 'a:1:{i:0;s:19:"language.entity.und";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.language_content_settings',
    'name' => 'uuid:36519ddd-db22-4f46-a96b-43d4320ef4f6',
    'value' => 'a:1:{i:0;s:47:"language.content_settings.path_alias.path_alias";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.tour',
    'name' => 'routes.*.route_name:entity.configurable_language.collection',
    'value' => 'a:1:{i:0;s:18:"tour.tour.language";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.tour',
    'name' => 'routes.*.route_name:entity.configurable_language.edit_form',
    'value' => 'a:1:{i:0;s:23:"tour.tour.language-edit";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.tour',
    'name' => 'routes.*.route_name:language.add',
    'value' => 'a:1:{i:0;s:22:"tour.tour.language-add";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.tour',
    'name' => 'uuid:1a1a6398-5ade-4a73-beba-9b2ebc3649d0',
    'value' => 'a:1:{i:0;s:23:"tour.tour.language-edit";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.tour',
    'name' => 'uuid:2bdf0b5a-9326-43fa-bcf1-6989f9e80b17',
    'value' => 'a:1:{i:0;s:18:"tour.tour.language";}',
  ])
  ->values([
    'collection' => 'config.entity.key_store.tour',
    'name' => 'uuid:6119559d-daae-4505-b1d5-de0630664a04',
    'value' => 'a:1:{i:0;s:22:"tour.tour.language-add";}',
  ])
  ->values([
    'collection' => 'entity.definitions.installed',
    'name' => 'configurable_language.entity_type',
    'value' => "O:42:\"Drupal\Core\Config\Entity\ConfigEntityType\":43:{s:5:\"\0*\0id\";s:21:\"configurable_language\";s:8:\"\0*\0class\";s:43:\"Drupal\language\Entity\ConfigurableLanguage\";s:11:\"\0*\0provider\";s:8:\"language\";s:15:\"\0*\0static_cache\";b:0;s:15:\"\0*\0render_cache\";b:1;s:19:\"\0*\0persistent_cache\";b:1;s:14:\"\0*\0entity_keys\";a:9:{s:2:\"id\";s:2:\"id\";s:5:\"label\";s:5:\"label\";s:6:\"weight\";s:6:\"weight\";s:8:\"revision\";s:0:\"\";s:6:\"bundle\";s:0:\"\";s:8:\"langcode\";s:8:\"langcode\";s:16:\"default_langcode\";s:16:\"default_langcode\";s:29:\"revision_translation_affected\";s:29:\"revision_translation_affected\";s:4:\"uuid\";s:4:\"uuid\";}s:16:\"\0*\0originalClass\";s:43:\"Drupal\language\Entity\ConfigurableLanguage\";s:11:\"\0*\0handlers\";a:4:{s:12:\"list_builder\";s:35:\"Drupal\language\LanguageListBuilder\";s:6:\"access\";s:44:\"Drupal\language\LanguageAccessControlHandler\";s:4:\"form\";a:3:{s:3:\"add\";s:36:\"Drupal\language\Form\LanguageAddForm\";s:4:\"edit\";s:37:\"Drupal\language\Form\LanguageEditForm\";s:6:\"delete\";s:39:\"Drupal\language\Form\LanguageDeleteForm\";}s:7:\"storage\";s:45:\"Drupal\Core\Config\Entity\ConfigEntityStorage\";}s:19:\"\0*\0admin_permission\";s:20:\"administer languages\";s:25:\"\0*\0permission_granularity\";s:11:\"entity_type\";s:8:\"\0*\0links\";a:3:{s:11:\"delete-form\";s:62:\"/admin/config/regional/language/delete/{configurable_language}\";s:9:\"edit-form\";s:60:\"/admin/config/regional/language/edit/{configurable_language}\";s:10:\"collection\";s:31:\"/admin/config/regional/language\";}s:21:\"\0*\0bundle_entity_type\";N;s:12:\"\0*\0bundle_of\";N;s:15:\"\0*\0bundle_label\";N;s:13:\"\0*\0base_table\";N;s:22:\"\0*\0revision_data_table\";N;s:17:\"\0*\0revision_table\";N;s:13:\"\0*\0data_table\";N;s:11:\"\0*\0internal\";b:0;s:15:\"\0*\0translatable\";b:0;s:19:\"\0*\0show_revision_ui\";b:0;s:8:\"\0*\0label\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:8:\"Language\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:19:\"\0*\0label_collection\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:9:\"Languages\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:17:\"\0*\0label_singular\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:8:\"language\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:15:\"\0*\0label_plural\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:9:\"languages\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:14:\"\0*\0label_count\";a:3:{s:8:\"singular\";s:15:\"@count language\";s:6:\"plural\";s:16:\"@count languages\";s:7:\"context\";N;}s:15:\"\0*\0uri_callback\";N;s:8:\"\0*\0group\";s:13:\"configuration\";s:14:\"\0*\0group_label\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:13:\"Configuration\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:1:{s:7:\"context\";s:17:\"Entity type group\";}}s:22:\"\0*\0field_ui_base_route\";N;s:26:\"\0*\0common_reference_target\";b:0;s:22:\"\0*\0list_cache_contexts\";a:0:{}s:18:\"\0*\0list_cache_tags\";a:1:{i:0;s:33:\"config:configurable_language_list\";}s:14:\"\0*\0constraints\";a:0:{}s:13:\"\0*\0additional\";a:0:{}s:14:\"\0*\0_serviceIds\";a:0:{}s:18:\"\0*\0_entityStorages\";a:0:{}s:20:\"\0*\0stringTranslation\";N;s:16:\"\0*\0config_prefix\";s:6:\"entity\";s:14:\"\0*\0lookup_keys\";a:1:{i:0;s:4:\"uuid\";}s:16:\"\0*\0config_export\";a:5:{i:0;s:2:\"id\";i:1;s:5:\"label\";i:2;s:9:\"direction\";i:3;s:6:\"weight\";i:4;s:6:\"locked\";}s:21:\"\0*\0mergedConfigExport\";a:0:{}}",
  ])
  ->values([
    'collection' => 'entity.definitions.installed',
    'name' => 'language_content_settings.entity_type',
    'value' => "O:42:\"Drupal\Core\Config\Entity\ConfigEntityType\":43:{s:5:\"\0*\0id\";s:25:\"language_content_settings\";s:8:\"\0*\0class\";s:46:\"Drupal\language\Entity\ContentLanguageSettings\";s:11:\"\0*\0provider\";s:8:\"language\";s:15:\"\0*\0static_cache\";b:0;s:15:\"\0*\0render_cache\";b:1;s:19:\"\0*\0persistent_cache\";b:1;s:14:\"\0*\0entity_keys\";a:7:{s:2:\"id\";s:2:\"id\";s:8:\"revision\";s:0:\"\";s:6:\"bundle\";s:0:\"\";s:8:\"langcode\";s:8:\"langcode\";s:16:\"default_langcode\";s:16:\"default_langcode\";s:29:\"revision_translation_affected\";s:29:\"revision_translation_affected\";s:4:\"uuid\";s:4:\"uuid\";}s:16:\"\0*\0originalClass\";s:46:\"Drupal\language\Entity\ContentLanguageSettings\";s:11:\"\0*\0handlers\";a:2:{s:6:\"access\";s:45:\"Drupal\Core\Entity\EntityAccessControlHandler\";s:7:\"storage\";s:45:\"Drupal\Core\Config\Entity\ConfigEntityStorage\";}s:19:\"\0*\0admin_permission\";s:20:\"administer languages\";s:25:\"\0*\0permission_granularity\";s:11:\"entity_type\";s:8:\"\0*\0links\";a:0:{}s:21:\"\0*\0bundle_entity_type\";N;s:12:\"\0*\0bundle_of\";N;s:15:\"\0*\0bundle_label\";N;s:13:\"\0*\0base_table\";N;s:22:\"\0*\0revision_data_table\";N;s:17:\"\0*\0revision_table\";N;s:13:\"\0*\0data_table\";N;s:11:\"\0*\0internal\";b:0;s:15:\"\0*\0translatable\";b:0;s:19:\"\0*\0show_revision_ui\";b:0;s:8:\"\0*\0label\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:25:\"Content language settings\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:19:\"\0*\0label_collection\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:25:\"Content language settings\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:17:\"\0*\0label_singular\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:24:\"content language setting\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:15:\"\0*\0label_plural\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:26:\"content languages settings\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:0:{}}s:14:\"\0*\0label_count\";a:3:{s:8:\"singular\";s:31:\"@count content language setting\";s:6:\"plural\";s:33:\"@count content languages settings\";s:7:\"context\";N;}s:15:\"\0*\0uri_callback\";N;s:8:\"\0*\0group\";s:13:\"configuration\";s:14:\"\0*\0group_label\";O:48:\"Drupal\Core\StringTranslation\TranslatableMarkup\":3:{s:9:\"\0*\0string\";s:13:\"Configuration\";s:12:\"\0*\0arguments\";a:0:{}s:10:\"\0*\0options\";a:1:{s:7:\"context\";s:17:\"Entity type group\";}}s:22:\"\0*\0field_ui_base_route\";N;s:26:\"\0*\0common_reference_target\";b:0;s:22:\"\0*\0list_cache_contexts\";a:0:{}s:18:\"\0*\0list_cache_tags\";a:1:{i:0;s:8:\"rendered\";}s:14:\"\0*\0constraints\";a:0:{}s:13:\"\0*\0additional\";a:0:{}s:14:\"\0*\0_serviceIds\";a:0:{}s:18:\"\0*\0_entityStorages\";a:0:{}s:20:\"\0*\0stringTranslation\";N;s:16:\"\0*\0config_prefix\";s:16:\"content_settings\";s:14:\"\0*\0lookup_keys\";a:1:{i:0;s:4:\"uuid\";}s:16:\"\0*\0config_export\";a:5:{i:0;s:2:\"id\";i:1;s:21:\"target_entity_type_id\";i:2;s:13:\"target_bundle\";i:3;s:16:\"default_langcode\";i:4;s:18:\"language_alterable\";}s:21:\"\0*\0mergedConfigExport\";a:0:{}}",
  ])
  ->execute();

// cSpell:disable
// Install the default configuration.
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.content_settings.path_alias.path_alias',
    'data' => 'a:10:{s:4:"uuid";s:36:"36519ddd-db22-4f46-a96b-43d4320ef4f6";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:10:"path_alias";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"dhjFABRzm52DkRXnYja5ZoHwjY0R3gEGic3KpDUXgRo";}s:2:"id";s:21:"path_alias.path_alias";s:21:"target_entity_type_id";s:10:"path_alias";s:13:"target_bundle";s:10:"path_alias";s:16:"default_langcode";s:3:"und";s:18:"language_alterable";b:1;}',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.entity.en',
    'data' => 'a:10:{s:4:"uuid";s:36:"3cbf115c-f946-41e5-8e04-c0b69a3528dd";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"lBXDpdDPXQtrfTJQhr6MjRJJEEyYSoRJ0acdvHLsWeA";}s:2:"id";s:2:"en";s:5:"label";s:7:"English";s:9:"direction";s:3:"ltr";s:6:"weight";i:0;s:6:"locked";b:0;}',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.entity.und',
    'data' => 'a:10:{s:4:"uuid";s:36:"880c449f-c3d7-476c-ac7a-24ebc1a77142";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"eNX6lLCKDaY83nCMh20My---y03KbiFlv802DKCCpvg";}s:2:"id";s:3:"und";s:5:"label";s:13:"Not specified";s:9:"direction";s:3:"ltr";s:6:"weight";i:2;s:6:"locked";b:1;}',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.entity.zxx',
    'data' => 'a:10:{s:4:"uuid";s:36:"480e2aed-08e8-407f-a1ce-4538747e819b";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"35CefWbnzaiytcg3acexxz_GTvuwIjYd_ZTcmmR-tXA";}s:2:"id";s:3:"zxx";s:5:"label";s:14:"Not applicable";s:9:"direction";s:3:"ltr";s:6:"weight";i:3;s:6:"locked";b:1;}',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.mappings',
    'data' => 'a:2:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"EMWe7Yu4Q5eD-NUfNuQAWGBvYUNZPIinztEtONSmsDc";}s:3:"map";a:10:{s:2:"no";s:2:"nb";s:2:"pt";s:5:"pt-pt";s:2:"zh";s:7:"zh-hans";s:5:"zh-tw";s:7:"zh-hant";s:5:"zh-hk";s:7:"zh-hant";s:5:"zh-mo";s:7:"zh-hant";s:6:"zh-cht";s:7:"zh-hant";s:5:"zh-cn";s:7:"zh-hans";s:5:"zh-sg";s:7:"zh-hans";s:6:"zh-chs";s:7:"zh-hans";}}',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.negotiation',
    'data' => 'a:4:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"uEePITI9tV6WqzmsTb7MfPCi5yPWXSxAN1xeLcYFQbM";}s:7:"session";a:1:{s:9:"parameter";s:8:"language";}s:3:"url";a:3:{s:6:"source";s:11:"path_prefix";s:8:"prefixes";a:1:{s:2:"en";s:0:"";}s:7:"domains";a:1:{s:2:"en";s:0:"";}}s:17:"selected_langcode";s:12:"site_default";}',
  ])
  ->values([
    'collection' => '',
    'name' => 'language.types',
    'data' => 'a:4:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"dqouFqVseNJNvEjsoYKxbinFOITuCxYhi4y2OTNQP_8";}s:3:"all";a:3:{i:0;s:18:"language_interface";i:1;s:16:"language_content";i:2;s:12:"language_url";}s:12:"configurable";a:1:{i:0;s:18:"language_interface";}s:11:"negotiation";a:3:{s:16:"language_content";a:1:{s:7:"enabled";a:1:{s:18:"language-interface";i:0;}}s:12:"language_url";a:1:{s:7:"enabled";a:2:{s:12:"language-url";i:0;s:21:"language-url-fallback";i:1;}}s:18:"language_interface";a:1:{s:7:"enabled";a:1:{s:12:"language-url";i:0;}}}}',
  ])
  ->execute();

// Update node configuration.
$connection->merge('config')
  ->fields(['data' => 'a:11:{s:4:"uuid";s:36:"36ba1669-8b37-4583-b0c1-f096ad81c57e";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:7:{i:0;s:48:"core.entity_view_display.comment.comment.default";i:1;s:29:"field.field.node.article.body";i:2;s:32:"field.field.node.article.comment";i:3;s:36:"field.field.node.article.field_image";i:4;s:35:"field.field.node.article.field_tags";i:5;s:16:"image.style.wide";i:6;s:17:"node.type.article";}s:6:"module";a:4:{i:0;s:7:"comment";i:1;s:5:"image";i:2;s:4:"text";i:3;s:4:"user";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"7fb0j9HnIXfP9ndewzgPPzzeX_SV532wuYpkscrCMCk";}s:2:"id";s:20:"node.article.default";s:16:"targetEntityType";s:4:"node";s:6:"bundle";s:7:"article";s:4:"mode";s:7:"default";s:7:"content";a:5:{s:4:"body";a:6:{s:4:"type";s:12:"text_default";s:5:"label";s:6:"hidden";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}s:6:"weight";i:0;s:6:"region";s:7:"content";}s:7:"comment";a:6:{s:4:"type";s:15:"comment_default";s:5:"label";s:5:"above";s:8:"settings";a:2:{s:9:"view_mode";s:7:"default";s:8:"pager_id";i:0;}s:20:"third_party_settings";a:0:{}s:6:"weight";i:110;s:6:"region";s:7:"content";}s:11:"field_image";a:6:{s:4:"type";s:5:"image";s:5:"label";s:6:"hidden";s:8:"settings";a:3:{s:10:"image_link";s:0:"";s:11:"image_style";s:4:"wide";s:13:"image_loading";a:1:{s:9:"attribute";s:4:"lazy";}}s:20:"third_party_settings";a:0:{}s:6:"weight";i:-1;s:6:"region";s:7:"content";}s:10:"field_tags";a:6:{s:4:"type";s:22:"entity_reference_label";s:5:"label";s:5:"above";s:8:"settings";a:1:{s:4:"link";b:1;}s:20:"third_party_settings";a:0:{}s:6:"weight";i:10;s:6:"region";s:7:"content";}s:5:"links";a:4:{s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}s:6:"weight";i:100;s:6:"region";s:7:"content";}}s:6:"hidden";a:1:{s:8:"langcode";b:1;}}'])
  ->condition('name', 'core.entity_view_display.node.article.default')
  ->execute();
$connection->merge('config')
  ->fields(['data' => 'a:11:{s:4:"uuid";s:36:"abd70880-e072-4557-b23d-41ef2c4cbe47";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:6:{i:0;s:30:"core.entity_view_mode.node.rss";i:1;s:29:"field.field.node.article.body";i:2;s:32:"field.field.node.article.comment";i:3;s:36:"field.field.node.article.field_image";i:4;s:35:"field.field.node.article.field_tags";i:5;s:17:"node.type.article";}s:6:"module";a:1:{i:0;s:4:"user";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"2rIr6K5Q0UQ9khg0zE_CK-PtJH76UL-BDDZcZnZzwCc";}s:2:"id";s:16:"node.article.rss";s:16:"targetEntityType";s:4:"node";s:6:"bundle";s:7:"article";s:4:"mode";s:3:"rss";s:7:"content";a:1:{s:5:"links";a:2:{s:6:"weight";i:100;s:6:"region";s:7:"content";}}s:6:"hidden";a:5:{s:4:"body";b:1;s:7:"comment";b:1;s:11:"field_image";b:1;s:10:"field_tags";b:1;s:8:"langcode";b:1;}}'])
  ->condition('name', 'core.entity_view_display.node.article.rss')
  ->execute();
$connection->merge('config')
  ->fields(['data' => 'a:11:{s:4:"uuid";s:36:"266be0ef-d33b-46fd-91dc-2369ccef93f2";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:7:{i:0;s:33:"core.entity_view_mode.node.teaser";i:1;s:29:"field.field.node.article.body";i:2;s:32:"field.field.node.article.comment";i:3;s:36:"field.field.node.article.field_image";i:4;s:35:"field.field.node.article.field_tags";i:5;s:18:"image.style.medium";i:6;s:17:"node.type.article";}s:6:"module";a:3:{i:0;s:5:"image";i:1;s:4:"text";i:2;s:4:"user";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"83jX5ChAGdMzOxiDA_B1NjgebVMuD8lNVDgClg_QVP8";}s:2:"id";s:19:"node.article.teaser";s:16:"targetEntityType";s:4:"node";s:6:"bundle";s:7:"article";s:4:"mode";s:6:"teaser";s:7:"content";a:4:{s:4:"body";a:6:{s:4:"type";s:23:"text_summary_or_trimmed";s:5:"label";s:6:"hidden";s:8:"settings";a:1:{s:11:"trim_length";i:600;}s:20:"third_party_settings";a:0:{}s:6:"weight";i:0;s:6:"region";s:7:"content";}s:11:"field_image";a:6:{s:4:"type";s:5:"image";s:5:"label";s:6:"hidden";s:8:"settings";a:3:{s:10:"image_link";s:7:"content";s:11:"image_style";s:6:"medium";s:13:"image_loading";a:1:{s:9:"attribute";s:4:"lazy";}}s:20:"third_party_settings";a:0:{}s:6:"weight";i:-1;s:6:"region";s:7:"content";}s:10:"field_tags";a:6:{s:4:"type";s:22:"entity_reference_label";s:5:"label";s:5:"above";s:8:"settings";a:1:{s:4:"link";b:1;}s:20:"third_party_settings";a:0:{}s:6:"weight";i:10;s:6:"region";s:7:"content";}s:5:"links";a:2:{s:6:"weight";i:100;s:6:"region";s:7:"content";}}s:6:"hidden";a:4:{s:7:"comment";b:1;s:11:"field_image";b:1;s:10:"field_tags";b:1;s:8:"langcode";b:1;}}'])
  ->condition('name', 'core.entity_view_display.node.article.teaser')
  ->execute();
$connection->merge('config')
  ->fields(['data' => 'a:11:{s:4:"uuid";s:36:"cb05b577-489d-47d8-8d61-559b780e335b";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:26:"field.field.node.page.body";i:1;s:14:"node.type.page";}s:6:"module";a:2:{i:0;s:4:"text";i:1;s:4:"user";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"M_Y8L5tfmhx7DR143E05YyZSpvgil6VFvqcfBWykalg";}s:2:"id";s:17:"node.page.default";s:16:"targetEntityType";s:4:"node";s:6:"bundle";s:4:"page";s:4:"mode";s:7:"default";s:7:"content";a:2:{s:4:"body";a:6:{s:4:"type";s:12:"text_default";s:5:"label";s:6:"hidden";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}s:6:"weight";i:100;s:6:"region";s:7:"content";}s:5:"links";a:2:{s:6:"weight";i:101;s:6:"region";s:7:"content";}}s:6:"hidden";a:1:{s:8:"langcode";b:1;}}'])
  ->condition('name', 'core.entity_view_display.node.page.default')
  ->execute();
$connection->merge('config')
  ->fields(['data' => 'a:11:{s:4:"uuid";s:36:"49f85577-6356-4be0-9816-044aed35c9c4";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:3:{i:0;s:33:"core.entity_view_mode.node.teaser";i:1;s:26:"field.field.node.page.body";i:2;s:14:"node.type.page";}s:6:"module";a:2:{i:0;s:4:"text";i:1;s:4:"user";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"8BgdRtLbtQ0F__o0FHSH0Mx5fvXOra9tfT1GmNKbRYw";}s:2:"id";s:16:"node.page.teaser";s:16:"targetEntityType";s:4:"node";s:6:"bundle";s:4:"page";s:4:"mode";s:6:"teaser";s:7:"content";a:2:{s:4:"body";a:6:{s:4:"type";s:23:"text_summary_or_trimmed";s:5:"label";s:6:"hidden";s:8:"settings";a:1:{s:11:"trim_length";i:600;}s:20:"third_party_settings";a:0:{}s:6:"weight";i:100;s:6:"region";s:7:"content";}s:5:"links";a:2:{s:6:"weight";i:101;s:6:"region";s:7:"content";}}s:6:"hidden";a:1:{s:8:"langcode";b:1;}}'])
  ->condition('name', 'core.entity_view_display.node.page.teaser')
  ->execute();
// cSpell:enable
