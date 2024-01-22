<?php

/**
 * @file
 * Partial database to create broken config overrides.
 *
 * @see \Drupal\system\Tests\Update\ConfigOverridesUpdateTest
 */

use Drupal\Core\Database\Database;

// cSpell:ignore Aplicar Reiniciar Ordenar acceso Último

$connection = Database::getConnection();

$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'views.view.spanish_view',
    'data' => "a:12:{s:4:\"uuid\";s:36:\"6ee816aa-4a08-4180-b87d-f05ec147a726\";s:8:\"langcode\";s:2:\"es\";s:6:\"status\";b:1;s:12:\"dependencies\";a:1:{s:6:\"module\";a:2:{i:0;s:4:\"node\";i:1;s:4:\"user\";}}s:2:\"id\";s:12:\"spanish_view\";s:5:\"label\";s:7:\"Maestro\";s:6:\"module\";s:5:\"views\";s:11:\"description\";s:0:\"\";s:3:\"tag\";s:0:\"\";s:10:\"base_table\";s:15:\"node_field_data\";s:10:\"base_field\";s:3:\"nid\";s:7:\"display\";a:2:{s:7:\"default\";a:6:{s:2:\"id\";s:7:\"default\";s:13:\"display_title\";s:7:\"Maestro\";s:14:\"display_plugin\";s:7:\"default\";s:8:\"position\";i:0;s:15:\"display_options\";a:17:{s:5:\"title\";s:12:\"Spanish View\";s:6:\"fields\";a:1:{s:5:\"title\";a:35:{s:2:\"id\";s:5:\"title\";s:5:\"table\";s:15:\"node_field_data\";s:5:\"field\";s:5:\"title\";s:12:\"relationship\";s:4:\"none\";s:10:\"group_type\";s:5:\"group\";s:11:\"admin_label\";s:0:\"\";s:9:\"plugin_id\";s:5:\"field\";s:5:\"label\";s:0:\"\";s:7:\"exclude\";b:0;s:5:\"alter\";a:26:{s:10:\"alter_text\";b:0;s:4:\"text\";s:0:\"\";s:9:\"make_link\";b:0;s:4:\"path\";s:0:\"\";s:8:\"absolute\";b:0;s:8:\"external\";b:0;s:14:\"replace_spaces\";b:0;s:9:\"path_case\";s:4:\"none\";s:15:\"trim_whitespace\";b:0;s:3:\"alt\";s:0:\"\";s:3:\"rel\";s:0:\"\";s:10:\"link_class\";s:0:\"\";s:6:\"prefix\";s:0:\"\";s:6:\"suffix\";s:0:\"\";s:6:\"target\";s:0:\"\";s:5:\"nl2br\";b:0;s:10:\"max_length\";i:0;s:13:\"word_boundary\";b:1;s:8:\"ellipsis\";b:1;s:9:\"more_link\";b:0;s:14:\"more_link_text\";s:0:\"\";s:14:\"more_link_path\";s:0:\"\";s:10:\"strip_tags\";b:0;s:4:\"trim\";b:0;s:13:\"preserve_tags\";s:0:\"\";s:4:\"html\";b:0;}s:12:\"element_type\";s:0:\"\";s:13:\"element_class\";s:0:\"\";s:18:\"element_label_type\";s:0:\"\";s:19:\"element_label_class\";s:0:\"\";s:19:\"element_label_colon\";b:1;s:20:\"element_wrapper_type\";s:0:\"\";s:21:\"element_wrapper_class\";s:0:\"\";s:23:\"element_default_classes\";b:1;s:5:\"empty\";s:0:\"\";s:10:\"hide_empty\";b:0;s:10:\"empty_zero\";b:0;s:16:\"hide_alter_empty\";b:1;s:17:\"click_sort_column\";s:5:\"value\";s:4:\"type\";s:6:\"string\";s:8:\"settings\";a:1:{s:14:\"link_to_entity\";b:1;}s:12:\"group_column\";s:5:\"value\";s:13:\"group_columns\";a:0:{}s:10:\"group_rows\";b:1;s:11:\"delta_limit\";i:0;s:12:\"delta_offset\";i:0;s:14:\"delta_reversed\";b:0;s:16:\"delta_first_last\";b:0;s:10:\"multi_type\";s:9:\"separator\";s:9:\"separator\";s:2:\", \";s:17:\"field_api_classes\";b:0;}}s:5:\"pager\";a:2:{s:4:\"type\";s:4:\"some\";s:7:\"options\";a:2:{s:6:\"offset\";i:0;s:14:\"items_per_page\";i:5;}}s:12:\"exposed_form\";a:2:{s:4:\"type\";s:5:\"basic\";s:7:\"options\";a:7:{s:13:\"submit_button\";s:5:\"Apply\";s:12:\"reset_button\";b:0;s:18:\"reset_button_label\";s:5:\"Reset\";s:19:\"exposed_sorts_label\";s:7:\"Sort by\";s:17:\"expose_sort_order\";b:1;s:14:\"sort_asc_label\";s:3:\"Asc\";s:15:\"sort_desc_label\";s:4:\"Desc\";}}s:6:\"access\";a:2:{s:4:\"type\";s:4:\"perm\";s:7:\"options\";a:1:{s:4:\"perm\";s:14:\"access content\";}}s:5:\"cache\";a:2:{s:4:\"type\";s:3:\"tag\";s:7:\"options\";a:0:{}}s:5:\"empty\";a:0:{}s:5:\"sorts\";a:1:{s:7:\"created\";a:13:{s:2:\"id\";s:7:\"created\";s:5:\"table\";s:15:\"node_field_data\";s:5:\"field\";s:7:\"created\";s:12:\"relationship\";s:4:\"none\";s:10:\"group_type\";s:5:\"group\";s:11:\"admin_label\";s:0:\"\";s:11:\"entity_type\";s:4:\"node\";s:12:\"entity_field\";s:7:\"created\";s:9:\"plugin_id\";s:4:\"date\";s:5:\"order\";s:4:\"DESC\";s:6:\"expose\";a:2:{s:5:\"label\";s:0:\"\";s:16:\"field_identifier\";s:0:\"\";}s:7:\"exposed\";b:0;s:11:\"granularity\";s:6:\"second\";}}s:9:\"arguments\";a:0:{}s:7:\"filters\";a:1:{s:6:\"status\";a:9:{s:2:\"id\";s:6:\"status\";s:5:\"table\";s:15:\"node_field_data\";s:5:\"field\";s:6:\"status\";s:11:\"entity_type\";s:4:\"node\";s:12:\"entity_field\";s:6:\"status\";s:9:\"plugin_id\";s:7:\"boolean\";s:5:\"value\";s:1:\"1\";s:5:\"group\";i:1;s:6:\"expose\";a:1:{s:8:\"operator\";s:0:\"\";}}}s:5:\"style\";a:1:{s:4:\"type\";s:7:\"default\";}s:3:\"row\";a:1:{s:4:\"type\";s:6:\"fields\";}s:5:\"query\";a:2:{s:4:\"type\";s:11:\"views_query\";s:7:\"options\";a:5:{s:13:\"query_comment\";s:0:\"\";s:19:\"disable_sql_rewrite\";b:0;s:8:\"distinct\";b:0;s:7:\"replica\";b:0;s:10:\"query_tags\";a:0:{}}}s:13:\"relationships\";a:0:{}s:6:\"header\";a:0:{}s:6:\"footer\";a:0:{}s:17:\"display_extenders\";a:0:{}}s:14:\"cache_metadata\";a:3:{s:7:\"max-age\";i:-1;s:8:\"contexts\";a:4:{i:0;s:26:\"languages:language_content\";i:1;s:28:\"languages:language_interface\";i:2;s:21:\"user.node_grants:view\";i:3;s:16:\"user.permissions\";}s:4:\"tags\";a:0:{}}}s:7:\"block_1\";a:6:{s:2:\"id\";s:7:\"block_1\";s:13:\"display_title\";s:7:\"Maestro\";s:14:\"display_plugin\";s:5:\"block\";s:8:\"position\";i:1;s:15:\"display_options\";a:2:{s:19:\"display_description\";s:0:\"\";s:17:\"display_extenders\";a:0:{}}s:14:\"cache_metadata\";a:3:{s:7:\"max-age\";i:-1;s:8:\"contexts\";a:4:{i:0;s:26:\"languages:language_content\";i:1;s:28:\"languages:language_interface\";i:2;s:21:\"user.node_grants:view\";i:3;s:16:\"user.permissions\";}s:4:\"tags\";a:0:{}}}}}",
  ])
  ->values([
    'collection' => 'language.en',
    'name' => 'views.view.spanish_view',
    'data' => 'a:1:{s:7:"display";a:1:{s:7:"default";a:2:{s:13:"display_title";s:6:"Master";s:15:"display_options";a:2:{s:12:"exposed_form";a:1:{s:7:"options";a:5:{s:13:"submit_button";s:7:"Aplicar";s:18:"reset_button_label";s:9:"Reiniciar";s:19:"exposed_sorts_label";s:11:"Ordenar por";s:14:"sort_asc_label";s:3:"Asc";s:15:"sort_desc_label";s:4:"Desc";}}s:7:"filters";a:1:{s:6:"access";a:1:{s:6:"expose";a:1:{s:5:"label";s:14:"Último acceso";}}}}}}}',
  ])
  ->execute();
