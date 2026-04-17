<?php

// @codingStandardsIgnoreFile
// cspell:disable

/**
 * @file
 * Test fixture to enable media module.
 *
 * After system/tests/fixtures/update/drupal-9.4.0.filled.standard.php.gz is
 * loaded, this file will alter the database to the state of a fresh install of
 * Drupal 11 with media module enabled, including all of its
 * default configuration.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Set the schema versions.
$connection->merge('key_value')
  ->fields(['value' => 'i:8700;'])
  ->condition('collection', 'system.schema')
  ->condition('name', 'media')
  ->execute();

// Update core.extension.
$extensions = $connection->select('config')
  ->fields('config', ['data'])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute()
  ->fetchField();

$extensions = unserialize($extensions);
$extensions['module']['media'] = 0;
$connection->update('config')
  ->fields(['data' => serialize($extensions)])
  ->condition('collection', '')
  ->condition('name', 'core.extension')
  ->execute();

// Add all the removed updates as existing updates.
require_once __DIR__ . '/../../../media.post_update.php';
$existing_updates = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute()
  ->fetchField();
$existing_updates = unserialize($existing_updates);
$existing_updates = array_merge(
  $existing_updates,
  array_keys(media_removed_post_updates())
);
$connection->update('key_value')
  ->fields(['value' => serialize($existing_updates)])
  ->condition('collection', 'post_update')
  ->condition('name', 'existing_updates')
  ->execute();

$existing_help_search_unindexed_count = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'state')
  ->condition('name', 'help_search_unindexed_count')
  ->execute()
  ->fetchField();
$existing_help_search_unindexed_count = unserialize($existing_help_search_unindexed_count);
// $existing_help_search_unindexed_count is a string. Convert it to an integer.
$existing_help_search_unindexed_count = (int) $existing_help_search_unindexed_count;
$existing_help_search_unindexed_count += 1;
// convert the integer back to a string.
$existing_help_search_unindexed_count = (string) $existing_help_search_unindexed_count;
$connection->update('key_value')
  ->fields(['value' => serialize($existing_help_search_unindexed_count)])
  ->condition('collection', 'state')
  ->condition('name', 'help_search_unindexed_count')
  ->execute();

// Install the default configuration for media types.
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'core.entity_form_display.media.audio.default',
    'data' => 'a:11:{s:4:"uuid";s:36:"0ed5417b-1ede-4a5e-a10a-67ff6674fb2a";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:46:"field.field.media.audio.field_media_audio_file";i:1;s:16:"media.type.audio";}s:6:"module";a:2:{i:0;s:4:"file";i:1;s:4:"path";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"G2_SKH3jmI9FQeXSUxo3KgQqiyF1hPDEkc7-3-rCSbc";}s:2:"id";s:19:"media.audio.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:5:"audio";s:4:"mode";s:7:"default";s:7:"content";a:5:{s:7:"created";a:5:{s:4:"type";s:18:"datetime_timestamp";s:6:"weight";i:10;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:22:"field_media_audio_file";a:5:{s:4:"type";s:12:"file_generic";s:6:"weight";i:0;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:18:"progress_indicator";s:8:"throbber";}s:20:"third_party_settings";a:0:{}}s:4:"path";a:5:{s:4:"type";s:4:"path";s:6:"weight";i:30;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:6:"status";a:5:{s:4:"type";s:16:"boolean_checkbox";s:6:"weight";i:100;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:13:"display_label";b:1;}s:20:"third_party_settings";a:0:{}}s:3:"uid";a:5:{s:4:"type";s:29:"entity_reference_autocomplete";s:6:"weight";i:5;s:6:"region";s:7:"content";s:8:"settings";a:4:{s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;s:4:"size";i:60;s:11:"placeholder";s:0:"";}s:20:"third_party_settings";a:0:{}}}s:6:"hidden";a:1:{s:4:"name";b:1;}}',
  ])
  ->values([
   'collection' => '',
   'name' => 'core.entity_form_display.media.document.default',
   'data' => 'a:11:{s:4:"uuid";s:36:"d72ca34c-9bdb-4642-8a83-056adea80156";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:47:"field.field.media.document.field_media_document";i:1;s:19:"media.type.document";}s:6:"module";a:2:{i:0;s:4:"file";i:1;s:4:"path";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"aewrRkePgJzdD5kPOq8JeMcKHs6yat49nE7ZeCQzQZg";}s:2:"id";s:22:"media.document.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:8:"document";s:4:"mode";s:7:"default";s:7:"content";a:5:{s:7:"created";a:5:{s:4:"type";s:18:"datetime_timestamp";s:6:"weight";i:10;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:20:"field_media_document";a:5:{s:4:"type";s:12:"file_generic";s:6:"weight";i:0;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:18:"progress_indicator";s:8:"throbber";}s:20:"third_party_settings";a:0:{}}s:4:"path";a:5:{s:4:"type";s:4:"path";s:6:"weight";i:30;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:6:"status";a:5:{s:4:"type";s:16:"boolean_checkbox";s:6:"weight";i:100;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:13:"display_label";b:1;}s:20:"third_party_settings";a:0:{}}s:3:"uid";a:5:{s:4:"type";s:29:"entity_reference_autocomplete";s:6:"weight";i:5;s:6:"region";s:7:"content";s:8:"settings";a:4:{s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;s:4:"size";i:60;s:11:"placeholder";s:0:"";}s:20:"third_party_settings";a:0:{}}}s:6:"hidden";a:1:{s:4:"name";b:1;}}',
  ])
  ->values([
   'collection' => '',
   'name' => 'core.entity_form_display.media.image.default',
   'data' => 'a:11:{s:4:"uuid";s:36:"8ce10b83-c817-4f2b-be11-347f405df75d";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:3:{i:0;s:41:"field.field.media.image.field_media_image";i:1;s:21:"image.style.thumbnail";i:2;s:16:"media.type.image";}s:6:"module";a:2:{i:0;s:5:"image";i:1;s:4:"path";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"JSY4-JPyNZBiYYo6imdRYF6_SdtWQexPndrLvn3-vw4";}s:2:"id";s:19:"media.image.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:5:"image";s:4:"mode";s:7:"default";s:7:"content";a:5:{s:7:"created";a:5:{s:4:"type";s:18:"datetime_timestamp";s:6:"weight";i:10;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:17:"field_media_image";a:5:{s:4:"type";s:11:"image_image";s:6:"weight";i:0;s:6:"region";s:7:"content";s:8:"settings";a:2:{s:18:"progress_indicator";s:8:"throbber";s:19:"preview_image_style";s:9:"thumbnail";}s:20:"third_party_settings";a:0:{}}s:4:"path";a:5:{s:4:"type";s:4:"path";s:6:"weight";i:30;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:6:"status";a:5:{s:4:"type";s:16:"boolean_checkbox";s:6:"weight";i:100;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:13:"display_label";b:1;}s:20:"third_party_settings";a:0:{}}s:3:"uid";a:5:{s:4:"type";s:29:"entity_reference_autocomplete";s:6:"weight";i:5;s:6:"region";s:7:"content";s:8:"settings";a:4:{s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;s:4:"size";i:60;s:11:"placeholder";s:0:"";}s:20:"third_party_settings";a:0:{}}}s:6:"hidden";a:1:{s:4:"name";b:1;}}',
  ])
  ->values([
   'collection' => '',
   'name' => 'core.entity_form_display.media.remote_video.default',
   'data' => 'a:11:{s:4:"uuid";s:36:"c926b2ec-f81f-4589-b41b-4f2d0e0914a3";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:55:"field.field.media.remote_video.field_media_oembed_video";i:1;s:23:"media.type.remote_video";}s:6:"module";a:2:{i:0;s:5:"media";i:1;s:4:"path";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"pM8mGlwfpvfG_y5tZn0lGAXFLXz2_yKkL7MvWZsRqdA";}s:2:"id";s:26:"media.remote_video.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:12:"remote_video";s:4:"mode";s:7:"default";s:7:"content";a:5:{s:7:"created";a:5:{s:4:"type";s:18:"datetime_timestamp";s:6:"weight";i:10;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:24:"field_media_oembed_video";a:5:{s:4:"type";s:16:"oembed_textfield";s:6:"weight";i:0;s:6:"region";s:7:"content";s:8:"settings";a:2:{s:4:"size";i:60;s:11:"placeholder";s:0:"";}s:20:"third_party_settings";a:0:{}}s:4:"path";a:5:{s:4:"type";s:4:"path";s:6:"weight";i:30;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:6:"status";a:5:{s:4:"type";s:16:"boolean_checkbox";s:6:"weight";i:100;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:13:"display_label";b:1;}s:20:"third_party_settings";a:0:{}}s:3:"uid";a:5:{s:4:"type";s:29:"entity_reference_autocomplete";s:6:"weight";i:5;s:6:"region";s:7:"content";s:8:"settings";a:4:{s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;s:4:"size";i:60;s:11:"placeholder";s:0:"";}s:20:"third_party_settings";a:0:{}}}s:6:"hidden";a:1:{s:4:"name";b:1;}}',
  ])
  ->values([
   'collection' => '',
   'name' => 'core.entity_form_display.media.video.default',
   'data' => 'a:11:{s:4:"uuid";s:36:"1e7693aa-92f8-48cf-b74c-6d0da26d2461";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:46:"field.field.media.video.field_media_video_file";i:1;s:16:"media.type.video";}s:6:"module";a:2:{i:0;s:4:"file";i:1;s:4:"path";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"0kIIaDTt6dixXy8TZkat2MNGZJ6vkRG8TaBWTy3E1bM";}s:2:"id";s:19:"media.video.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:5:"video";s:4:"mode";s:7:"default";s:7:"content";a:5:{s:7:"created";a:5:{s:4:"type";s:18:"datetime_timestamp";s:6:"weight";i:10;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:22:"field_media_video_file";a:5:{s:4:"type";s:12:"file_generic";s:6:"weight";i:0;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:18:"progress_indicator";s:8:"throbber";}s:20:"third_party_settings";a:0:{}}s:4:"path";a:5:{s:4:"type";s:4:"path";s:6:"weight";i:30;s:6:"region";s:7:"content";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}}s:6:"status";a:5:{s:4:"type";s:16:"boolean_checkbox";s:6:"weight";i:100;s:6:"region";s:7:"content";s:8:"settings";a:1:{s:13:"display_label";b:1;}s:20:"third_party_settings";a:0:{}}s:3:"uid";a:5:{s:4:"type";s:29:"entity_reference_autocomplete";s:6:"weight";i:5;s:6:"region";s:7:"content";s:8:"settings";a:4:{s:14:"match_operator";s:8:"CONTAINS";s:11:"match_limit";i:10;s:4:"size";i:60;s:11:"placeholder";s:0:"";}s:20:"third_party_settings";a:0:{}}}s:6:"hidden";a:1:{s:4:"name";b:1;}}',
  ])
  ->values(array(
  'collection' => '',
  'name' => 'core.entity_view_display.media.audio.default',
  'data' => 'a:11:{s:4:"uuid";s:36:"f21273c1-c89d-4651-a9ea-907731af96e4";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:46:"field.field.media.audio.field_media_audio_file";i:1;s:16:"media.type.audio";}s:6:"module";a:1:{i:0;s:4:"file";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"AS765MdDfNpK6K5eE7WVnBvpynClz_havy1R3bO3gVo";}s:2:"id";s:19:"media.audio.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:5:"audio";s:4:"mode";s:7:"default";s:7:"content";a:1:{s:22:"field_media_audio_file";a:6:{s:4:"type";s:10:"file_audio";s:5:"label";s:15:"visually_hidden";s:8:"settings";a:4:{s:8:"controls";b:1;s:8:"autoplay";b:0;s:4:"loop";b:0;s:26:"multiple_file_display_type";s:4:"tags";}s:20:"third_party_settings";a:0:{}s:6:"weight";i:0;s:6:"region";s:7:"content";}}s:6:"hidden";a:4:{s:7:"created";b:1;s:4:"name";b:1;s:9:"thumbnail";b:1;s:3:"uid";b:1;}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'core.entity_view_display.media.document.default',
    'data' => 'a:11:{s:4:"uuid";s:36:"cd33a2a2-151f-4248-b8f4-dac10e8d2b53";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:47:"field.field.media.document.field_media_document";i:1;s:19:"media.type.document";}s:6:"module";a:1:{i:0;s:4:"file";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"XxUyhaTuM0OUUZpr8G6jdrFBEh5eag7auWxBKhm6cvY";}s:2:"id";s:22:"media.document.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:8:"document";s:4:"mode";s:7:"default";s:7:"content";a:1:{s:20:"field_media_document";a:6:{s:4:"type";s:12:"file_default";s:5:"label";s:15:"visually_hidden";s:8:"settings";a:0:{}s:20:"third_party_settings";a:0:{}s:6:"weight";i:1;s:6:"region";s:7:"content";}}s:6:"hidden";a:4:{s:7:"created";b:1;s:4:"name";b:1;s:9:"thumbnail";b:1;s:3:"uid";b:1;}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'core.entity_view_display.media.image.default',
    'data' => 'a:11:{s:4:"uuid";s:36:"0cb05265-046e-45ce-92fe-28c0cc2e2712";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:3:{i:0;s:41:"field.field.media.image.field_media_image";i:1;s:17:"image.style.large";i:2;s:16:"media.type.image";}s:6:"module";a:1:{i:0;s:5:"image";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"73xaTNkI5J6sfFcBmNYeuk070X3mQS_iwwWaPYyfG2M";}s:2:"id";s:19:"media.image.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:5:"image";s:4:"mode";s:7:"default";s:7:"content";a:1:{s:17:"field_media_image";a:6:{s:4:"type";s:5:"image";s:5:"label";s:15:"visually_hidden";s:8:"settings";a:3:{s:11:"image_style";s:5:"large";s:10:"image_link";s:0:"";s:13:"image_loading";a:1:{s:9:"attribute";s:4:"lazy";}}s:20:"third_party_settings";a:0:{}s:6:"weight";i:1;s:6:"region";s:7:"content";}}s:6:"hidden";a:4:{s:7:"created";b:1;s:4:"name";b:1;s:9:"thumbnail";b:1;s:3:"uid";b:1;}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'core.entity_view_display.media.remote_video.default',
    'data' => 'a:11:{s:4:"uuid";s:36:"46c5888c-03d6-44a7-8fef-90fe3654b8f6";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:55:"field.field.media.remote_video.field_media_oembed_video";i:1;s:23:"media.type.remote_video";}s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"ZdPcl2hPxl5pgv3pI-07R7h51OjeUeKJTy-ab1NfM34";}s:2:"id";s:26:"media.remote_video.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:12:"remote_video";s:4:"mode";s:7:"default";s:7:"content";a:1:{s:24:"field_media_oembed_video";a:6:{s:4:"type";s:6:"oembed";s:5:"label";s:6:"hidden";s:8:"settings";a:3:{s:9:"max_width";i:0;s:10:"max_height";i:0;s:7:"loading";a:1:{s:9:"attribute";s:4:"lazy";}}s:20:"third_party_settings";a:0:{}s:6:"weight";i:0;s:6:"region";s:7:"content";}}s:6:"hidden";a:4:{s:7:"created";b:1;s:4:"name";b:1;s:9:"thumbnail";b:1;s:3:"uid";b:1;}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'core.entity_view_display.media.video.default',
    'data' => 'a:11:{s:4:"uuid";s:36:"81e62d0f-5fc1-48a1-938c-0dc421b50758";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:46:"field.field.media.video.field_media_video_file";i:1;s:16:"media.type.video";}s:6:"module";a:1:{i:0;s:4:"file";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"SxvbuGh-6cQMxl9bBV27-hGI46u7ZvwlMm5ObaJMNnw";}s:2:"id";s:19:"media.video.default";s:16:"targetEntityType";s:5:"media";s:6:"bundle";s:5:"video";s:4:"mode";s:7:"default";s:7:"content";a:1:{s:22:"field_media_video_file";a:6:{s:4:"type";s:10:"file_video";s:5:"label";s:15:"visually_hidden";s:8:"settings";a:7:{s:8:"controls";b:1;s:8:"autoplay";b:0;s:4:"loop";b:0;s:26:"multiple_file_display_type";s:4:"tags";s:5:"muted";b:0;s:5:"width";i:640;s:6:"height";i:480;}s:20:"third_party_settings";a:0:{}s:6:"weight";i:0;s:6:"region";s:7:"content";}}s:6:"hidden";a:4:{s:7:"created";b:1;s:4:"name";b:1;s:9:"thumbnail";b:1;s:3:"uid";b:1;}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'core.entity_view_mode.media.full',
    'data' => 'a:10:{s:4:"uuid";s:36:"7ececf4d-b962-4add-86b8-302d3fba137b";s:8:"langcode";s:2:"en";s:6:"status";b:0;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"dTfAUHooYV0uOVPO3saGpgv-c5PppJXDwxvwRTJOycM";}s:2:"id";s:10:"media.full";s:5:"label";s:12:"Full content";s:11:"description";s:0:"";s:16:"targetEntityType";s:5:"media";s:5:"cache";b:1;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.field.media.audio.field_media_audio_file',
    'data' => 'a:17:{s:4:"uuid";s:36:"3497bc57-a128-4254-a3af-8382e79e4635";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:42:"field.storage.media.field_media_audio_file";i:1;s:16:"media.type.audio";}s:6:"module";a:1:{i:0;s:4:"file";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"UlZPIbHcLyNqGY5bvydCTijGcKDmwn4Fo8oYZT85wlk";}s:2:"id";s:34:"media.audio.field_media_audio_file";s:10:"field_name";s:22:"field_media_audio_file";s:11:"entity_type";s:5:"media";s:6:"bundle";s:5:"audio";s:5:"label";s:10:"Audio file";s:11:"description";s:0:"";s:8:"required";b:1;s:12:"translatable";b:1;s:13:"default_value";a:0:{}s:22:"default_value_callback";s:0:"";s:8:"settings";a:6:{s:7:"handler";s:12:"default:file";s:16:"handler_settings";a:0:{}s:14:"file_directory";s:31:"[date:custom:Y]-[date:custom:m]";s:15:"file_extensions";s:11:"mp3 wav aac";s:12:"max_filesize";s:0:"";s:17:"description_field";b:0;}s:10:"field_type";s:4:"file";}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.field.media.document.field_media_document',
    'data' => 'a:17:{s:4:"uuid";s:36:"cf380f50-97c3-4698-8ebf-6f9e42ca87be";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:3:{s:6:"config";a:2:{i:0;s:40:"field.storage.media.field_media_document";i:1;s:19:"media.type.document";}s:6:"module";a:1:{i:0;s:4:"file";}s:8:"enforced";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"DY5HtJTxUjFRGU_PaY6ifo2nhR-nAZ0y0s6kLmUbv5g";}s:2:"id";s:35:"media.document.field_media_document";s:10:"field_name";s:20:"field_media_document";s:11:"entity_type";s:5:"media";s:6:"bundle";s:8:"document";s:5:"label";s:8:"Document";s:11:"description";s:0:"";s:8:"required";b:1;s:12:"translatable";b:1;s:13:"default_value";a:0:{}s:22:"default_value_callback";s:0:"";s:8:"settings";a:6:{s:7:"handler";s:12:"default:file";s:16:"handler_settings";a:0:{}s:14:"file_directory";s:31:"[date:custom:Y]-[date:custom:m]";s:15:"file_extensions";s:96:"txt rtf doc docx ppt pptx xls xlsx pdf odf odg odp ods odt fodt fods fodp fodg key numbers pages";s:12:"max_filesize";s:0:"";s:17:"description_field";b:0;}s:10:"field_type";s:4:"file";}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.field.media.image.field_media_image',
    'data' => 'a:17:{s:4:"uuid";s:36:"27d81988-4bc5-4e6a-b75f-95e48d3c10bd";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:3:{s:6:"config";a:2:{i:0;s:37:"field.storage.media.field_media_image";i:1;s:16:"media.type.image";}s:6:"module";a:1:{i:0;s:5:"image";}s:8:"enforced";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"P7CkVOgjDXiN26Fm2hniNei-XPK3iuZTlcBGqreTbJ0";}s:2:"id";s:29:"media.image.field_media_image";s:10:"field_name";s:17:"field_media_image";s:11:"entity_type";s:5:"media";s:6:"bundle";s:5:"image";s:5:"label";s:5:"Image";s:11:"description";s:0:"";s:8:"required";b:1;s:12:"translatable";b:1;s:13:"default_value";a:0:{}s:22:"default_value_callback";s:0:"";s:8:"settings";a:12:{s:7:"handler";s:12:"default:file";s:16:"handler_settings";a:0:{}s:14:"file_directory";s:31:"[date:custom:Y]-[date:custom:m]";s:15:"file_extensions";s:21:"png gif jpg jpeg webp";s:12:"max_filesize";s:0:"";s:14:"max_resolution";s:0:"";s:14:"min_resolution";s:0:"";s:9:"alt_field";b:1;s:18:"alt_field_required";b:1;s:11:"title_field";b:0;s:20:"title_field_required";b:0;s:13:"default_image";a:5:{s:4:"uuid";N;s:3:"alt";s:0:"";s:5:"title";s:0:"";s:5:"width";N;s:6:"height";N;}}s:10:"field_type";s:5:"image";}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.field.media.remote_video.field_media_oembed_video',
    'data' => 'a:17:{s:4:"uuid";s:36:"422c4e79-8e60-422b-b662-395b164fcfd4";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"config";a:2:{i:0;s:44:"field.storage.media.field_media_oembed_video";i:1;s:23:"media.type.remote_video";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"Eo4HHenV5iZat_kEWgr_wydD3TgwURMCzwt-7qIEyoM";}s:2:"id";s:43:"media.remote_video.field_media_oembed_video";s:10:"field_name";s:24:"field_media_oembed_video";s:11:"entity_type";s:5:"media";s:6:"bundle";s:12:"remote_video";s:5:"label";s:9:"Video URL";s:11:"description";s:0:"";s:8:"required";b:1;s:12:"translatable";b:1;s:13:"default_value";a:0:{}s:22:"default_value_callback";s:0:"";s:8:"settings";a:0:{}s:10:"field_type";s:6:"string";}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.field.media.video.field_media_video_file',
    'data' => 'a:17:{s:4:"uuid";s:36:"7a835b5f-19b2-46ff-94f1-8573558e1d4e";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:2:{i:0;s:42:"field.storage.media.field_media_video_file";i:1;s:16:"media.type.video";}s:6:"module";a:1:{i:0;s:4:"file";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"6kMMjmk2r_csGQ52qI9BUaO8r_oAzNLbxlclaj-JlDQ";}s:2:"id";s:34:"media.video.field_media_video_file";s:10:"field_name";s:22:"field_media_video_file";s:11:"entity_type";s:5:"media";s:6:"bundle";s:5:"video";s:5:"label";s:10:"Video file";s:11:"description";s:0:"";s:8:"required";b:1;s:12:"translatable";b:1;s:13:"default_value";a:0:{}s:22:"default_value_callback";s:0:"";s:8:"settings";a:6:{s:7:"handler";s:12:"default:file";s:16:"handler_settings";a:0:{}s:14:"file_directory";s:31:"[date:custom:Y]-[date:custom:m]";s:15:"file_extensions";s:3:"mp4";s:12:"max_filesize";s:0:"";s:17:"description_field";b:0;}s:10:"field_type";s:4:"file";}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.storage.media.field_media_audio_file',
    'data' => 'a:17:{s:4:"uuid";s:36:"f611f589-9bfd-4ba8-b9a5-90c71c426ac3";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:2:{i:0;s:4:"file";i:1;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"JCHoh95CpUeBx9ch24Tmi6ru0nwmNz8xWVH4Qs7RnTg";}s:2:"id";s:28:"media.field_media_audio_file";s:10:"field_name";s:22:"field_media_audio_file";s:11:"entity_type";s:5:"media";s:4:"type";s:4:"file";s:8:"settings";a:4:{s:11:"target_type";s:4:"file";s:13:"display_field";b:0;s:15:"display_default";b:0;s:10:"uri_scheme";s:6:"public";}s:6:"module";s:4:"file";s:6:"locked";b:0;s:11:"cardinality";i:1;s:12:"translatable";b:1;s:7:"indexes";a:0:{}s:22:"persist_with_no_fields";b:0;s:14:"custom_storage";b:0;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.storage.media.field_media_document',
    'data' => 'a:17:{s:4:"uuid";s:36:"7f657557-0789-4cd3-89ef-4a758c8079bd";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"module";a:2:{i:0;s:4:"file";i:1;s:5:"media";}s:8:"enforced";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"BdkTx7IL59MCw5a_fOZprPTOGM_wcjz-Fm8g7HV3vFk";}s:2:"id";s:26:"media.field_media_document";s:10:"field_name";s:20:"field_media_document";s:11:"entity_type";s:5:"media";s:4:"type";s:4:"file";s:8:"settings";a:4:{s:11:"target_type";s:4:"file";s:13:"display_field";b:0;s:15:"display_default";b:0;s:10:"uri_scheme";s:6:"public";}s:6:"module";s:4:"file";s:6:"locked";b:0;s:11:"cardinality";i:1;s:12:"translatable";b:1;s:7:"indexes";a:0:{}s:22:"persist_with_no_fields";b:0;s:14:"custom_storage";b:0;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.storage.media.field_media_image',
    'data' => 'a:17:{s:4:"uuid";s:36:"00ba4f2a-f4db-420c-b032-bf7e7cfb2dbb";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"module";a:3:{i:0;s:4:"file";i:1;s:5:"image";i:2;s:5:"media";}s:8:"enforced";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"0N0KSFk57p6qsq3qM4lYVGSuROvzXK-tSsdwByqUh3g";}s:2:"id";s:23:"media.field_media_image";s:10:"field_name";s:17:"field_media_image";s:11:"entity_type";s:5:"media";s:4:"type";s:5:"image";s:8:"settings";a:5:{s:11:"target_type";s:4:"file";s:13:"display_field";b:0;s:15:"display_default";b:0;s:10:"uri_scheme";s:6:"public";s:13:"default_image";a:5:{s:4:"uuid";N;s:3:"alt";s:0:"";s:5:"title";s:0:"";s:5:"width";N;s:6:"height";N;}}s:6:"module";s:5:"image";s:6:"locked";b:0;s:11:"cardinality";i:1;s:12:"translatable";b:1;s:7:"indexes";a:0:{}s:22:"persist_with_no_fields";b:0;s:14:"custom_storage";b:0;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.storage.media.field_media_oembed_video',
    'data' => 'a:17:{s:4:"uuid";s:36:"5e59b34b-09ae-4b6e-a0cc-35db6a383d00";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"cNf_852Dq-fNnSaMI4LxL-J6N7bLkHuDbD9EUqOn4_U";}s:2:"id";s:30:"media.field_media_oembed_video";s:10:"field_name";s:24:"field_media_oembed_video";s:11:"entity_type";s:5:"media";s:4:"type";s:6:"string";s:8:"settings";a:3:{s:10:"max_length";i:255;s:14:"case_sensitive";b:0;s:8:"is_ascii";b:0;}s:6:"module";s:4:"core";s:6:"locked";b:0;s:11:"cardinality";i:1;s:12:"translatable";b:1;s:7:"indexes";a:0:{}s:22:"persist_with_no_fields";b:0;s:14:"custom_storage";b:0;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'field.storage.media.field_media_video_file',
    'data' => 'a:17:{s:4:"uuid";s:36:"339c3ba9-788b-4cb7-864f-7f59c723cb32";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:2:{i:0;s:4:"file";i:1;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"z5mgbn1PIVZ5TNMByBmivqo_u3Rdk58UIzpyN4ypTeM";}s:2:"id";s:28:"media.field_media_video_file";s:10:"field_name";s:22:"field_media_video_file";s:11:"entity_type";s:5:"media";s:4:"type";s:4:"file";s:8:"settings";a:4:{s:11:"target_type";s:4:"file";s:13:"display_field";b:0;s:15:"display_default";b:0;s:10:"uri_scheme";s:6:"public";}s:6:"module";s:4:"file";s:6:"locked";b:0;s:11:"cardinality";i:1;s:12:"translatable";b:1;s:7:"indexes";a:0:{}s:22:"persist_with_no_fields";b:0;s:14:"custom_storage";b:0;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'filter.format.basic_html_with_media_embed',
    'data' => "a:8:{s:4:\"uuid\";s:36:\"8960e940-a523-4558-b7a8-c5e1efce7321\";s:8:\"langcode\";s:2:\"en\";s:6:\"status\";b:1;s:12:\"dependencies\";a:1:{s:6:\"module\";a:2:{i:0;s:6:\"editor\";i:1;s:5:\"media\";}}s:4:\"name\";s:27:\"Basic HTML with media embed\";s:6:\"format\";s:27:\"basic_html_with_media_embed\";s:6:\"weight\";i:0;s:7:\"filters\";a:7:{s:21:\"editor_file_reference\";a:5:{s:2:\"id\";s:21:\"editor_file_reference\";s:8:\"provider\";s:6:\"editor\";s:6:\"status\";b:1;s:6:\"weight\";i:0;s:8:\"settings\";a:0:{}}s:12:\"filter_align\";a:5:{s:2:\"id\";s:12:\"filter_align\";s:8:\"provider\";s:6:\"filter\";s:6:\"status\";b:1;s:6:\"weight\";i:0;s:8:\"settings\";a:0:{}}s:14:\"filter_caption\";a:5:{s:2:\"id\";s:14:\"filter_caption\";s:8:\"provider\";s:6:\"filter\";s:6:\"status\";b:1;s:6:\"weight\";i:0;s:8:\"settings\";a:0:{}}s:11:\"filter_html\";a:5:{s:2:\"id\";s:11:\"filter_html\";s:8:\"provider\";s:6:\"filter\";s:6:\"status\";b:1;s:6:\"weight\";i:-10;s:8:\"settings\";a:3:{s:12:\"allowed_html\";s:215:\"<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type='1 A I'> <li> <dl> <dt> <dd> <h2 id='jump-*'> <h3 id> <h4 id> <h5 id> <h6 id> <drupal-media data-entity-type data-entity-uuid>\";s:16:\"filter_html_help\";b:1;s:20:\"filter_html_nofollow\";b:0;}}s:24:\"filter_html_image_secure\";a:5:{s:2:\"id\";s:24:\"filter_html_image_secure\";s:8:\"provider\";s:6:\"filter\";s:6:\"status\";b:1;s:6:\"weight\";i:9;s:8:\"settings\";a:0:{}}s:22:\"filter_image_lazy_load\";a:5:{s:2:\"id\";s:22:\"filter_image_lazy_load\";s:8:\"provider\";s:6:\"filter\";s:6:\"status\";b:1;s:6:\"weight\";i:15;s:8:\"settings\";a:0:{}}s:11:\"media_embed\";a:5:{s:2:\"id\";s:11:\"media_embed\";s:8:\"provider\";s:5:\"media\";s:6:\"status\";b:1;s:6:\"weight\";i:100;s:8:\"settings\";a:3:{s:17:\"default_view_mode\";s:7:\"default\";s:18:\"allowed_view_modes\";a:0:{}s:19:\"allowed_media_types\";a:0:{}}}}}",
  ))
  ->values(array(
    'collection' => '',
    'name' => 'filter.format.full_html_with_media_embed',
    'data' => 'a:8:{s:4:"uuid";s:36:"9341d6ce-1922-46ac-94bd-c8f750826e39";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:2:{i:0;s:6:"editor";i:1;s:5:"media";}}s:4:"name";s:26:"Full HTML with media embed";s:6:"format";s:26:"full_html_with_media_embed";s:6:"weight";i:0;s:7:"filters";a:6:{s:21:"editor_file_reference";a:5:{s:2:"id";s:21:"editor_file_reference";s:8:"provider";s:6:"editor";s:6:"status";b:1;s:6:"weight";i:0;s:8:"settings";a:0:{}}s:12:"filter_align";a:5:{s:2:"id";s:12:"filter_align";s:8:"provider";s:6:"filter";s:6:"status";b:1;s:6:"weight";i:0;s:8:"settings";a:0:{}}s:14:"filter_caption";a:5:{s:2:"id";s:14:"filter_caption";s:8:"provider";s:6:"filter";s:6:"status";b:1;s:6:"weight";i:0;s:8:"settings";a:0:{}}s:20:"filter_htmlcorrector";a:5:{s:2:"id";s:20:"filter_htmlcorrector";s:8:"provider";s:6:"filter";s:6:"status";b:1;s:6:"weight";i:10;s:8:"settings";a:0:{}}s:22:"filter_image_lazy_load";a:5:{s:2:"id";s:22:"filter_image_lazy_load";s:8:"provider";s:6:"filter";s:6:"status";b:1;s:6:"weight";i:15;s:8:"settings";a:0:{}}s:11:"media_embed";a:5:{s:2:"id";s:11:"media_embed";s:8:"provider";s:5:"media";s:6:"status";b:1;s:6:"weight";i:100;s:8:"settings";a:3:{s:17:"default_view_mode";s:7:"default";s:18:"allowed_view_modes";a:0:{}s:19:"allowed_media_types";a:0:{}}}}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'media.settings',
    'data' => 'a:5:{s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"WCFqLQAxMw1weToDJEhfnW1Z-iOF7cqMdL8X7YTFxBA";}s:13:"icon_base_uri";s:28:"public://media-icons/generic";s:13:"iframe_domain";N;s:20:"oembed_providers_url";s:33:"https://oembed.com/providers.json";s:14:"standalone_url";b:0;}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'media.type.audio',
    'data' => 'a:13:{s:4:"uuid";s:36:"a566dfa8-ac1c-4031-a238-e349fd893d77";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"eJw8n6Tk2tO3ZysuEeGR1gZa1yRffaZzR4t0Q7iNurs";}s:2:"id";s:5:"audio";s:5:"label";s:5:"Audio";s:11:"description";s:28:"A locally hosted audio file.";s:6:"source";s:10:"audio_file";s:25:"queue_thumbnail_downloads";b:0;s:12:"new_revision";b:1;s:20:"source_configuration";a:1:{s:12:"source_field";s:22:"field_media_audio_file";}s:9:"field_map";a:1:{s:4:"name";s:4:"name";}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'media.type.document',
    'data' => 'a:13:{s:4:"uuid";s:36:"cfaa37b0-a6dc-4ebd-8ef3-f84fca02c5b1";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"_D9q3XSnP6ik9we9UuoTvZsQKPuYNp_G9PfwVtWzgnQ";}s:2:"id";s:8:"document";s:5:"label";s:8:"Document";s:11:"description";s:44:"An uploaded file or document, such as a PDF.";s:6:"source";s:4:"file";s:25:"queue_thumbnail_downloads";b:0;s:12:"new_revision";b:1;s:20:"source_configuration";a:1:{s:12:"source_field";s:20:"field_media_document";}s:9:"field_map";a:1:{s:4:"name";s:4:"name";}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'media.type.image',
    'data' => 'a:13:{s:4:"uuid";s:36:"1a7e63bc-0868-4862-ab2a-8080b4c8dcdf";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"6Qope5wG7HUpV0tPOBMtDI_GZkHFcF1Xj4hgD9Cu_hM";}s:2:"id";s:5:"image";s:5:"label";s:5:"Image";s:11:"description";s:36:"Use local images for reusable media.";s:6:"source";s:5:"image";s:25:"queue_thumbnail_downloads";b:0;s:12:"new_revision";b:1;s:20:"source_configuration";a:1:{s:12:"source_field";s:17:"field_media_image";}s:9:"field_map";a:1:{s:4:"name";s:4:"name";}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'media.type.remote_video',
    'data' => 'a:13:{s:4:"uuid";s:36:"d5e9531e-da39-4c44-8226-286dd2e29e1b";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"hIBTnDGgDKnCiP6HUZm1m7600DHUEpC6FN3LQ4sUgZ4";}s:2:"id";s:12:"remote_video";s:5:"label";s:12:"Remote video";s:11:"description";s:46:"A remotely hosted video from YouTube or Vimeo.";s:6:"source";s:12:"oembed:video";s:25:"queue_thumbnail_downloads";b:0;s:12:"new_revision";b:1;s:20:"source_configuration";a:3:{s:12:"source_field";s:24:"field_media_oembed_video";s:20:"thumbnails_directory";s:44:"public://oembed_thumbnails/[date:custom:Y-m]";s:9:"providers";a:2:{i:0;s:7:"YouTube";i:1;s:5:"Vimeo";}}s:9:"field_map";a:1:{s:5:"title";s:4:"name";}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'media.type.video',
    'data' => 'a:13:{s:4:"uuid";s:36:"25e028c9-70cb-4f71-acba-349e80e6a0f6";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:0:{}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"hzgvcRgZHltqWf8hBmttoWh95tCJoPL25lPq9YSIRsY";}s:2:"id";s:5:"video";s:5:"label";s:5:"Video";s:11:"description";s:28:"A locally hosted video file.";s:6:"source";s:10:"video_file";s:25:"queue_thumbnail_downloads";b:0;s:12:"new_revision";b:1;s:20:"source_configuration";a:1:{s:12:"source_field";s:22:"field_media_video_file";}s:9:"field_map";a:1:{s:4:"name";s:4:"name";}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'system.action.media_delete_action',
    'data' => 'a:10:{s:4:"uuid";s:36:"12f3865c-772d-4afb-885e-8d4fcdcd1fc8";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"FrZy1tmuXJcOxhXlBoI1Hsnen5TT-9OCC1iolWH84go";}s:2:"id";s:19:"media_delete_action";s:5:"label";s:12:"Delete media";s:4:"type";s:5:"media";s:6:"plugin";s:26:"entity:delete_action:media";s:13:"configuration";a:0:{}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'system.action.media_publish_action',
    'data' => 'a:10:{s:4:"uuid";s:36:"645ae6fe-90ca-4ddb-b4d5-a4323f75989a";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"nh83qNNrmWE-CDdHz2MdFOAk60T9mzv3R-MaKfZR2jw";}s:2:"id";s:20:"media_publish_action";s:5:"label";s:13:"Publish media";s:4:"type";s:5:"media";s:6:"plugin";s:27:"entity:publish_action:media";s:13:"configuration";a:0:{}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'system.action.media_save_action',
    'data' => 'a:10:{s:4:"uuid";s:36:"153e58e0-147e-4db4-a15b-238802068a0b";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"VVyUA6PIaVeGtcIbgEWqJ6SYDiJdReBeojFswURFpKs";}s:2:"id";s:17:"media_save_action";s:5:"label";s:10:"Save media";s:4:"type";s:5:"media";s:6:"plugin";s:24:"entity:save_action:media";s:13:"configuration";a:0:{}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'system.action.media_unpublish_action',
    'data' => 'a:10:{s:4:"uuid";s:36:"bde38241-bb97-4bbb-8657-12f78130f03e";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:1:{s:6:"module";a:1:{i:0;s:5:"media";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"CsK6TseQ2DatEbZgbd30swOlZ28_HHwAESU2LvEnWq0";}s:2:"id";s:22:"media_unpublish_action";s:5:"label";s:15:"Unpublish media";s:4:"type";s:5:"media";s:6:"plugin";s:29:"entity:unpublish_action:media";s:13:"configuration";a:0:{}}',
  ))
  ->values(array(
    'collection' => '',
    'name' => 'views.view.media',
    'data' => 'a:13:{s:4:"uuid";s:36:"e677312c-070e-42ec-8219-69b436c9c5bc";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"config";a:1:{i:0;s:21:"image.style.thumbnail";}s:6:"module";a:3:{i:0;s:5:"image";i:1;s:5:"media";i:2;s:4:"user";}}s:5:"_core";a:1:{s:19:"default_config_hash";s:43:"kOTUk5XWeupgBOJuRRarCiDcFKVZcvaW-FT5MTzrML8";}s:2:"id";s:5:"media";s:5:"label";s:5:"Media";s:6:"module";s:5:"views";s:11:"description";s:22:"Find and manage media.";s:3:"tag";s:0:"";s:10:"base_table";s:16:"media_field_data";s:10:"base_field";s:3:"mid";s:7:"display";a:2:{s:7:"default";a:6:{s:2:"id";s:7:"default";s:13:"display_title";s:7:"Default";s:14:"display_plugin";s:7:"default";s:8:"position";i:0;s:15:"display_options";a:17:{s:5:"title";s:5:"Media";s:6:"fields";a:8:{s:15:"media_bulk_form";a:26:{s:2:"id";s:15:"media_bulk_form";s:5:"table";s:5:"media";s:5:"field";s:15:"media_bulk_form";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:9:"plugin_id";s:9:"bulk_form";s:5:"label";s:0:"";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:0;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:12:"action_title";s:6:"Action";s:15:"include_exclude";s:7:"exclude";s:16:"selected_actions";a:0:{}}s:20:"thumbnail__target_id";a:37:{s:2:"id";s:20:"thumbnail__target_id";s:5:"table";s:16:"media_field_data";s:5:"field";s:20:"thumbnail__target_id";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:9:"thumbnail";s:9:"plugin_id";s:5:"field";s:5:"label";s:9:"Thumbnail";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:17:"click_sort_column";s:9:"target_id";s:4:"type";s:5:"image";s:8:"settings";a:3:{s:10:"image_link";s:0:"";s:11:"image_style";s:9:"thumbnail";s:13:"image_loading";a:1:{s:9:"attribute";s:4:"lazy";}}s:12:"group_column";s:0:"";s:13:"group_columns";a:0:{}s:10:"group_rows";b:1;s:11:"delta_limit";i:0;s:12:"delta_offset";i:0;s:14:"delta_reversed";b:0;s:16:"delta_first_last";b:0;s:10:"multi_type";s:9:"separator";s:9:"separator";s:2:", ";s:17:"field_api_classes";b:0;}s:4:"name";a:37:{s:2:"id";s:4:"name";s:5:"table";s:16:"media_field_data";s:5:"field";s:4:"name";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:5:"media";s:9:"plugin_id";s:5:"field";s:5:"label";s:10:"Media name";s:7:"exclude";b:0;s:5:"alter";a:8:{s:10:"alter_text";b:0;s:9:"make_link";b:0;s:8:"absolute";b:0;s:13:"word_boundary";b:0;s:8:"ellipsis";b:0;s:10:"strip_tags";b:0;s:4:"trim";b:0;s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:17:"click_sort_column";s:5:"value";s:4:"type";s:6:"string";s:8:"settings";a:1:{s:14:"link_to_entity";b:1;}s:12:"group_column";s:5:"value";s:13:"group_columns";a:0:{}s:10:"group_rows";b:1;s:11:"delta_limit";i:0;s:12:"delta_offset";i:0;s:14:"delta_reversed";b:0;s:16:"delta_first_last";b:0;s:10:"multi_type";s:9:"separator";s:9:"separator";s:2:", ";s:17:"field_api_classes";b:0;}s:6:"bundle";a:37:{s:2:"id";s:6:"bundle";s:5:"table";s:16:"media_field_data";s:5:"field";s:6:"bundle";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:6:"bundle";s:9:"plugin_id";s:5:"field";s:5:"label";s:4:"Type";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:17:"click_sort_column";s:9:"target_id";s:4:"type";s:22:"entity_reference_label";s:8:"settings";a:1:{s:4:"link";b:0;}s:12:"group_column";s:9:"target_id";s:13:"group_columns";a:0:{}s:10:"group_rows";b:1;s:11:"delta_limit";i:0;s:12:"delta_offset";i:0;s:14:"delta_reversed";b:0;s:16:"delta_first_last";b:0;s:10:"multi_type";s:9:"separator";s:9:"separator";s:2:", ";s:17:"field_api_classes";b:0;}s:3:"uid";a:37:{s:2:"id";s:3:"uid";s:5:"table";s:16:"media_field_data";s:5:"field";s:3:"uid";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:3:"uid";s:9:"plugin_id";s:5:"field";s:5:"label";s:6:"Author";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:17:"click_sort_column";s:9:"target_id";s:4:"type";s:22:"entity_reference_label";s:8:"settings";a:1:{s:4:"link";b:1;}s:12:"group_column";s:9:"target_id";s:13:"group_columns";a:0:{}s:10:"group_rows";b:1;s:11:"delta_limit";i:0;s:12:"delta_offset";i:0;s:14:"delta_reversed";b:0;s:16:"delta_first_last";b:0;s:10:"multi_type";s:9:"separator";s:9:"separator";s:2:", ";s:17:"field_api_classes";b:0;}s:6:"status";a:37:{s:2:"id";s:6:"status";s:5:"table";s:16:"media_field_data";s:5:"field";s:6:"status";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:6:"status";s:9:"plugin_id";s:5:"field";s:5:"label";s:6:"Status";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:17:"click_sort_column";s:5:"value";s:4:"type";s:7:"boolean";s:8:"settings";a:3:{s:6:"format";s:6:"custom";s:19:"format_custom_false";s:11:"Unpublished";s:18:"format_custom_true";s:9:"Published";}s:12:"group_column";s:5:"value";s:13:"group_columns";a:0:{}s:10:"group_rows";b:1;s:11:"delta_limit";i:0;s:12:"delta_offset";i:0;s:14:"delta_reversed";b:0;s:16:"delta_first_last";b:0;s:10:"multi_type";s:9:"separator";s:9:"separator";s:2:", ";s:17:"field_api_classes";b:0;}s:7:"changed";a:37:{s:2:"id";s:7:"changed";s:5:"table";s:16:"media_field_data";s:5:"field";s:7:"changed";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:7:"changed";s:9:"plugin_id";s:5:"field";s:5:"label";s:7:"Updated";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:17:"click_sort_column";s:5:"value";s:4:"type";s:9:"timestamp";s:8:"settings";a:5:{s:11:"date_format";s:5:"short";s:18:"custom_date_format";s:0:"";s:8:"timezone";s:0:"";s:7:"tooltip";a:2:{s:11:"date_format";s:4:"long";s:18:"custom_date_format";s:0:"";}s:9:"time_diff";a:5:{s:7:"enabled";b:0;s:13:"future_format";s:15:"@interval hence";s:11:"past_format";s:13:"@interval ago";s:11:"granularity";i:2;s:7:"refresh";i:60;}}s:12:"group_column";s:5:"value";s:13:"group_columns";a:0:{}s:10:"group_rows";b:1;s:11:"delta_limit";i:0;s:12:"delta_offset";i:0;s:14:"delta_reversed";b:0;s:16:"delta_first_last";b:0;s:10:"multi_type";s:9:"separator";s:9:"separator";s:2:", ";s:17:"field_api_classes";b:0;}s:10:"operations";a:24:{s:2:"id";s:10:"operations";s:5:"table";s:5:"media";s:5:"field";s:10:"operations";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:9:"plugin_id";s:17:"entity_operations";s:5:"label";s:10:"Operations";s:7:"exclude";b:0;s:5:"alter";a:26:{s:10:"alter_text";b:0;s:4:"text";s:0:"";s:9:"make_link";b:0;s:4:"path";s:0:"";s:8:"absolute";b:0;s:8:"external";b:0;s:14:"replace_spaces";b:0;s:9:"path_case";s:4:"none";s:15:"trim_whitespace";b:0;s:3:"alt";s:0:"";s:3:"rel";s:0:"";s:10:"link_class";s:0:"";s:6:"prefix";s:0:"";s:6:"suffix";s:0:"";s:6:"target";s:0:"";s:5:"nl2br";b:0;s:10:"max_length";i:0;s:13:"word_boundary";b:1;s:8:"ellipsis";b:1;s:9:"more_link";b:0;s:14:"more_link_text";s:0:"";s:14:"more_link_path";s:0:"";s:10:"strip_tags";b:0;s:4:"trim";b:0;s:13:"preserve_tags";s:0:"";s:4:"html";b:0;}s:12:"element_type";s:0:"";s:13:"element_class";s:0:"";s:18:"element_label_type";s:0:"";s:19:"element_label_class";s:0:"";s:19:"element_label_colon";b:1;s:20:"element_wrapper_type";s:0:"";s:21:"element_wrapper_class";s:0:"";s:23:"element_default_classes";b:1;s:5:"empty";s:0:"";s:10:"hide_empty";b:0;s:10:"empty_zero";b:0;s:16:"hide_alter_empty";b:1;s:11:"destination";b:1;}}s:5:"pager";a:2:{s:4:"type";s:4:"full";s:7:"options";a:8:{s:6:"offset";i:0;s:24:"pagination_heading_level";s:2:"h4";s:14:"items_per_page";i:50;s:11:"total_pages";N;s:2:"id";i:0;s:4:"tags";a:4:{s:4:"next";s:8:"Next ›";s:8:"previous";s:12:"‹ Previous";s:5:"first";s:8:"« First";s:4:"last";s:7:"Last »";}s:6:"expose";a:7:{s:14:"items_per_page";b:0;s:20:"items_per_page_label";s:14:"Items per page";s:22:"items_per_page_options";s:13:"5, 10, 25, 50";s:26:"items_per_page_options_all";b:0;s:32:"items_per_page_options_all_label";s:7:"- All -";s:6:"offset";b:0;s:12:"offset_label";s:6:"Offset";}s:8:"quantity";i:9;}}s:12:"exposed_form";a:2:{s:4:"type";s:5:"basic";s:7:"options";a:7:{s:13:"submit_button";s:6:"Filter";s:12:"reset_button";b:0;s:18:"reset_button_label";s:5:"Reset";s:19:"exposed_sorts_label";s:7:"Sort by";s:17:"expose_sort_order";b:1;s:14:"sort_asc_label";s:3:"Asc";s:15:"sort_desc_label";s:4:"Desc";}}s:6:"access";a:2:{s:4:"type";s:4:"perm";s:7:"options";a:1:{s:4:"perm";s:21:"access media overview";}}s:5:"cache";a:2:{s:4:"type";s:3:"tag";s:7:"options";a:0:{}}s:5:"empty";a:1:{s:16:"area_text_custom";a:10:{s:2:"id";s:16:"area_text_custom";s:5:"table";s:5:"views";s:5:"field";s:16:"area_text_custom";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:9:"plugin_id";s:11:"text_custom";s:5:"empty";b:1;s:7:"content";s:19:"No media available.";s:8:"tokenize";b:0;}}s:5:"sorts";a:1:{s:7:"created";a:13:{s:2:"id";s:7:"created";s:5:"table";s:16:"media_field_data";s:5:"field";s:7:"created";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:7:"created";s:9:"plugin_id";s:4:"date";s:5:"order";s:4:"DESC";s:6:"expose";a:2:{s:5:"label";s:0:"";s:16:"field_identifier";s:7:"created";}s:7:"exposed";b:0;s:11:"granularity";s:6:"second";}}s:9:"arguments";a:0:{}s:7:"filters";a:5:{s:4:"name";a:16:{s:2:"id";s:4:"name";s:5:"table";s:16:"media_field_data";s:5:"field";s:4:"name";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:4:"name";s:9:"plugin_id";s:6:"string";s:8:"operator";s:8:"contains";s:5:"value";s:0:"";s:5:"group";i:1;s:7:"exposed";b:1;s:6:"expose";a:12:{s:11:"operator_id";s:7:"name_op";s:5:"label";s:10:"Media name";s:11:"description";s:0:"";s:12:"use_operator";b:0;s:8:"operator";s:7:"name_op";s:24:"operator_limit_selection";b:0;s:13:"operator_list";a:0:{}s:10:"identifier";s:4:"name";s:8:"required";b:0;s:8:"remember";b:0;s:8:"multiple";b:0;s:14:"remember_roles";a:3:{s:13:"authenticated";s:13:"authenticated";s:9:"anonymous";s:1:"0";s:13:"administrator";s:1:"0";}}s:10:"is_grouped";b:0;s:10:"group_info";a:10:{s:5:"label";s:0:"";s:11:"description";s:0:"";s:10:"identifier";s:0:"";s:8:"optional";b:1;s:6:"widget";s:6:"select";s:8:"multiple";b:0;s:8:"remember";b:0;s:13:"default_group";s:3:"All";s:22:"default_group_multiple";a:0:{}s:11:"group_items";a:0:{}}}s:6:"bundle";a:16:{s:2:"id";s:6:"bundle";s:5:"table";s:16:"media_field_data";s:5:"field";s:6:"bundle";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:6:"bundle";s:9:"plugin_id";s:6:"bundle";s:8:"operator";s:2:"in";s:5:"value";a:0:{}s:5:"group";i:1;s:7:"exposed";b:1;s:6:"expose";a:13:{s:11:"operator_id";s:9:"bundle_op";s:5:"label";s:4:"Type";s:11:"description";s:0:"";s:12:"use_operator";b:0;s:8:"operator";s:9:"bundle_op";s:24:"operator_limit_selection";b:0;s:13:"operator_list";a:0:{}s:10:"identifier";s:4:"type";s:8:"required";b:0;s:8:"remember";b:0;s:8:"multiple";b:0;s:14:"remember_roles";a:3:{s:13:"authenticated";s:13:"authenticated";s:9:"anonymous";s:1:"0";s:13:"administrator";s:1:"0";}s:6:"reduce";b:0;}s:10:"is_grouped";b:0;s:10:"group_info";a:10:{s:5:"label";s:0:"";s:11:"description";s:0:"";s:10:"identifier";s:0:"";s:8:"optional";b:1;s:6:"widget";s:6:"select";s:8:"multiple";b:0;s:8:"remember";b:0;s:13:"default_group";s:3:"All";s:22:"default_group_multiple";a:0:{}s:11:"group_items";a:0:{}}}s:6:"status";a:16:{s:2:"id";s:6:"status";s:5:"table";s:16:"media_field_data";s:5:"field";s:6:"status";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:6:"status";s:9:"plugin_id";s:7:"boolean";s:8:"operator";s:1:"=";s:5:"value";s:1:"1";s:5:"group";i:1;s:7:"exposed";b:1;s:6:"expose";a:12:{s:11:"operator_id";s:0:"";s:5:"label";s:4:"True";s:11:"description";N;s:12:"use_operator";b:0;s:8:"operator";s:9:"status_op";s:24:"operator_limit_selection";b:0;s:13:"operator_list";a:0:{}s:10:"identifier";s:6:"status";s:8:"required";b:1;s:8:"remember";b:0;s:8:"multiple";b:0;s:14:"remember_roles";a:1:{s:13:"authenticated";s:13:"authenticated";}}s:10:"is_grouped";b:1;s:10:"group_info";a:10:{s:5:"label";s:16:"Published status";s:11:"description";s:0:"";s:10:"identifier";s:6:"status";s:8:"optional";b:1;s:6:"widget";s:6:"select";s:8:"multiple";b:0;s:8:"remember";b:0;s:13:"default_group";s:3:"All";s:22:"default_group_multiple";a:0:{}s:11:"group_items";a:2:{i:1;a:3:{s:5:"title";s:9:"Published";s:8:"operator";s:1:"=";s:5:"value";s:1:"1";}i:2;a:3:{s:5:"title";s:11:"Unpublished";s:8:"operator";s:1:"=";s:5:"value";s:1:"0";}}}}s:12:"status_extra";a:15:{s:2:"id";s:12:"status_extra";s:5:"table";s:16:"media_field_data";s:5:"field";s:12:"status_extra";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:9:"plugin_id";s:12:"media_status";s:8:"operator";s:1:"=";s:5:"value";s:0:"";s:5:"group";i:1;s:7:"exposed";b:0;s:6:"expose";a:12:{s:11:"operator_id";s:0:"";s:5:"label";s:0:"";s:11:"description";s:0:"";s:12:"use_operator";b:0;s:8:"operator";s:0:"";s:24:"operator_limit_selection";b:0;s:13:"operator_list";a:0:{}s:10:"identifier";s:0:"";s:8:"required";b:0;s:8:"remember";b:0;s:8:"multiple";b:0;s:14:"remember_roles";a:1:{s:13:"authenticated";s:13:"authenticated";}}s:10:"is_grouped";b:0;s:10:"group_info";a:10:{s:5:"label";s:0:"";s:11:"description";s:0:"";s:10:"identifier";s:0:"";s:8:"optional";b:1;s:6:"widget";s:6:"select";s:8:"multiple";b:0;s:8:"remember";b:0;s:13:"default_group";s:3:"All";s:22:"default_group_multiple";a:0:{}s:11:"group_items";a:0:{}}}s:8:"langcode";a:16:{s:2:"id";s:8:"langcode";s:5:"table";s:16:"media_field_data";s:5:"field";s:8:"langcode";s:12:"relationship";s:4:"none";s:10:"group_type";s:5:"group";s:11:"admin_label";s:0:"";s:11:"entity_type";s:5:"media";s:12:"entity_field";s:8:"langcode";s:9:"plugin_id";s:8:"language";s:8:"operator";s:2:"in";s:5:"value";a:0:{}s:5:"group";i:1;s:7:"exposed";b:1;s:6:"expose";a:13:{s:11:"operator_id";s:11:"langcode_op";s:5:"label";s:8:"Language";s:11:"description";s:0:"";s:12:"use_operator";b:0;s:8:"operator";s:11:"langcode_op";s:24:"operator_limit_selection";b:0;s:13:"operator_list";a:0:{}s:10:"identifier";s:8:"langcode";s:8:"required";b:0;s:8:"remember";b:0;s:8:"multiple";b:0;s:14:"remember_roles";a:3:{s:13:"authenticated";s:13:"authenticated";s:9:"anonymous";s:1:"0";s:13:"administrator";s:1:"0";}s:6:"reduce";b:0;}s:10:"is_grouped";b:0;s:10:"group_info";a:10:{s:5:"label";s:0:"";s:11:"description";s:0:"";s:10:"identifier";s:0:"";s:8:"optional";b:1;s:6:"widget";s:6:"select";s:8:"multiple";b:0;s:8:"remember";b:0;s:13:"default_group";s:3:"All";s:22:"default_group_multiple";a:0:{}s:11:"group_items";a:0:{}}}}s:5:"style";a:2:{s:4:"type";s:5:"table";s:7:"options";a:12:{s:8:"grouping";a:0:{}s:9:"row_class";s:0:"";s:17:"default_row_class";b:1;s:7:"columns";a:6:{s:4:"name";s:4:"name";s:6:"bundle";s:6:"bundle";s:7:"changed";s:7:"changed";s:3:"uid";s:3:"uid";s:6:"status";s:6:"status";s:20:"thumbnail__target_id";s:20:"thumbnail__target_id";}s:7:"default";s:7:"changed";s:4:"info";a:6:{s:4:"name";a:6:{s:8:"sortable";b:1;s:18:"default_sort_order";s:3:"asc";s:5:"align";s:0:"";s:9:"separator";s:0:"";s:12:"empty_column";b:0;s:10:"responsive";s:0:"";}s:6:"bundle";a:6:{s:8:"sortable";b:1;s:18:"default_sort_order";s:3:"asc";s:5:"align";s:0:"";s:9:"separator";s:0:"";s:12:"empty_column";b:0;s:10:"responsive";s:0:"";}s:7:"changed";a:6:{s:8:"sortable";b:1;s:18:"default_sort_order";s:4:"desc";s:5:"align";s:0:"";s:9:"separator";s:0:"";s:12:"empty_column";b:0;s:10:"responsive";s:0:"";}s:3:"uid";a:6:{s:8:"sortable";b:0;s:18:"default_sort_order";s:3:"asc";s:5:"align";s:0:"";s:9:"separator";s:0:"";s:12:"empty_column";b:0;s:10:"responsive";s:0:"";}s:6:"status";a:6:{s:8:"sortable";b:1;s:18:"default_sort_order";s:3:"asc";s:5:"align";s:0:"";s:9:"separator";s:0:"";s:12:"empty_column";b:0;s:10:"responsive";s:0:"";}s:20:"thumbnail__target_id";a:6:{s:8:"sortable";b:0;s:18:"default_sort_order";s:3:"asc";s:5:"align";s:0:"";s:9:"separator";s:0:"";s:12:"empty_column";b:0;s:10:"responsive";s:0:"";}}s:8:"override";b:1;s:6:"sticky";b:0;s:7:"summary";s:0:"";s:11:"empty_table";b:1;s:7:"caption";s:0:"";s:11:"description";s:0:"";}}s:3:"row";a:1:{s:4:"type";s:6:"fields";}s:5:"query";a:2:{s:4:"type";s:11:"views_query";s:7:"options";a:5:{s:13:"query_comment";s:0:"";s:19:"disable_sql_rewrite";b:0;s:8:"distinct";b:0;s:7:"replica";b:0;s:10:"query_tags";a:0:{}}}s:13:"relationships";a:0:{}s:6:"header";a:0:{}s:6:"footer";a:0:{}s:17:"display_extenders";a:0:{}}s:14:"cache_metadata";a:3:{s:7:"max-age";i:0;s:8:"contexts";a:6:{i:0;s:26:"languages:language_content";i:1;s:28:"languages:language_interface";i:2;s:3:"url";i:3;s:14:"url.query_args";i:4;s:4:"user";i:5;s:16:"user.permissions";}s:4:"tags";a:0:{}}}s:15:"media_page_list";a:6:{s:2:"id";s:15:"media_page_list";s:13:"display_title";s:5:"Media";s:14:"display_plugin";s:4:"page";s:8:"position";i:1;s:15:"display_options";a:4:{s:19:"display_description";s:0:"";s:17:"display_extenders";a:0:{}s:4:"path";s:19:"admin/content/media";s:4:"menu";a:8:{s:4:"type";s:3:"tab";s:5:"title";s:5:"Media";s:11:"description";s:0:"";s:6:"weight";i:0;s:8:"expanded";b:0;s:9:"menu_name";s:4:"main";s:6:"parent";s:0:"";s:7:"context";s:1:"0";}}s:14:"cache_metadata";a:3:{s:7:"max-age";i:0;s:8:"contexts";a:6:{i:0;s:26:"languages:language_content";i:1;s:28:"languages:language_interface";i:2;s:3:"url";i:3;s:14:"url.query_args";i:4;s:4:"user";i:5;s:16:"user.permissions";}s:4:"tags";a:0:{}}}}}',
  ))
  ->execute();

// Add help search items for the media module.
$connection->insert('help_search_items')
  ->fields([
    'section_plugin_id',
    'permission',
    'topic_id',
  ])
  ->values(array(
    'section_plugin_id' => 'help_topics',
    'permission' => '',
    'topic_id' => 'media.media_type',
  ))
  ->execute();

// Add Key Value store entries for media module.
$connection->insert('key_value')
  ->fields(array(
    'collection',
    'name',
    'value',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.action',
    'name' => 'uuid:12f3865c-772d-4afb-885e-8d4fcdcd1fc8',
    'value' => 'a:1:{i:0;s:33:"system.action.media_delete_action";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.action',
    'name' => 'uuid:153e58e0-147e-4db4-a15b-238802068a0b',
    'value' => 'a:1:{i:0;s:31:"system.action.media_save_action";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.action',
    'name' => 'uuid:645ae6fe-90ca-4ddb-b4d5-a4323f75989a',
    'value' => 'a:1:{i:0;s:34:"system.action.media_publish_action";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.action',
    'name' => 'uuid:bde38241-bb97-4bbb-8657-12f78130f03e',
    'value' => 'a:1:{i:0;s:36:"system.action.media_unpublish_action";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_form_display',
    'name' => 'uuid:0ed5417b-1ede-4a5e-a10a-67ff6674fb2a',
    'value' => 'a:1:{i:0;s:44:"core.entity_form_display.media.audio.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_form_display',
    'name' => 'uuid:1e7693aa-92f8-48cf-b74c-6d0da26d2461',
    'value' => 'a:1:{i:0;s:44:"core.entity_form_display.media.video.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_form_display',
    'name' => 'uuid:8ce10b83-c817-4f2b-be11-347f405df75d',
    'value' => 'a:1:{i:0;s:44:"core.entity_form_display.media.image.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_form_display',
    'name' => 'uuid:c926b2ec-f81f-4589-b41b-4f2d0e0914a3',
    'value' => 'a:1:{i:0;s:51:"core.entity_form_display.media.remote_video.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_form_display',
    'name' => 'uuid:d72ca34c-9bdb-4642-8a83-056adea80156',
    'value' => 'a:1:{i:0;s:47:"core.entity_form_display.media.document.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_view_display',
    'name' => 'uuid:0cb05265-046e-45ce-92fe-28c0cc2e2712',
    'value' => 'a:1:{i:0;s:44:"core.entity_view_display.media.image.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_view_display',
    'name' => 'uuid:46c5888c-03d6-44a7-8fef-90fe3654b8f6',
    'value' => 'a:1:{i:0;s:51:"core.entity_view_display.media.remote_video.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_view_display',
    'name' => 'uuid:81e62d0f-5fc1-48a1-938c-0dc421b50758',
    'value' => 'a:1:{i:0;s:44:"core.entity_view_display.media.video.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_view_display',
    'name' => 'uuid:cd33a2a2-151f-4248-b8f4-dac10e8d2b53',
    'value' => 'a:1:{i:0;s:47:"core.entity_view_display.media.document.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_view_display',
    'name' => 'uuid:f21273c1-c89d-4651-a9ea-907731af96e4',
    'value' => 'a:1:{i:0;s:44:"core.entity_view_display.media.audio.default";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.entity_view_mode',
    'name' => 'uuid:7ececf4d-b962-4add-86b8-302d3fba137b',
    'value' => 'a:1:{i:0;s:32:"core.entity_view_mode.media.full";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_config',
    'name' => 'uuid:27d81988-4bc5-4e6a-b75f-95e48d3c10bd',
    'value' => 'a:1:{i:0;s:41:"field.field.media.image.field_media_image";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_config',
    'name' => 'uuid:3497bc57-a128-4254-a3af-8382e79e4635',
    'value' => 'a:1:{i:0;s:46:"field.field.media.audio.field_media_audio_file";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_config',
    'name' => 'uuid:422c4e79-8e60-422b-b662-395b164fcfd4',
    'value' => 'a:1:{i:0;s:55:"field.field.media.remote_video.field_media_oembed_video";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_config',
    'name' => 'uuid:7a835b5f-19b2-46ff-94f1-8573558e1d4e',
    'value' => 'a:1:{i:0;s:46:"field.field.media.video.field_media_video_file";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_config',
    'name' => 'uuid:cf380f50-97c3-4698-8ebf-6f9e42ca87be',
    'value' => 'a:1:{i:0;s:47:"field.field.media.document.field_media_document";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_storage_config',
    'name' => 'uuid:00ba4f2a-f4db-420c-b032-bf7e7cfb2dbb',
    'value' => 'a:1:{i:0;s:37:"field.storage.media.field_media_image";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_storage_config',
    'name' => 'uuid:339c3ba9-788b-4cb7-864f-7f59c723cb32',
    'value' => 'a:1:{i:0;s:42:"field.storage.media.field_media_video_file";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_storage_config',
    'name' => 'uuid:5e59b34b-09ae-4b6e-a0cc-35db6a383d00',
    'value' => 'a:1:{i:0;s:44:"field.storage.media.field_media_oembed_video";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_storage_config',
    'name' => 'uuid:7f657557-0789-4cd3-89ef-4a758c8079bd',
    'value' => 'a:1:{i:0;s:40:"field.storage.media.field_media_document";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.field_storage_config',
    'name' => 'uuid:f611f589-9bfd-4ba8-b9a5-90c71c426ac3',
    'value' => 'a:1:{i:0;s:42:"field.storage.media.field_media_audio_file";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.filter_format',
    'name' => 'uuid:8960e940-a523-4558-b7a8-c5e1efce7321',
    'value' => 'a:1:{i:0;s:41:"filter.format.basic_html_with_media_embed";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.filter_format',
    'name' => 'uuid:9341d6ce-1922-46ac-94bd-c8f750826e39',
    'value' => 'a:1:{i:0;s:40:"filter.format.full_html_with_media_embed";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.media_type',
    'name' => 'uuid:1a7e63bc-0868-4862-ab2a-8080b4c8dcdf',
    'value' => 'a:1:{i:0;s:16:"media.type.image";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.media_type',
    'name' => 'uuid:25e028c9-70cb-4f71-acba-349e80e6a0f6',
    'value' => 'a:1:{i:0;s:16:"media.type.video";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.media_type',
    'name' => 'uuid:a566dfa8-ac1c-4031-a238-e349fd893d77',
    'value' => 'a:1:{i:0;s:16:"media.type.audio";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.media_type',
    'name' => 'uuid:cfaa37b0-a6dc-4ebd-8ef3-f84fca02c5b1',
    'value' => 'a:1:{i:0;s:19:"media.type.document";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.media_type',
    'name' => 'uuid:d5e9531e-da39-4c44-8226-286dd2e29e1b',
    'value' => 'a:1:{i:0;s:23:"media.type.remote_video";}',
  ))
  ->values(array(
    'collection' => 'config.entity.key_store.view',
    'name' => 'uuid:e677312c-070e-42ec-8219-69b436c9c5bc',
    'value' => 'a:1:{i:0;s:16:"views.view.media";}',
  ))
  ->values(array(
    'collection' => 'entity.definitions.bundle_field_map',
    'name' => 'media',
    'value' => 'a:5:{s:22:"field_media_audio_file";a:2:{s:4:"type";s:4:"file";s:7:"bundles";a:1:{s:5:"audio";s:5:"audio";}}s:20:"field_media_document";a:2:{s:4:"type";s:4:"file";s:7:"bundles";a:1:{s:8:"document";s:8:"document";}}s:17:"field_media_image";a:2:{s:4:"type";s:5:"image";s:7:"bundles";a:1:{s:5:"image";s:5:"image";}}s:24:"field_media_oembed_video";a:2:{s:4:"type";s:6:"string";s:7:"bundles";a:1:{s:12:"remote_video";s:12:"remote_video";}}s:22:"field_media_video_file";a:2:{s:4:"type";s:4:"file";s:7:"bundles";a:1:{s:5:"video";s:5:"video";}}}',
  ))
  ->values(array(
    'collection' => 'entity.definitions.installed',
    'name' => 'media.entity_type',
    'value' => 'O:36:"Drupal\Core\Entity\ContentEntityType":41:{s:5:" * id";s:5:"media";s:8:" * class";s:25:"Drupal\media\Entity\Media";s:11:" * provider";s:5:"media";s:15:" * static_cache";b:1;s:15:" * render_cache";b:1;s:19:" * persistent_cache";b:1;s:14:" * entity_keys";a:10:{s:2:"id";s:3:"mid";s:8:"revision";s:3:"vid";s:6:"bundle";s:6:"bundle";s:5:"label";s:4:"name";s:8:"langcode";s:8:"langcode";s:4:"uuid";s:4:"uuid";s:9:"published";s:6:"status";s:5:"owner";s:3:"uid";s:16:"default_langcode";s:16:"default_langcode";s:29:"revision_translation_affected";s:29:"revision_translation_affected";}s:16:" * originalClass";s:25:"Drupal\media\Entity\Media";s:11:" * handlers";a:7:{s:7:"storage";s:25:"Drupal\media\MediaStorage";s:12:"view_builder";s:36:"Drupal\Core\Entity\EntityViewBuilder";s:12:"list_builder";s:29:"Drupal\media\MediaListBuilder";s:6:"access";s:38:"Drupal\media\MediaAccessControlHandler";s:4:"form";a:7:{s:7:"default";s:22:"Drupal\media\MediaForm";s:3:"add";s:22:"Drupal\media\MediaForm";s:4:"edit";s:22:"Drupal\media\MediaForm";s:6:"delete";s:42:"Drupal\Core\Entity\ContentEntityDeleteForm";s:23:"delete-multiple-confirm";s:42:"Drupal\Core\Entity\Form\DeleteMultipleForm";s:15:"revision-delete";s:42:"Drupal\Core\Entity\Form\RevisionDeleteForm";s:15:"revision-revert";s:42:"Drupal\Core\Entity\Form\RevisionRevertForm";}s:10:"views_data";s:27:"Drupal\media\MediaViewsData";s:14:"route_provider";a:2:{s:4:"html";s:39:"Drupal\media\Routing\MediaRouteProvider";s:8:"revision";s:52:"Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider";}}s:19:" * admin_permission";s:16:"administer media";s:24:" * collection_permission";N;s:25:" * permission_granularity";s:6:"bundle";s:8:" * links";a:11:{s:8:"add-page";s:10:"/media/add";s:8:"add-form";s:23:"/media/add/{media_type}";s:9:"canonical";s:19:"/media/{media}/edit";s:10:"collection";s:20:"/admin/content/media";s:11:"delete-form";s:21:"/media/{media}/delete";s:20:"delete-multiple-form";s:13:"/media/delete";s:9:"edit-form";s:19:"/media/{media}/edit";s:8:"revision";s:46:"/media/{media}/revisions/{media_revision}/view";s:20:"revision-delete-form";s:47:"/media/{media}/revision/{media_revision}/delete";s:20:"revision-revert-form";s:47:"/media/{media}/revision/{media_revision}/revert";s:15:"version-history";s:24:"/media/{media}/revisions";}s:21:" * bundle_entity_type";s:10:"media_type";s:12:" * bundle_of";N;s:15:" * bundle_label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:10:"Media type";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:13:" * base_table";s:5:"media";s:22:" * revision_data_table";s:20:"media_field_revision";s:17:" * revision_table";s:14:"media_revision";s:13:" * data_table";s:16:"media_field_data";s:11:" * internal";b:0;s:15:" * translatable";b:1;s:19:" * show_revision_ui";b:1;s:8:" * label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:5:"Media";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:19:" * label_collection";s:0:"";s:17:" * label_singular";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:10:"media item";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:15:" * label_plural";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:11:"media items";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:14:" * label_count";a:3:{s:8:"singular";s:17:"@count media item";s:6:"plural";s:18:"@count media items";s:7:"context";N;}s:15:" * uri_callback";N;s:8:" * group";s:7:"content";s:14:" * group_label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:7:"Content";s:12:" * arguments";a:0:{}s:10:" * options";a:1:{s:7:"context";s:17:"Entity type group";}}s:22:" * field_ui_base_route";s:27:"entity.media_type.edit_form";s:26:" * common_reference_target";b:1;s:22:" * list_cache_contexts";a:0:{}s:18:" * list_cache_tags";a:1:{i:0;s:10:"media_list";}s:14:" * constraints";a:2:{s:13:"EntityChanged";N;s:26:"EntityUntranslatableFields";N;}s:13:" * additional";a:0:{}s:14:" * _serviceIds";a:0:{}s:18:" * _entityStorages";a:0:{}s:20:" * stringTranslation";N;s:25:" * revision_metadata_keys";a:4:{s:13:"revision_user";s:13:"revision_user";s:16:"revision_created";s:16:"revision_created";s:20:"revision_log_message";s:20:"revision_log_message";s:16:"revision_default";s:16:"revision_default";}}',
  ))
  ->values(array(
    'collection' => 'entity.definitions.installed',
    'name' => 'media.field_storage_definitions',
    'value' => "a:22:{s:3:\"mid\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:7:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:2:\"ID\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"read-only\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:3:\"mid\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:integer\";s:8:\"settings\";a:6:{s:8:\"unsigned\";b:1;s:4:\"size\";s:6:\"normal\";s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";s:6:\"prefix\";s:0:\"\";s:6:\"suffix\";s:0:\"\";}}s:18:\" * fieldDefinition\";r:2;}s:7:\" * type\";s:7:\"integer\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:3:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:4:\"size\";s:6:\"normal\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:4:\"uuid\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:7:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:4:\"UUID\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"read-only\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:4:\"uuid\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:15:\"field_item:uuid\";s:8:\"settings\";a:3:{s:10:\"max_length\";i:128;s:8:\"is_ascii\";b:1;s:14:\"case_sensitive\";b:0;}}s:18:\" * fieldDefinition\";r:36;}s:7:\" * type\";s:4:\"uuid\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:3:{s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:128;s:6:\"binary\";b:0;}}s:11:\"unique keys\";a:1:{s:5:\"value\";a:1:{i:0;s:5:\"value\";}}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:3:\"vid\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:7:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:11:\"Revision ID\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"read-only\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:3:\"vid\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:integer\";s:8:\"settings\";a:6:{s:8:\"unsigned\";b:1;s:4:\"size\";s:6:\"normal\";s:3:\"min\";s:0:\"\";s:3:\"max\";s:0:\"\";s:6:\"prefix\";s:0:\"\";s:6:\"suffix\";s:0:\"\";}}s:18:\" * fieldDefinition\";r:69;}s:7:\" * type\";s:7:\"integer\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:3:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:4:\"size\";s:6:\"normal\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:8:\"langcode\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:9:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:8:\"Language\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:7:\"display\";a:2:{s:4:\"view\";a:1:{s:7:\"options\";a:1:{s:6:\"region\";s:6:\"hidden\";}}s:4:\"form\";a:1:{s:7:\"options\";a:2:{s:4:\"type\";s:15:\"language_select\";s:6:\"weight\";i:2;}}}s:12:\"revisionable\";b:1;s:12:\"translatable\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:8:\"langcode\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:19:\"field_item:language\";s:8:\"settings\";a:0:{}}s:18:\" * fieldDefinition\";r:103;}s:7:\" * type\";s:8:\"language\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:2:{s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:12;}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:6:\"bundle\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:8:{s:5:\"label\";s:10:\"Media type\";s:8:\"required\";b:1;s:9:\"read-only\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:6:\"bundle\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:27:\"field_item:entity_reference\";s:8:\"settings\";a:3:{s:11:\"target_type\";s:10:\"media_type\";s:7:\"handler\";s:7:\"default\";s:16:\"handler_settings\";a:0:{}}}s:18:\" * fieldDefinition\";r:139;}s:7:\" * type\";s:16:\"entity_reference\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:9:\"target_id\";a:3:{s:11:\"description\";s:28:\"The ID of the target entity.\";s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:32;}}s:7:\"indexes\";a:1:{s:9:\"target_id\";a:1:{i:0;s:9:\"target_id\";}}s:11:\"unique keys\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:16:\"revision_created\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:8:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:20:\"Revision create time\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:47:\"The time that the current revision was created.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"revisionable\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:16:\"revision_created\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:created\";s:8:\"settings\";a:0:{}}s:18:\" * fieldDefinition\";r:170;}s:7:\" * type\";s:7:\"created\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:1:{s:4:\"type\";s:3:\"int\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:13:\"revision_user\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:8:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:13:\"Revision user\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:50:\"The user ID of the author of the current revision.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"revisionable\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:13:\"revision_user\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:27:\"field_item:entity_reference\";s:8:\"settings\";a:3:{s:11:\"target_type\";s:4:\"user\";s:7:\"handler\";s:7:\"default\";s:16:\"handler_settings\";a:0:{}}}s:18:\" * fieldDefinition\";r:200;}s:7:\" * type\";s:16:\"entity_reference\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:9:\"target_id\";a:3:{s:11:\"description\";s:28:\"The ID of the target entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;}}s:7:\"indexes\";a:1:{s:9:\"target_id\";a:1:{i:0;s:9:\"target_id\";}}s:11:\"unique keys\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:20:\"revision_log_message\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:10:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:20:\"Revision log message\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:43:\"Briefly describe the changes you have made.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"revisionable\";b:1;s:13:\"default_value\";a:1:{i:0;a:1:{s:5:\"value\";s:0:\"\";}}s:7:\"display\";a:1:{s:4:\"form\";a:1:{s:7:\"options\";a:3:{s:4:\"type\";s:15:\"string_textarea\";s:6:\"weight\";i:25;s:8:\"settings\";a:1:{s:4:\"rows\";i:4;}}}}s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:20:\"revision_log_message\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:22:\"field_item:string_long\";s:8:\"settings\";a:1:{s:14:\"case_sensitive\";b:0;}}s:18:\" * fieldDefinition\";r:237;}s:7:\" * type\";s:11:\"string_long\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:2:{s:4:\"type\";s:4:\"text\";s:4:\"size\";s:3:\"big\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:6:\"status\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:10:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:9:\"Published\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"revisionable\";b:1;s:12:\"translatable\";b:1;s:13:\"default_value\";a:1:{i:0;a:1:{s:5:\"value\";b:1;}}s:7:\"display\";a:1:{s:4:\"form\";a:2:{s:7:\"options\";a:3:{s:4:\"type\";s:16:\"boolean_checkbox\";s:8:\"settings\";a:1:{s:13:\"display_label\";b:1;}s:6:\"weight\";i:100;}s:12:\"configurable\";b:1;}}s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:6:\"status\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:boolean\";s:8:\"settings\";a:2:{s:8:\"on_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:2:\"On\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"off_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:3:\"Off\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}}}s:18:\" * fieldDefinition\";r:279;}s:7:\" * type\";s:7:\"boolean\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:2:{s:4:\"type\";s:3:\"int\";s:4:\"size\";s:4:\"tiny\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:3:\"uid\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:11:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:11:\"Authored by\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"translatable\";b:1;s:22:\"default_value_callback\";s:48:\"Drupal\\media\\Entity\\Media::getDefaultEntityOwner\";s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:26:\"The user ID of the author.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"revisionable\";b:1;s:7:\"display\";a:2:{s:4:\"form\";a:2:{s:7:\"options\";a:3:{s:4:\"type\";s:29:\"entity_reference_autocomplete\";s:6:\"weight\";i:5;s:8:\"settings\";a:4:{s:14:\"match_operator\";s:8:\"CONTAINS\";s:4:\"size\";s:2:\"60\";s:17:\"autocomplete_type\";s:4:\"tags\";s:11:\"placeholder\";s:0:\"\";}}s:12:\"configurable\";b:1;}s:4:\"view\";a:2:{s:7:\"options\";a:3:{s:5:\"label\";s:6:\"hidden\";s:4:\"type\";s:6:\"author\";s:6:\"weight\";i:0;}s:12:\"configurable\";b:1;}}s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:3:\"uid\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:27:\"field_item:entity_reference\";s:8:\"settings\";a:3:{s:11:\"target_type\";s:4:\"user\";s:7:\"handler\";s:7:\"default\";s:16:\"handler_settings\";a:0:{}}}s:18:\" * fieldDefinition\";r:326;}s:7:\" * type\";s:16:\"entity_reference\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:9:\"target_id\";a:3:{s:11:\"description\";s:28:\"The ID of the target entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;}}s:7:\"indexes\";a:1:{s:9:\"target_id\";a:1:{i:0;s:9:\"target_id\";}}s:11:\"unique keys\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:4:\"name\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:11:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:4:\"Name\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:8:\"required\";b:1;s:12:\"translatable\";b:1;s:12:\"revisionable\";b:1;s:13:\"default_value\";a:1:{i:0;a:1:{s:5:\"value\";s:0:\"\";}}s:7:\"display\";a:2:{s:4:\"form\";a:2:{s:7:\"options\";a:2:{s:4:\"type\";s:16:\"string_textfield\";s:6:\"weight\";i:-5;}s:12:\"configurable\";b:1;}s:4:\"view\";a:2:{s:7:\"options\";a:1:{s:6:\"region\";s:6:\"hidden\";}s:12:\"configurable\";b:1;}}s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:4:\"name\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:17:\"field_item:string\";s:8:\"settings\";a:3:{s:10:\"max_length\";i:255;s:8:\"is_ascii\";b:0;s:14:\"case_sensitive\";b:0;}}s:18:\" * fieldDefinition\";r:382;}s:7:\" * type\";s:6:\"string\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:3:{s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:255;s:6:\"binary\";b:0;}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:9:\"thumbnail\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:11:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:9:\"Thumbnail\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:32:\"The thumbnail of the media item.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"revisionable\";b:1;s:12:\"translatable\";b:1;s:7:\"display\";a:1:{s:4:\"view\";a:2:{s:7:\"options\";a:4:{s:4:\"type\";s:5:\"image\";s:6:\"weight\";i:5;s:5:\"label\";s:6:\"hidden\";s:8:\"settings\";a:1:{s:11:\"image_style\";s:9:\"thumbnail\";}}s:12:\"configurable\";b:1;}}s:9:\"read-only\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:9:\"thumbnail\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:16:\"field_item:image\";s:8:\"settings\";a:16:{s:13:\"default_image\";a:5:{s:4:\"uuid\";N;s:3:\"alt\";s:0:\"\";s:5:\"title\";s:0:\"\";s:5:\"width\";N;s:6:\"height\";N;}s:11:\"target_type\";s:4:\"file\";s:13:\"display_field\";b:0;s:15:\"display_default\";b:0;s:10:\"uri_scheme\";s:6:\"public\";s:15:\"file_extensions\";s:21:\"png gif jpg jpeg webp\";s:9:\"alt_field\";i:1;s:18:\"alt_field_required\";i:1;s:11:\"title_field\";i:0;s:20:\"title_field_required\";i:0;s:14:\"max_resolution\";s:0:\"\";s:14:\"min_resolution\";s:0:\"\";s:14:\"file_directory\";s:31:\"[date:custom:Y]-[date:custom:m]\";s:12:\"max_filesize\";s:0:\"\";s:7:\"handler\";s:7:\"default\";s:16:\"handler_settings\";a:0:{}}}s:18:\" * fieldDefinition\";r:428;}s:7:\" * type\";s:5:\"image\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:5:{s:9:\"target_id\";a:3:{s:11:\"description\";s:26:\"The ID of the file entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;}s:3:\"alt\";a:3:{s:11:\"description\";s:56:\"Alternative image text, for the image's 'alt' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:512;}s:5:\"title\";a:3:{s:11:\"description\";s:52:\"Image title text, for the image's 'title' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:1024;}s:5:\"width\";a:3:{s:11:\"description\";s:33:\"The width of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;}s:6:\"height\";a:3:{s:11:\"description\";s:34:\"The height of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;}}s:7:\"indexes\";a:1:{s:9:\"target_id\";a:1:{i:0;s:9:\"target_id\";}}s:12:\"foreign keys\";a:1:{s:9:\"target_id\";a:2:{s:5:\"table\";s:12:\"file_managed\";s:7:\"columns\";a:1:{s:9:\"target_id\";s:3:\"fid\";}}}s:11:\"unique keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:7:\"created\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:11:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:11:\"Authored on\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:36:\"The time the media item was created.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"translatable\";b:1;s:12:\"revisionable\";b:1;s:22:\"default_value_callback\";s:41:\"Drupal\\media\\Entity\\Media::getRequestTime\";s:7:\"display\";a:2:{s:4:\"form\";a:2:{s:7:\"options\";a:2:{s:4:\"type\";s:18:\"datetime_timestamp\";s:6:\"weight\";i:10;}s:12:\"configurable\";b:1;}s:4:\"view\";a:2:{s:7:\"options\";a:3:{s:5:\"label\";s:6:\"hidden\";s:4:\"type\";s:9:\"timestamp\";s:6:\"weight\";i:0;}s:12:\"configurable\";b:1;}}s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:7:\"created\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:created\";s:8:\"settings\";a:0:{}}s:18:\" * fieldDefinition\";r:514;}s:7:\" * type\";s:7:\"created\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:1:{s:4:\"type\";s:3:\"int\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:7:\"changed\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:9:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:7:\"Changed\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:40:\"The time the media item was last edited.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"translatable\";b:1;s:12:\"revisionable\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:7:\"changed\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:changed\";s:8:\"settings\";a:0:{}}s:18:\" * fieldDefinition\";r:558;}s:7:\" * type\";s:7:\"changed\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:1:{s:4:\"type\";s:3:\"int\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:16:\"default_langcode\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:10:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:19:\"Default translation\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:58:\"A flag indicating whether this is the default translation.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:12:\"translatable\";b:1;s:12:\"revisionable\";b:1;s:13:\"default_value\";a:1:{i:0;a:1:{s:5:\"value\";b:1;}}s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:16:\"default_langcode\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:boolean\";s:8:\"settings\";a:2:{s:8:\"on_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:2:\"On\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"off_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:3:\"Off\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}}}s:18:\" * fieldDefinition\";r:589;}s:7:\" * type\";s:7:\"boolean\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:2:{s:4:\"type\";s:3:\"int\";s:4:\"size\";s:4:\"tiny\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:16:\"revision_default\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:11:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:16:\"Default revision\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:72:\"A flag indicating whether this was a default revision when it was saved.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:16:\"storage_required\";b:1;s:8:\"internal\";b:1;s:12:\"translatable\";b:0;s:12:\"revisionable\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:16:\"revision_default\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:boolean\";s:8:\"settings\";a:2:{s:8:\"on_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:2:\"On\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"off_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:3:\"Off\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}}}s:18:\" * fieldDefinition\";r:632;}s:7:\" * type\";s:7:\"boolean\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:2:{s:4:\"type\";s:3:\"int\";s:4:\"size\";s:4:\"tiny\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:29:\"revision_translation_affected\";O:37:\"Drupal\\Core\\Field\\BaseFieldDefinition\":5:{s:13:\" * definition\";a:10:{s:5:\"label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:29:\"Revision translation affected\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:11:\"description\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:72:\"Indicates if the last edit of a translation belongs to current revision.\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"read-only\";b:1;s:12:\"revisionable\";b:1;s:12:\"translatable\";b:1;s:8:\"provider\";s:5:\"media\";s:10:\"field_name\";s:29:\"revision_translation_affected\";s:11:\"entity_type\";s:5:\"media\";s:6:\"bundle\";N;s:13:\"initial_value\";N;}s:17:\" * itemDefinition\";O:51:\"Drupal\\Core\\Field\\TypedData\\FieldItemDataDefinition\":2:{s:13:\" * definition\";a:2:{s:4:\"type\";s:18:\"field_item:boolean\";s:8:\"settings\";a:2:{s:8:\"on_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:2:\"On\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}s:9:\"off_label\";O:48:\"Drupal\\Core\\StringTranslation\\TranslatableMarkup\":3:{s:9:\" * string\";s:3:\"Off\";s:12:\" * arguments\";a:0:{}s:10:\" * options\";a:0:{}}}}s:18:\" * fieldDefinition\";r:674;}s:7:\" * type\";s:7:\"boolean\";s:9:\" * schema\";a:4:{s:7:\"columns\";a:1:{s:5:\"value\";a:2:{s:4:\"type\";s:3:\"int\";s:4:\"size\";s:4:\"tiny\";}}s:11:\"unique keys\";a:0:{}s:7:\"indexes\";a:0:{}s:12:\"foreign keys\";a:0:{}}s:10:\" * indexes\";a:0:{}}s:22:\"field_media_audio_file\";O:38:\"Drupal\\field\\Entity\\FieldStorageConfig\":30:{s:15:\" * entityTypeId\";s:20:\"field_storage_config\";s:15:\" * enforceIsNew\";b:1;s:12:\" * typedData\";N;s:16:\" * cacheContexts\";a:0:{}s:12:\" * cacheTags\";a:0:{}s:14:\" * cacheMaxAge\";i:-1;s:14:\" * _serviceIds\";a:0:{}s:18:\" * _entityStorages\";a:0:{}s:13:\" * originalId\";s:28:\"media.field_media_audio_file\";s:9:\" * status\";b:1;s:7:\" * uuid\";s:36:\"f611f589-9bfd-4ba8-b9a5-90c71c426ac3\";s:11:\" * langcode\";s:2:\"en\";s:23:\" * third_party_settings\";a:0:{}s:8:\" * _core\";a:1:{s:19:\"default_config_hash\";s:43:\"JCHoh95CpUeBx9ch24Tmi6ru0nwmNz8xWVH4Qs7RnTg\";}s:14:\" * trustedData\";b:1;s:15:\" * dependencies\";a:1:{s:6:\"module\";a:2:{i:0;s:4:\"file\";i:1;s:5:\"media\";}}s:12:\" * isSyncing\";b:0;s:5:\" * id\";s:28:\"media.field_media_audio_file\";s:13:\" * field_name\";s:22:\"field_media_audio_file\";s:14:\" * entity_type\";s:5:\"media\";s:7:\" * type\";s:4:\"file\";s:9:\" * module\";s:4:\"file\";s:11:\" * settings\";a:4:{s:11:\"target_type\";s:4:\"file\";s:13:\"display_field\";b:0;s:15:\"display_default\";b:0;s:10:\"uri_scheme\";s:6:\"public\";}s:14:\" * cardinality\";i:1;s:15:\" * translatable\";b:1;s:9:\" * locked\";b:0;s:25:\" * persist_with_no_fields\";b:0;s:14:\"custom_storage\";b:0;s:10:\" * indexes\";a:0:{}s:10:\" * deleted\";b:0;}s:20:\"field_media_document\";O:38:\"Drupal\\field\\Entity\\FieldStorageConfig\":30:{s:15:\" * entityTypeId\";s:20:\"field_storage_config\";s:15:\" * enforceIsNew\";b:1;s:12:\" * typedData\";N;s:16:\" * cacheContexts\";a:0:{}s:12:\" * cacheTags\";a:0:{}s:14:\" * cacheMaxAge\";i:-1;s:14:\" * _serviceIds\";a:0:{}s:18:\" * _entityStorages\";a:0:{}s:13:\" * originalId\";s:26:\"media.field_media_document\";s:9:\" * status\";b:1;s:7:\" * uuid\";s:36:\"7f657557-0789-4cd3-89ef-4a758c8079bd\";s:11:\" * langcode\";s:2:\"en\";s:23:\" * third_party_settings\";a:0:{}s:8:\" * _core\";a:1:{s:19:\"default_config_hash\";s:43:\"BdkTx7IL59MCw5a_fOZprPTOGM_wcjz-Fm8g7HV3vFk\";}s:14:\" * trustedData\";b:1;s:15:\" * dependencies\";a:2:{s:6:\"module\";a:2:{i:0;s:4:\"file\";i:1;s:5:\"media\";}s:8:\"enforced\";a:1:{s:6:\"module\";a:1:{i:0;s:5:\"media\";}}}s:12:\" * isSyncing\";b:0;s:5:\" * id\";s:26:\"media.field_media_document\";s:13:\" * field_name\";s:20:\"field_media_document\";s:14:\" * entity_type\";s:5:\"media\";s:7:\" * type\";s:4:\"file\";s:9:\" * module\";s:4:\"file\";s:11:\" * settings\";a:4:{s:11:\"target_type\";s:4:\"file\";s:13:\"display_field\";b:0;s:15:\"display_default\";b:0;s:10:\"uri_scheme\";s:6:\"public\";}s:14:\" * cardinality\";i:1;s:15:\" * translatable\";b:1;s:9:\" * locked\";b:0;s:25:\" * persist_with_no_fields\";b:0;s:14:\"custom_storage\";b:0;s:10:\" * indexes\";a:0:{}s:10:\" * deleted\";b:0;}s:17:\"field_media_image\";O:38:\"Drupal\\field\\Entity\\FieldStorageConfig\":30:{s:15:\" * entityTypeId\";s:20:\"field_storage_config\";s:15:\" * enforceIsNew\";b:1;s:12:\" * typedData\";N;s:16:\" * cacheContexts\";a:0:{}s:12:\" * cacheTags\";a:0:{}s:14:\" * cacheMaxAge\";i:-1;s:14:\" * _serviceIds\";a:0:{}s:18:\" * _entityStorages\";a:0:{}s:13:\" * originalId\";s:23:\"media.field_media_image\";s:9:\" * status\";b:1;s:7:\" * uuid\";s:36:\"00ba4f2a-f4db-420c-b032-bf7e7cfb2dbb\";s:11:\" * langcode\";s:2:\"en\";s:23:\" * third_party_settings\";a:0:{}s:8:\" * _core\";a:1:{s:19:\"default_config_hash\";s:43:\"0N0KSFk57p6qsq3qM4lYVGSuROvzXK-tSsdwByqUh3g\";}s:14:\" * trustedData\";b:1;s:15:\" * dependencies\";a:2:{s:6:\"module\";a:3:{i:0;s:4:\"file\";i:1;s:5:\"image\";i:2;s:5:\"media\";}s:8:\"enforced\";a:1:{s:6:\"module\";a:1:{i:0;s:5:\"media\";}}}s:12:\" * isSyncing\";b:0;s:5:\" * id\";s:23:\"media.field_media_image\";s:13:\" * field_name\";s:17:\"field_media_image\";s:14:\" * entity_type\";s:5:\"media\";s:7:\" * type\";s:5:\"image\";s:9:\" * module\";s:5:\"image\";s:11:\" * settings\";a:5:{s:11:\"target_type\";s:4:\"file\";s:13:\"display_field\";b:0;s:15:\"display_default\";b:0;s:10:\"uri_scheme\";s:6:\"public\";s:13:\"default_image\";a:5:{s:4:\"uuid\";N;s:3:\"alt\";s:0:\"\";s:5:\"title\";s:0:\"\";s:5:\"width\";N;s:6:\"height\";N;}}s:14:\" * cardinality\";i:1;s:15:\" * translatable\";b:1;s:9:\" * locked\";b:0;s:25:\" * persist_with_no_fields\";b:0;s:14:\"custom_storage\";b:0;s:10:\" * indexes\";a:0:{}s:10:\" * deleted\";b:0;}s:24:\"field_media_oembed_video\";O:38:\"Drupal\\field\\Entity\\FieldStorageConfig\":30:{s:15:\" * entityTypeId\";s:20:\"field_storage_config\";s:15:\" * enforceIsNew\";b:1;s:12:\" * typedData\";N;s:16:\" * cacheContexts\";a:0:{}s:12:\" * cacheTags\";a:0:{}s:14:\" * cacheMaxAge\";i:-1;s:14:\" * _serviceIds\";a:0:{}s:18:\" * _entityStorages\";a:0:{}s:13:\" * originalId\";s:30:\"media.field_media_oembed_video\";s:9:\" * status\";b:1;s:7:\" * uuid\";s:36:\"5e59b34b-09ae-4b6e-a0cc-35db6a383d00\";s:11:\" * langcode\";s:2:\"en\";s:23:\" * third_party_settings\";a:0:{}s:8:\" * _core\";a:1:{s:19:\"default_config_hash\";s:43:\"cNf_852Dq-fNnSaMI4LxL-J6N7bLkHuDbD9EUqOn4_U\";}s:14:\" * trustedData\";b:1;s:15:\" * dependencies\";a:1:{s:6:\"module\";a:1:{i:0;s:5:\"media\";}}s:12:\" * isSyncing\";b:0;s:5:\" * id\";s:30:\"media.field_media_oembed_video\";s:13:\" * field_name\";s:24:\"field_media_oembed_video\";s:14:\" * entity_type\";s:5:\"media\";s:7:\" * type\";s:6:\"string\";s:9:\" * module\";s:4:\"core\";s:11:\" * settings\";a:3:{s:10:\"max_length\";i:255;s:14:\"case_sensitive\";b:0;s:8:\"is_ascii\";b:0;}s:14:\" * cardinality\";i:1;s:15:\" * translatable\";b:1;s:9:\" * locked\";b:0;s:25:\" * persist_with_no_fields\";b:0;s:14:\"custom_storage\";b:0;s:10:\" * indexes\";a:0:{}s:10:\" * deleted\";b:0;}s:22:\"field_media_video_file\";O:38:\"Drupal\\field\\Entity\\FieldStorageConfig\":30:{s:15:\" * entityTypeId\";s:20:\"field_storage_config\";s:15:\" * enforceIsNew\";b:1;s:12:\" * typedData\";N;s:16:\" * cacheContexts\";a:0:{}s:12:\" * cacheTags\";a:0:{}s:14:\" * cacheMaxAge\";i:-1;s:14:\" * _serviceIds\";a:0:{}s:18:\" * _entityStorages\";a:0:{}s:13:\" * originalId\";s:28:\"media.field_media_video_file\";s:9:\" * status\";b:1;s:7:\" * uuid\";s:36:\"339c3ba9-788b-4cb7-864f-7f59c723cb32\";s:11:\" * langcode\";s:2:\"en\";s:23:\" * third_party_settings\";a:0:{}s:8:\" * _core\";a:1:{s:19:\"default_config_hash\";s:43:\"z5mgbn1PIVZ5TNMByBmivqo_u3Rdk58UIzpyN4ypTeM\";}s:14:\" * trustedData\";b:1;s:15:\" * dependencies\";a:1:{s:6:\"module\";a:2:{i:0;s:4:\"file\";i:1;s:5:\"media\";}}s:12:\" * isSyncing\";b:0;s:5:\" * id\";s:28:\"media.field_media_video_file\";s:13:\" * field_name\";s:22:\"field_media_video_file\";s:14:\" * entity_type\";s:5:\"media\";s:7:\" * type\";s:4:\"file\";s:9:\" * module\";s:4:\"file\";s:11:\" * settings\";a:4:{s:11:\"target_type\";s:4:\"file\";s:13:\"display_field\";b:0;s:15:\"display_default\";b:0;s:10:\"uri_scheme\";s:6:\"public\";}s:14:\" * cardinality\";i:1;s:15:\" * translatable\";b:1;s:9:\" * locked\";b:0;s:25:\" * persist_with_no_fields\";b:0;s:14:\"custom_storage\";b:0;s:10:\" * indexes\";a:0:{}s:10:\" * deleted\";b:0;}}",
  ))
  ->values(array(
    'collection' => 'entity.definitions.installed',
    'name' => 'media_type.entity_type',
    'value' => 'O:42:"Drupal\Core\Config\Entity\ConfigEntityType":44:{s:5:" * id";s:10:"media_type";s:8:" * class";s:29:"Drupal\media\Entity\MediaType";s:11:" * provider";s:5:"media";s:15:" * static_cache";b:0;s:15:" * render_cache";b:1;s:19:" * persistent_cache";b:1;s:14:" * entity_keys";a:9:{s:2:"id";s:2:"id";s:5:"label";s:5:"label";s:6:"status";s:6:"status";s:8:"revision";s:0:"";s:6:"bundle";s:0:"";s:8:"langcode";s:8:"langcode";s:16:"default_langcode";s:16:"default_langcode";s:29:"revision_translation_affected";s:29:"revision_translation_affected";s:4:"uuid";s:4:"uuid";}s:16:" * originalClass";s:29:"Drupal\media\Entity\MediaType";s:11:" * handlers";a:5:{s:6:"access";s:42:"Drupal\media\MediaTypeAccessControlHandler";s:4:"form";a:3:{s:3:"add";s:26:"Drupal\media\MediaTypeForm";s:4:"edit";s:26:"Drupal\media\MediaTypeForm";s:6:"delete";s:44:"Drupal\media\Form\MediaTypeDeleteConfirmForm";}s:12:"list_builder";s:33:"Drupal\media\MediaTypeListBuilder";s:14:"route_provider";a:2:{s:4:"html";s:51:"Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider";s:11:"permissions";s:49:"Drupal\user\Entity\EntityPermissionsRouteProvider";}s:7:"storage";s:45:"Drupal\Core\Config\Entity\ConfigEntityStorage";}s:19:" * admin_permission";s:22:"administer media types";s:24:" * collection_permission";N;s:25:" * permission_granularity";s:11:"entity_type";s:8:" * links";a:5:{s:8:"add-form";s:26:"/admin/structure/media/add";s:9:"edit-form";s:42:"/admin/structure/media/manage/{media_type}";s:11:"delete-form";s:49:"/admin/structure/media/manage/{media_type}/delete";s:23:"entity-permissions-form";s:54:"/admin/structure/media/manage/{media_type}/permissions";s:10:"collection";s:22:"/admin/structure/media";}s:21:" * bundle_entity_type";N;s:12:" * bundle_of";s:5:"media";s:15:" * bundle_label";N;s:13:" * base_table";N;s:22:" * revision_data_table";N;s:17:" * revision_table";N;s:13:" * data_table";N;s:11:" * internal";b:0;s:15:" * translatable";b:0;s:19:" * show_revision_ui";b:0;s:8:" * label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:10:"Media type";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:19:" * label_collection";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:11:"Media types";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:17:" * label_singular";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:10:"media type";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:15:" * label_plural";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:11:"media types";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}s:14:" * label_count";a:3:{s:8:"singular";s:17:"@count media type";s:6:"plural";s:18:"@count media types";s:7:"context";N;}s:15:" * uri_callback";N;s:8:" * group";s:13:"configuration";s:14:" * group_label";O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:13:"Configuration";s:12:" * arguments";a:0:{}s:10:" * options";a:1:{s:7:"context";s:17:"Entity type group";}}s:22:" * field_ui_base_route";N;s:26:" * common_reference_target";b:0;s:22:" * list_cache_contexts";a:0:{}s:18:" * list_cache_tags";a:1:{i:0;s:22:"config:media_type_list";}s:14:" * constraints";a:2:{s:19:"ImmutableProperties";a:2:{i:0;s:2:"id";i:1;s:6:"source";}s:23:"MediaMappingsConstraint";a:0:{}}s:13:" * additional";a:0:{}s:14:" * _serviceIds";a:0:{}s:18:" * _entityStorages";a:0:{}s:20:" * stringTranslation";N;s:16:" * config_prefix";s:4:"type";s:14:" * lookup_keys";a:1:{i:0;s:4:"uuid";}s:16:" * config_export";a:9:{i:0;s:2:"id";i:1;s:5:"label";i:2;s:11:"description";i:3;s:6:"source";i:4;s:25:"queue_thumbnail_downloads";i:5;s:12:"new_revision";i:6;s:20:"source_configuration";i:7;s:9:"field_map";i:8;s:6:"status";}s:21:" * mergedConfigExport";a:0:{}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.entity_schema_data',
    'value' => 'a:4:{s:5:"media";a:2:{s:11:"primary key";a:1:{i:0;s:3:"mid";}s:11:"unique keys";a:1:{s:10:"media__vid";a:1:{i:0;s:3:"vid";}}}s:14:"media_revision";a:2:{s:11:"primary key";a:1:{i:0;s:3:"vid";}s:7:"indexes";a:1:{s:10:"media__mid";a:1:{i:0;s:3:"mid";}}}s:16:"media_field_data";a:2:{s:11:"primary key";a:2:{i:0;s:3:"mid";i:1;s:8:"langcode";}s:7:"indexes";a:3:{s:37:"media__id__default_langcode__langcode";a:3:{i:0;s:3:"mid";i:1;s:16:"default_langcode";i:2;s:8:"langcode";}s:10:"media__vid";a:1:{i:0;s:3:"vid";}s:20:"media__status_bundle";a:3:{i:0;s:6:"status";i:1;s:6:"bundle";i:2;s:3:"mid";}}}s:20:"media_field_revision";a:2:{s:11:"primary key";a:2:{i:0;s:3:"vid";i:1;s:8:"langcode";}s:7:"indexes";a:1:{s:37:"media__id__default_langcode__langcode";a:3:{i:0;s:3:"mid";i:1;s:16:"default_langcode";i:2;s:8:"langcode";}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.bundle',
    'value' => 'a:2:{s:5:"media";a:2:{s:6:"fields";a:1:{s:6:"bundle";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;}}s:7:"indexes";a:1:{s:30:"media_field__bundle__target_id";a:1:{i:0;s:6:"bundle";}}}s:16:"media_field_data";a:2:{s:6:"fields";a:1:{s:6:"bundle";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;}}s:7:"indexes";a:1:{s:30:"media_field__bundle__target_id";a:1:{i:0;s:6:"bundle";}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.changed',
    'value' => 'a:2:{s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:7:"changed";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:0;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:7:"changed";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.created',
    'value' => 'a:2:{s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:7:"created";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:0;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:7:"created";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.default_langcode',
    'value' => 'a:2:{s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:16:"default_langcode";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:16:"default_langcode";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.field_media_audio_file',
    'value' => 'a:2:{s:29:"media__field_media_audio_file";a:5:{s:11:"description";s:52:"Data storage for media field field_media_audio_file.";s:6:"fields";a:9:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:32:"field_media_audio_file_target_id";a:4:{s:11:"description";s:26:"The ID of the file entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}s:30:"field_media_audio_file_display";a:6:{s:11:"description";s:75:"Flag to control whether this file should be displayed when viewing content.";s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"unsigned";b:1;s:7:"default";i:1;s:8:"not null";b:0;}s:34:"field_media_audio_file_description";a:3:{s:11:"description";s:26:"A description of the file.";s:4:"type";s:4:"text";s:8:"not null";b:0;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:3:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}s:32:"field_media_audio_file_target_id";a:1:{i:0;s:32:"field_media_audio_file_target_id";}}s:12:"foreign keys";a:1:{s:32:"field_media_audio_file_target_id";a:2:{s:5:"table";s:12:"file_managed";s:7:"columns";a:1:{s:32:"field_media_audio_file_target_id";s:3:"fid";}}}}s:38:"media_revision__field_media_audio_file";a:5:{s:11:"description";s:64:"Revision archive storage for media field field_media_audio_file.";s:6:"fields";a:9:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:32:"field_media_audio_file_target_id";a:4:{s:11:"description";s:26:"The ID of the file entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}s:30:"field_media_audio_file_display";a:6:{s:11:"description";s:75:"Flag to control whether this file should be displayed when viewing content.";s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"unsigned";b:1;s:7:"default";i:1;s:8:"not null";b:0;}s:34:"field_media_audio_file_description";a:3:{s:11:"description";s:26:"A description of the file.";s:4:"type";s:4:"text";s:8:"not null";b:0;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:3:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}s:32:"field_media_audio_file_target_id";a:1:{i:0;s:32:"field_media_audio_file_target_id";}}s:12:"foreign keys";a:1:{s:32:"field_media_audio_file_target_id";a:2:{s:5:"table";s:12:"file_managed";s:7:"columns";a:1:{s:32:"field_media_audio_file_target_id";s:3:"fid";}}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.field_media_document',
    'value' => 'a:2:{s:27:"media__field_media_document";a:5:{s:11:"description";s:50:"Data storage for media field field_media_document.";s:6:"fields";a:9:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:30:"field_media_document_target_id";a:4:{s:11:"description";s:26:"The ID of the file entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}s:28:"field_media_document_display";a:6:{s:11:"description";s:75:"Flag to control whether this file should be displayed when viewing content.";s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"unsigned";b:1;s:7:"default";i:1;s:8:"not null";b:0;}s:32:"field_media_document_description";a:3:{s:11:"description";s:26:"A description of the file.";s:4:"type";s:4:"text";s:8:"not null";b:0;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:3:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}s:30:"field_media_document_target_id";a:1:{i:0;s:30:"field_media_document_target_id";}}s:12:"foreign keys";a:1:{s:30:"field_media_document_target_id";a:2:{s:5:"table";s:12:"file_managed";s:7:"columns";a:1:{s:30:"field_media_document_target_id";s:3:"fid";}}}}s:36:"media_revision__field_media_document";a:5:{s:11:"description";s:62:"Revision archive storage for media field field_media_document.";s:6:"fields";a:9:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:30:"field_media_document_target_id";a:4:{s:11:"description";s:26:"The ID of the file entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}s:28:"field_media_document_display";a:6:{s:11:"description";s:75:"Flag to control whether this file should be displayed when viewing content.";s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"unsigned";b:1;s:7:"default";i:1;s:8:"not null";b:0;}s:32:"field_media_document_description";a:3:{s:11:"description";s:26:"A description of the file.";s:4:"type";s:4:"text";s:8:"not null";b:0;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:3:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}s:30:"field_media_document_target_id";a:1:{i:0;s:30:"field_media_document_target_id";}}s:12:"foreign keys";a:1:{s:30:"field_media_document_target_id";a:2:{s:5:"table";s:12:"file_managed";s:7:"columns";a:1:{s:30:"field_media_document_target_id";s:3:"fid";}}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.field_media_image',
    'value' => "a:2:{s:24:\"media__field_media_image\";a:5:{s:11:\"description\";s:47:\"Data storage for media field field_media_image.\";s:6:\"fields\";a:11:{s:6:\"bundle\";a:5:{s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:128;s:8:\"not null\";b:1;s:7:\"default\";s:0:\"\";s:11:\"description\";s:88:\"The field instance bundle to which this row belongs, used when deleting a field instance\";}s:7:\"deleted\";a:5:{s:4:\"type\";s:3:\"int\";s:4:\"size\";s:4:\"tiny\";s:8:\"not null\";b:1;s:7:\"default\";i:0;s:11:\"description\";s:60:\"A boolean indicating whether this data item has been deleted\";}s:9:\"entity_id\";a:4:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;s:11:\"description\";s:38:\"The entity id this data is attached to\";}s:11:\"revision_id\";a:4:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;s:11:\"description\";s:47:\"The entity revision id this data is attached to\";}s:8:\"langcode\";a:5:{s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:32;s:8:\"not null\";b:1;s:7:\"default\";s:0:\"\";s:11:\"description\";s:37:\"The language code for this data item.\";}s:5:\"delta\";a:4:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;s:11:\"description\";s:67:\"The sequence number for this data item, used for multi-value fields\";}s:27:\"field_media_image_target_id\";a:4:{s:11:\"description\";s:26:\"The ID of the file entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;}s:21:\"field_media_image_alt\";a:4:{s:11:\"description\";s:56:\"Alternative image text, for the image's 'alt' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:512;s:8:\"not null\";b:0;}s:23:\"field_media_image_title\";a:4:{s:11:\"description\";s:52:\"Image title text, for the image's 'title' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:1024;s:8:\"not null\";b:0;}s:23:\"field_media_image_width\";a:4:{s:11:\"description\";s:33:\"The width of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}s:24:\"field_media_image_height\";a:4:{s:11:\"description\";s:34:\"The height of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}}s:11:\"primary key\";a:4:{i:0;s:9:\"entity_id\";i:1;s:7:\"deleted\";i:2;s:5:\"delta\";i:3;s:8:\"langcode\";}s:7:\"indexes\";a:3:{s:6:\"bundle\";a:1:{i:0;s:6:\"bundle\";}s:11:\"revision_id\";a:1:{i:0;s:11:\"revision_id\";}s:27:\"field_media_image_target_id\";a:1:{i:0;s:27:\"field_media_image_target_id\";}}s:12:\"foreign keys\";a:1:{s:27:\"field_media_image_target_id\";a:2:{s:5:\"table\";s:12:\"file_managed\";s:7:\"columns\";a:1:{s:27:\"field_media_image_target_id\";s:3:\"fid\";}}}}s:33:\"media_revision__field_media_image\";a:5:{s:11:\"description\";s:59:\"Revision archive storage for media field field_media_image.\";s:6:\"fields\";a:11:{s:6:\"bundle\";a:5:{s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:128;s:8:\"not null\";b:1;s:7:\"default\";s:0:\"\";s:11:\"description\";s:88:\"The field instance bundle to which this row belongs, used when deleting a field instance\";}s:7:\"deleted\";a:5:{s:4:\"type\";s:3:\"int\";s:4:\"size\";s:4:\"tiny\";s:8:\"not null\";b:1;s:7:\"default\";i:0;s:11:\"description\";s:60:\"A boolean indicating whether this data item has been deleted\";}s:9:\"entity_id\";a:4:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;s:11:\"description\";s:38:\"The entity id this data is attached to\";}s:11:\"revision_id\";a:4:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;s:11:\"description\";s:47:\"The entity revision id this data is attached to\";}s:8:\"langcode\";a:5:{s:4:\"type\";s:13:\"varchar_ascii\";s:6:\"length\";i:32;s:8:\"not null\";b:1;s:7:\"default\";s:0:\"\";s:11:\"description\";s:37:\"The language code for this data item.\";}s:5:\"delta\";a:4:{s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;s:11:\"description\";s:67:\"The sequence number for this data item, used for multi-value fields\";}s:27:\"field_media_image_target_id\";a:4:{s:11:\"description\";s:26:\"The ID of the file entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:1;}s:21:\"field_media_image_alt\";a:4:{s:11:\"description\";s:56:\"Alternative image text, for the image's 'alt' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:512;s:8:\"not null\";b:0;}s:23:\"field_media_image_title\";a:4:{s:11:\"description\";s:52:\"Image title text, for the image's 'title' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:1024;s:8:\"not null\";b:0;}s:23:\"field_media_image_width\";a:4:{s:11:\"description\";s:33:\"The width of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}s:24:\"field_media_image_height\";a:4:{s:11:\"description\";s:34:\"The height of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}}s:11:\"primary key\";a:5:{i:0;s:9:\"entity_id\";i:1;s:11:\"revision_id\";i:2;s:7:\"deleted\";i:3;s:5:\"delta\";i:4;s:8:\"langcode\";}s:7:\"indexes\";a:3:{s:6:\"bundle\";a:1:{i:0;s:6:\"bundle\";}s:11:\"revision_id\";a:1:{i:0;s:11:\"revision_id\";}s:27:\"field_media_image_target_id\";a:1:{i:0;s:27:\"field_media_image_target_id\";}}s:12:\"foreign keys\";a:1:{s:27:\"field_media_image_target_id\";a:2:{s:5:\"table\";s:12:\"file_managed\";s:7:\"columns\";a:1:{s:27:\"field_media_image_target_id\";s:3:\"fid\";}}}}}",
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.field_media_oembed_video',
    'value' => 'a:2:{s:31:"media__field_media_oembed_video";a:4:{s:11:"description";s:54:"Data storage for media field field_media_oembed_video.";s:6:"fields";a:7:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:30:"field_media_oembed_video_value";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:255;s:6:"binary";b:0;s:8:"not null";b:1;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}s:40:"media_revision__field_media_oembed_video";a:4:{s:11:"description";s:66:"Revision archive storage for media field field_media_oembed_video.";s:6:"fields";a:7:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:30:"field_media_oembed_video_value";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:255;s:6:"binary";b:0;s:8:"not null";b:1;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:2:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.field_media_video_file',
    'value' => 'a:2:{s:29:"media__field_media_video_file";a:5:{s:11:"description";s:52:"Data storage for media field field_media_video_file.";s:6:"fields";a:9:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:32:"field_media_video_file_target_id";a:4:{s:11:"description";s:26:"The ID of the file entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}s:30:"field_media_video_file_display";a:6:{s:11:"description";s:75:"Flag to control whether this file should be displayed when viewing content.";s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"unsigned";b:1;s:7:"default";i:1;s:8:"not null";b:0;}s:34:"field_media_video_file_description";a:3:{s:11:"description";s:26:"A description of the file.";s:4:"type";s:4:"text";s:8:"not null";b:0;}}s:11:"primary key";a:4:{i:0;s:9:"entity_id";i:1;s:7:"deleted";i:2;s:5:"delta";i:3;s:8:"langcode";}s:7:"indexes";a:3:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}s:32:"field_media_video_file_target_id";a:1:{i:0;s:32:"field_media_video_file_target_id";}}s:12:"foreign keys";a:1:{s:32:"field_media_video_file_target_id";a:2:{s:5:"table";s:12:"file_managed";s:7:"columns";a:1:{s:32:"field_media_video_file_target_id";s:3:"fid";}}}}s:38:"media_revision__field_media_video_file";a:5:{s:11:"description";s:64:"Revision archive storage for media field field_media_video_file.";s:6:"fields";a:9:{s:6:"bundle";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:88:"The field instance bundle to which this row belongs, used when deleting a field instance";}s:7:"deleted";a:5:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;s:7:"default";i:0;s:11:"description";s:60:"A boolean indicating whether this data item has been deleted";}s:9:"entity_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:38:"The entity id this data is attached to";}s:11:"revision_id";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:47:"The entity revision id this data is attached to";}s:8:"langcode";a:5:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:32;s:8:"not null";b:1;s:7:"default";s:0:"";s:11:"description";s:37:"The language code for this data item.";}s:5:"delta";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;s:11:"description";s:67:"The sequence number for this data item, used for multi-value fields";}s:32:"field_media_video_file_target_id";a:4:{s:11:"description";s:26:"The ID of the file entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}s:30:"field_media_video_file_display";a:6:{s:11:"description";s:75:"Flag to control whether this file should be displayed when viewing content.";s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"unsigned";b:1;s:7:"default";i:1;s:8:"not null";b:0;}s:34:"field_media_video_file_description";a:3:{s:11:"description";s:26:"A description of the file.";s:4:"type";s:4:"text";s:8:"not null";b:0;}}s:11:"primary key";a:5:{i:0;s:9:"entity_id";i:1;s:11:"revision_id";i:2;s:7:"deleted";i:3;s:5:"delta";i:4;s:8:"langcode";}s:7:"indexes";a:3:{s:6:"bundle";a:1:{i:0;s:6:"bundle";}s:11:"revision_id";a:1:{i:0;s:11:"revision_id";}s:32:"field_media_video_file_target_id";a:1:{i:0;s:32:"field_media_video_file_target_id";}}s:12:"foreign keys";a:1:{s:32:"field_media_video_file_target_id";a:2:{s:5:"table";s:12:"file_managed";s:7:"columns";a:1:{s:32:"field_media_video_file_target_id";s:3:"fid";}}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.langcode',
    'value' => 'a:4:{s:5:"media";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}s:14:"media_revision";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:8:"langcode";a:3:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:12;s:8:"not null";b:1;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.mid',
    'value' => 'a:4:{s:5:"media";a:1:{s:6:"fields";a:1:{s:3:"mid";a:4:{s:4:"type";s:6:"serial";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:3:"mid";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:14:"media_revision";a:1:{s:6:"fields";a:1:{s:3:"mid";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:3:"mid";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.name',
    'value' => 'a:2:{s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:4:"name";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:255;s:6:"binary";b:0;s:8:"not null";b:0;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:4:"name";a:4:{s:4:"type";s:7:"varchar";s:6:"length";i:255;s:6:"binary";b:0;s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.revision_created',
    'value' => 'a:1:{s:14:"media_revision";a:1:{s:6:"fields";a:1:{s:16:"revision_created";a:2:{s:4:"type";s:3:"int";s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.revision_default',
    'value' => 'a:1:{s:14:"media_revision";a:1:{s:6:"fields";a:1:{s:16:"revision_default";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.revision_log_message',
    'value' => 'a:1:{s:14:"media_revision";a:1:{s:6:"fields";a:1:{s:20:"revision_log_message";a:3:{s:4:"type";s:4:"text";s:4:"size";s:3:"big";s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.revision_translation_affected',
    'value' => 'a:2:{s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:29:"revision_translation_affected";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:0;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:29:"revision_translation_affected";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:0;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.revision_user',
    'value' => 'a:1:{s:14:"media_revision";a:2:{s:6:"fields";a:1:{s:13:"revision_user";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:0;}}s:7:"indexes";a:1:{s:37:"media_field__revision_user__target_id";a:1:{i:0;s:13:"revision_user";}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.status',
    'value' => 'a:2:{s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:6:"status";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:6:"status";a:3:{s:4:"type";s:3:"int";s:4:"size";s:4:"tiny";s:8:"not null";b:1;}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.thumbnail',
    'value' => "a:2:{s:16:\"media_field_data\";a:3:{s:6:\"fields\";a:5:{s:20:\"thumbnail__target_id\";a:4:{s:11:\"description\";s:26:\"The ID of the file entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}s:14:\"thumbnail__alt\";a:4:{s:11:\"description\";s:56:\"Alternative image text, for the image's 'alt' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:512;s:8:\"not null\";b:0;}s:16:\"thumbnail__title\";a:4:{s:11:\"description\";s:52:\"Image title text, for the image's 'title' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:1024;s:8:\"not null\";b:0;}s:16:\"thumbnail__width\";a:4:{s:11:\"description\";s:33:\"The width of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}s:17:\"thumbnail__height\";a:4:{s:11:\"description\";s:34:\"The height of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}}s:7:\"indexes\";a:1:{s:33:\"media_field__thumbnail__target_id\";a:1:{i:0;s:20:\"thumbnail__target_id\";}}s:12:\"foreign keys\";a:1:{s:33:\"media_field__thumbnail__target_id\";a:2:{s:5:\"table\";s:12:\"file_managed\";s:7:\"columns\";a:1:{s:20:\"thumbnail__target_id\";s:3:\"fid\";}}}}s:20:\"media_field_revision\";a:3:{s:6:\"fields\";a:5:{s:20:\"thumbnail__target_id\";a:4:{s:11:\"description\";s:26:\"The ID of the file entity.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}s:14:\"thumbnail__alt\";a:4:{s:11:\"description\";s:56:\"Alternative image text, for the image's 'alt' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:512;s:8:\"not null\";b:0;}s:16:\"thumbnail__title\";a:4:{s:11:\"description\";s:52:\"Image title text, for the image's 'title' attribute.\";s:4:\"type\";s:7:\"varchar\";s:6:\"length\";i:1024;s:8:\"not null\";b:0;}s:16:\"thumbnail__width\";a:4:{s:11:\"description\";s:33:\"The width of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}s:17:\"thumbnail__height\";a:4:{s:11:\"description\";s:34:\"The height of the image in pixels.\";s:4:\"type\";s:3:\"int\";s:8:\"unsigned\";b:1;s:8:\"not null\";b:0;}}s:7:\"indexes\";a:1:{s:33:\"media_field__thumbnail__target_id\";a:1:{i:0;s:20:\"thumbnail__target_id\";}}s:12:\"foreign keys\";a:1:{s:33:\"media_field__thumbnail__target_id\";a:2:{s:5:\"table\";s:12:\"file_managed\";s:7:\"columns\";a:1:{s:20:\"thumbnail__target_id\";s:3:\"fid\";}}}}}",
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.uid',
    'value' => 'a:2:{s:16:"media_field_data";a:2:{s:6:"fields";a:1:{s:3:"uid";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}}s:7:"indexes";a:1:{s:27:"media_field__uid__target_id";a:1:{i:0;s:3:"uid";}}}s:20:"media_field_revision";a:2:{s:6:"fields";a:1:{s:3:"uid";a:4:{s:11:"description";s:28:"The ID of the target entity.";s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:8:"not null";b:1;}}s:7:"indexes";a:1:{s:27:"media_field__uid__target_id";a:1:{i:0;s:3:"uid";}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.uuid',
    'value' => 'a:1:{s:5:"media";a:2:{s:6:"fields";a:1:{s:4:"uuid";a:4:{s:4:"type";s:13:"varchar_ascii";s:6:"length";i:128;s:6:"binary";b:0;s:8:"not null";b:1;}}s:11:"unique keys";a:1:{s:24:"media_field__uuid__value";a:1:{i:0;s:4:"uuid";}}}}',
  ))
  ->values(array(
    'collection' => 'entity.storage_schema.sql',
    'name' => 'media.field_schema_data.vid',
    'value' => 'a:4:{s:5:"media";a:1:{s:6:"fields";a:1:{s:3:"vid";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:0;}}}s:16:"media_field_data";a:1:{s:6:"fields";a:1:{s:3:"vid";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:14:"media_revision";a:1:{s:6:"fields";a:1:{s:3:"vid";a:4:{s:4:"type";s:6:"serial";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}s:20:"media_field_revision";a:1:{s:6:"fields";a:1:{s:3:"vid";a:4:{s:4:"type";s:3:"int";s:8:"unsigned";b:1;s:4:"size";s:6:"normal";s:8:"not null";b:1;}}}}',
  ))
  ->execute();

// Update routing.non_admin_routes state to include the new non-admin routes.
$non_admin_routes = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'state')
  ->condition('name', 'routing.non_admin_routes')
  ->execute()
  ->fetchField();
$non_admin_routes = unserialize($non_admin_routes);
$non_admin_routes[] = 'media.oembed_iframe';
$non_admin_routes[] = 'media.filter.preview';
$non_admin_routes[] = 'entity.media.add_page';
$non_admin_routes[] = 'entity.media.canonical';
$non_admin_routes[] = 'entity.media.edit_form';
$non_admin_routes[] = 'entity.media.delete_form';
$non_admin_routes[] = 'entity.media.delete_multiple_form';
$non_admin_routes[] = 'entity.media.version_history';
$non_admin_routes[] = 'entity.media.revision';
$non_admin_routes[] = 'entity.media.revision_revert_form';
$non_admin_routes[] = 'entity.media.revision_delete_form';
$connection->update('key_value')
  ->fields(['value' => serialize($non_admin_routes)])
  ->condition('collection', 'state')
  ->condition('name', 'routing.non_admin_routes')
  ->execute();

  // Update 'router.path_roots',
$router_path_roots = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'state')
  ->condition('name', 'router.path_roots')
  ->execute()
  ->fetchField();
$router_path_roots = unserialize($router_path_roots);
$router_path_roots[] = 'media';
$connection->update('key_value')
  ->fields(['value' => serialize($router_path_roots)])
  ->condition('collection', 'state')
  ->condition('name', 'router.path_roots')
  ->execute();

// update views.view_route_names
$views_route_names = $connection->select('key_value')
  ->fields('key_value', ['value'])
  ->condition('collection', 'state')
  ->condition('name', 'views.view_route_names')
  ->execute()
  ->fetchField();
$views_route_names = unserialize($views_route_names);
$views_route_names[] = 'media.media_page_list';
$views_route_names[] = 'entity.media.collection';
$connection->update('key_value')
  ->fields(['value' => serialize($views_route_names)])
  ->condition('collection', 'state')
  ->condition('name', 'views.view_route_names')
  ->execute();

$connection->schema()->createTable('media', array(
  'fields' => array(
    'mid' => array(
      'type' => 'serial',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'vid' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
    ),
    'uuid' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '12',
    ),
  ),
  'primary key' => array(
    'mid',
  ),
  'unique keys' => array(
    'media_field__uuid__value' => array(
      'uuid',
    ),
    'media__vid' => array(
      'vid',
    ),
  ),
  'indexes' => array(
    'media_field__bundle__target_id' => array(
      'bundle',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media__field_media_audio_file', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_audio_file_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_audio_file_display' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
      'default' => '1',
      'unsigned' => TRUE,
    ),
    'field_media_audio_file_description' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_audio_file_target_id' => array(
      'field_media_audio_file_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media__field_media_document', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_document_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_document_display' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
      'default' => '1',
      'unsigned' => TRUE,
    ),
    'field_media_document_description' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_document_target_id' => array(
      'field_media_document_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media__field_media_image', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_image_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_image_alt' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '512',
    ),
    'field_media_image_title' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '1024',
    ),
    'field_media_image_width' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_image_height' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
  ),
  'primary key' => array(
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_image_target_id' => array(
      'field_media_image_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media__field_media_oembed_video', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_oembed_video_value' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media__field_media_video_file', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_video_file_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_video_file_display' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
      'default' => '1',
      'unsigned' => TRUE,
    ),
    'field_media_video_file_description' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_video_file_target_id' => array(
      'field_media_video_file_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_field_data', array(
  'fields' => array(
    'mid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'vid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '12',
    ),
    'status' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
    ),
    'uid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'name' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '255',
    ),
    'thumbnail__target_id' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'thumbnail__alt' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '512',
    ),
    'thumbnail__title' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '1024',
    ),
    'thumbnail__width' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'thumbnail__height' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'created' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ),
    'changed' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ),
    'default_langcode' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
    ),
    'revision_translation_affected' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
    ),
  ),
  'primary key' => array(
    'mid',
    'langcode',
  ),
  'indexes' => array(
    'media__id__default_langcode__langcode' => array(
      'mid',
      'default_langcode',
      'langcode',
    ),
    'media__vid' => array(
      'vid',
    ),
    'media_field__bundle__target_id' => array(
      'bundle',
    ),
    'media_field__uid__target_id' => array(
      'uid',
    ),
    'media_field__thumbnail__target_id' => array(
      'thumbnail__target_id',
    ),
    'media__status_bundle' => array(
      'status',
      'bundle',
      'mid',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_field_revision', array(
  'fields' => array(
    'mid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'vid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '12',
    ),
    'status' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
    ),
    'uid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'name' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '255',
    ),
    'thumbnail__target_id' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'thumbnail__alt' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '512',
    ),
    'thumbnail__title' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '1024',
    ),
    'thumbnail__width' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'thumbnail__height' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'created' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ),
    'changed' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ),
    'default_langcode' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
    ),
    'revision_translation_affected' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
    ),
  ),
  'primary key' => array(
    'vid',
    'langcode',
  ),
  'indexes' => array(
    'media__id__default_langcode__langcode' => array(
      'mid',
      'default_langcode',
      'langcode',
    ),
    'media_field__uid__target_id' => array(
      'uid',
    ),
    'media_field__thumbnail__target_id' => array(
      'thumbnail__target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_revision', array(
  'fields' => array(
    'mid' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'vid' => array(
      'type' => 'serial',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '12',
    ),
    'revision_user' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_created' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
    ),
    'revision_log_message' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'big',
    ),
    'revision_default' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
    ),
  ),
  'primary key' => array(
    'vid',
  ),
  'indexes' => array(
    'media__mid' => array(
      'mid',
    ),
    'media_field__revision_user__target_id' => array(
      'revision_user',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_revision__field_media_audio_file', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_audio_file_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_audio_file_display' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
      'default' => '1',
      'unsigned' => TRUE,
    ),
    'field_media_audio_file_description' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_audio_file_target_id' => array(
      'field_media_audio_file_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_revision__field_media_document', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_document_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_document_display' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
      'default' => '1',
      'unsigned' => TRUE,
    ),
    'field_media_document_description' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_document_target_id' => array(
      'field_media_document_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_revision__field_media_image', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_image_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_image_alt' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '512',
    ),
    'field_media_image_title' => array(
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '1024',
    ),
    'field_media_image_width' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_image_height' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
  ),
  'primary key' => array(
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_image_target_id' => array(
      'field_media_image_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_revision__field_media_oembed_video', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_oembed_video_value' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

$connection->schema()->createTable('media_revision__field_media_video_file', array(
  'fields' => array(
    'bundle' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'deleted' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ),
    'entity_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'revision_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'langcode' => array(
      'type' => 'varchar_ascii',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ),
    'delta' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_video_file_target_id' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ),
    'field_media_video_file_display' => array(
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'tiny',
      'default' => '1',
      'unsigned' => TRUE,
    ),
    'field_media_video_file_description' => array(
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'entity_id',
    'revision_id',
    'deleted',
    'delta',
    'langcode',
  ),
  'indexes' => array(
    'bundle' => array(
      'bundle',
    ),
    'revision_id' => array(
      'revision_id',
    ),
    'field_media_video_file_target_id' => array(
      'field_media_video_file_target_id',
    ),
  ),
  'mysql_character_set' => 'utf8mb4',
));

// Insert the menu items.
$connection->insert('menu_tree')
  ->fields([
    'menu_name',
    'id',
    'parent',
    'route_name',
    'route_param_key',
    'route_parameters',
    'url',
    'title',
    'description',
    'class',
    'options',
    'provider',
    'enabled',
    'discovered',
    'expanded',
    'weight',
    'metadata',
    'has_children',
    'depth',
    'p1',
    'p2',
    'p3',
    'p4',
    'p5',
    'p6',
    'p7',
    'p8',
    'p9',
    'form_class',
  ])
  ->values(array(
    'menu_name' => 'admin',
    'mlid' => '67',
    'id' => 'entity.media_type.collection',
    'parent' => 'system.admin_structure',
    'route_name' => 'entity.media_type.collection',
    'route_param_key' => '',
    'route_parameters' => 'a:0:{}',
    'url' => '',
    'title' => 'O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:11:"Media types";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}',
    'description' => 'O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:19:"Manage media types.";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}',
    'class' => 'Drupal\Core\Menu\MenuLinkDefault',
    'options' => 'a:0:{}',
    'provider' => 'media',
    'enabled' => '1',
    'discovered' => '1',
    'expanded' => '0',
    'weight' => '0',
    'metadata' => 'a:0:{}',
    'has_children' => '0',
    'depth' => '3',
    'p1' => '6',
    'p2' => '11',
    'p3' => '67',
    'p4' => '0',
    'p5' => '0',
    'p6' => '0',
    'p7' => '0',
    'p8' => '0',
    'p9' => '0',
    'form_class' => 'Drupal\Core\Menu\Form\MenuLinkDefaultForm',
  ))
  ->values(array(
    'menu_name' => 'admin',
    'mlid' => '68',
    'id' => 'media.settings',
    'parent' => 'system.admin_config_media',
    'route_name' => 'media.settings',
    'route_param_key' => '',
    'route_parameters' => 'a:0:{}',
    'url' => '',
    'title' => 'O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:14:"Media settings";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}',
    'description' => 'O:48:"Drupal\Core\StringTranslation\TranslatableMarkup":3:{s:9:" * string";s:22:"Manage media settings.";s:12:" * arguments";a:0:{}s:10:" * options";a:0:{}}',
    'class' => 'Drupal\Core\Menu\MenuLinkDefault',
    'options' => 'a:0:{}',
    'provider' => 'media',
    'enabled' => '1',
    'discovered' => '1',
    'expanded' => '0',
    'weight' => '0',
    'metadata' => 'a:0:{}',
    'has_children' => '0',
    'depth' => '4',
    'p1' => '6',
    'p2' => '25',
    'p3' => '26',
    'p4' => '68',
    'p5' => '0',
    'p6' => '0',
    'p7' => '0',
    'p8' => '0',
    'p9' => '0',
    'form_class' => 'Drupal\Core\Menu\Form\MenuLinkDefaultForm',
  ))
  ->execute();

// Insert the routes.
$connection->insert('router')
  ->fields([
    'name',
    'path',
    'pattern_outline',
    'fit',
    'route',
    'number_parts',
  ])
  ->values(array(
    'name' => 'entity.entity_form_display.media.default',
    'path' => '/admin/structure/media/manage/{media_type}/form-display',
    'pattern_outline' => '/admin/structure/media/manage/%/form-display',
    'fit' => '61',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:55:"/admin/structure/media/manage/{media_type}/form-display";s:4:"host";s:0:"";s:8:"defaults";a:5:{s:12:"_entity_form";s:24:"entity_form_display.edit";s:6:"_title";s:19:"Manage form display";s:14:"form_mode_name";s:7:"default";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:26:"_field_ui_form_mode_access";s:29:"administer media form display";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:31:"access_check.field_ui.form_mode";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:73:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/form\-display$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:13:"/form-display";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:61;s:14:"patternOutline";s:44:"/admin/structure/media/manage/%/form-display";s:8:"numParts";i:6;}}',
    'number_parts' => '6',
  ))
  ->values(array(
    'name' => 'entity.entity_form_display.media.form_mode',
    'path' => '/admin/structure/media/manage/{media_type}/form-display/{form_mode_name}',
    'pattern_outline' => '/admin/structure/media/manage/%/form-display/%',
    'fit' => '122',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:72:"/admin/structure/media/manage/{media_type}/form-display/{form_mode_name}";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:12:"_entity_form";s:24:"entity_form_display.edit";s:6:"_title";s:19:"Manage form display";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:26:"_field_ui_form_mode_access";s:29:"administer media form display";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:31:"access_check.field_ui.form_mode";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:10:"media_type";i:1;s:14:"form_mode_name";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:100:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/form\-display/(?P<form_mode_name>[^/]++)$}sDu";s:11:"path_tokens";a:4:{i:0;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:14:"form_mode_name";i:4;b:1;}i:1;a:2:{i:0;s:4:"text";i:1;s:13:"/form-display";}i:2;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:3;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:2:{i:0;s:10:"media_type";i:1;s:14:"form_mode_name";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:122;s:14:"patternOutline";s:46:"/admin/structure/media/manage/%/form-display/%";s:8:"numParts";i:7;}}',
    'number_parts' => '7',
  ))
  ->values(array(
    'name' => 'entity.entity_view_display.media.default',
    'path' => '/admin/structure/media/manage/{media_type}/display',
    'pattern_outline' => '/admin/structure/media/manage/%/display',
    'fit' => '61',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:50:"/admin/structure/media/manage/{media_type}/display";s:4:"host";s:0:"";s:8:"defaults";a:5:{s:12:"_entity_form";s:24:"entity_view_display.edit";s:6:"_title";s:14:"Manage display";s:14:"view_mode_name";s:7:"default";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:26:"_field_ui_view_mode_access";s:24:"administer media display";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:31:"access_check.field_ui.view_mode";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:67:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/display$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:8:"/display";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:61;s:14:"patternOutline";s:39:"/admin/structure/media/manage/%/display";s:8:"numParts";i:6;}}',
    'number_parts' => '6',
  ))
  ->values(array(
    'name' => 'entity.entity_view_display.media.view_mode',
    'path' => '/admin/structure/media/manage/{media_type}/display/{view_mode_name}',
    'pattern_outline' => '/admin/structure/media/manage/%/display/%',
    'fit' => '122',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:67:"/admin/structure/media/manage/{media_type}/display/{view_mode_name}";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:12:"_entity_form";s:24:"entity_view_display.edit";s:6:"_title";s:14:"Manage display";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:26:"_field_ui_view_mode_access";s:24:"administer media display";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:31:"access_check.field_ui.view_mode";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:10:"media_type";i:1;s:14:"view_mode_name";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:94:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/display/(?P<view_mode_name>[^/]++)$}sDu";s:11:"path_tokens";a:4:{i:0;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:14:"view_mode_name";i:4;b:1;}i:1;a:2:{i:0;s:4:"text";i:1;s:8:"/display";}i:2;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:3;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:2:{i:0;s:10:"media_type";i:1;s:14:"view_mode_name";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:122;s:14:"patternOutline";s:41:"/admin/structure/media/manage/%/display/%";s:8:"numParts";i:7;}}',
    'number_parts' => '7',
  ))
  ->values(array(
    'name' => 'entity.field_config.media_field_delete_form',
    'path' => '/admin/structure/media/manage/{media_type}/fields/{field_config}/delete',
    'pattern_outline' => '/admin/structure/media/manage/%/fields/%/delete',
    'fit' => '245',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:71:"/admin/structure/media/manage/{media_type}/fields/{field_config}/delete";s:4:"host";s:0:"";s:8:"defaults";a:3:{s:12:"_entity_form";s:19:"field_config.delete";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:14:"_entity_access";s:19:"field_config.delete";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:2:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}s:12:"field_config";a:2:{s:4:"type";s:19:"entity:field_config";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:10:"media_type";i:1;s:12:"field_config";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:98:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/fields/(?P<field_config>[^/]++)/delete$}sDu";s:11:"path_tokens";a:5:{i:0;a:2:{i:0;s:4:"text";i:1;s:7:"/delete";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:12:"field_config";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:7:"/fields";}i:3;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:4;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:2:{i:0;s:10:"media_type";i:1;s:12:"field_config";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:245;s:14:"patternOutline";s:47:"/admin/structure/media/manage/%/fields/%/delete";s:8:"numParts";i:8;}}',
    'number_parts' => '8',
  ))
  ->values(array(
    'name' => 'entity.field_config.media_field_edit_form',
    'path' => '/admin/structure/media/manage/{media_type}/fields/{field_config}',
    'pattern_outline' => '/admin/structure/media/manage/%/fields/%',
    'fit' => '122',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:64:"/admin/structure/media/manage/{media_type}/fields/{field_config}";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:12:"_entity_form";s:17:"field_config.edit";s:15:"_title_callback";s:51:"\Drupal\field_ui\Form\FieldConfigEditForm::getTitle";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:14:"_entity_access";s:19:"field_config.update";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:2:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}s:12:"field_config";a:2:{s:4:"type";s:19:"entity:field_config";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:10:"media_type";i:1;s:12:"field_config";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:91:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/fields/(?P<field_config>[^/]++)$}sDu";s:11:"path_tokens";a:4:{i:0;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:12:"field_config";i:4;b:1;}i:1;a:2:{i:0;s:4:"text";i:1;s:7:"/fields";}i:2;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:3;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:2:{i:0;s:10:"media_type";i:1;s:12:"field_config";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:122;s:14:"patternOutline";s:40:"/admin/structure/media/manage/%/fields/%";s:8:"numParts";i:7;}}',
    'number_parts' => '7',
  ))
  ->values(array(
    'name' => 'entity.media.add_form',
    'path' => '/media/add/{media_type}',
    'pattern_outline' => '/media/add/%',
    'fit' => '6',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:23:"/media/add/{media_type}";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:12:"_entity_form";s:9:"media.add";s:14:"entity_type_id";s:5:"media";s:15:"_title_callback";s:62:"Drupal\Core\Entity\Controller\EntityController::addBundleTitle";s:16:"bundle_parameter";s:10:"media_type";}s:12:"requirements";a:1:{s:21:"_entity_create_access";s:18:"media:{media_type}";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:3:{s:4:"type";s:17:"entity:media_type";s:21:"with_config_overrides";b:1;s:9:"converter";s:21:"paramconverter.entity";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:26:"access_check.entity_create";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:40:"{^/media/add/(?P<media_type>[^/]++)$}sDu";s:11:"path_tokens";a:2:{i:0;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:1;a:2:{i:0;s:4:"text";i:1;s:10:"/media/add";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:6;s:14:"patternOutline";s:12:"/media/add/%";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media.add_page',
    'path' => '/media/add',
    'pattern_outline' => '/media/add',
    'fit' => '3',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:10:"/media/add";s:4:"host";s:0:"";s:8:"defaults";a:3:{s:11:"_controller";s:55:"Drupal\Core\Entity\Controller\EntityController::addPage";s:15:"_title_callback";s:56:"Drupal\Core\Entity\Controller\EntityController::addTitle";s:14:"entity_type_id";s:5:"media";}s:12:"requirements";a:1:{s:25:"_entity_create_any_access";s:5:"media";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:30:"access_check.entity_create_any";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:17:"{^/media/add$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:10:"/media/add";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:3;s:14:"patternOutline";s:10:"/media/add";s:8:"numParts";i:2;}}',
    'number_parts' => '2',
  ))
  ->values(array(
    'name' => 'entity.media.canonical',
    'path' => '/media/{media}/edit',
    'pattern_outline' => '/media/%/edit',
    'fit' => '5',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:19:"/media/{media}/edit";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:10:"media.edit";s:15:"_title_callback";s:58:"\Drupal\Core\Entity\Controller\EntityController::editTitle";}s:12:"requirements";a:2:{s:14:"_entity_access";s:12:"media.update";s:5:"media";s:3:"\d+";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:5:"media";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:33:"{^/media/(?P<media>\d+)/edit$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:5:"/edit";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:3:"\d+";i:3;s:5:"media";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:1:{i:0;s:5:"media";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:5;s:14:"patternOutline";s:13:"/media/%/edit";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media.collection',
    'path' => '/admin/content/media',
    'pattern_outline' => '/admin/content/media',
    'fit' => '7',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:20:"/admin/content/media";s:4:"host";s:0:"";s:8:"defaults";a:5:{s:11:"_controller";s:47:"Drupal\views\Routing\ViewPageController::handle";s:15:"_title_callback";s:49:"Drupal\views\Routing\ViewPageController::getTitle";s:7:"view_id";s:5:"media";s:10:"display_id";s:15:"media_page_list";s:30:"_view_display_show_admin_links";b:1;}s:12:"requirements";a:2:{s:11:"_permission";s:21:"access media overview";s:7:"_format";s:4:"html";}s:7:"options";a:9:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:18:"_view_argument_map";a:0:{}s:23:"_view_display_plugin_id";s:4:"page";s:26:"_view_display_plugin_class";s:38:"Drupal\views\Plugin\views\display\Page";s:30:"_view_display_show_admin_links";b:1;s:16:"returns_response";b:0;s:4:"utf8";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:27:"{^/admin/content/media$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:20:"/admin/content/media";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:7;s:14:"patternOutline";s:20:"/admin/content/media";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media.delete_form',
    'path' => '/media/{media}/delete',
    'pattern_outline' => '/media/%/delete',
    'fit' => '5',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:21:"/media/{media}/delete";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:12:"media.delete";s:15:"_title_callback";s:60:"\Drupal\Core\Entity\Controller\EntityController::deleteTitle";}s:12:"requirements";a:2:{s:14:"_entity_access";s:12:"media.delete";s:5:"media";s:3:"\d+";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:5:"media";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:35:"{^/media/(?P<media>\d+)/delete$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:7:"/delete";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:3:"\d+";i:3;s:5:"media";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:1:{i:0;s:5:"media";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:5;s:14:"patternOutline";s:15:"/media/%/delete";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media.delete_multiple_form',
    'path' => '/media/delete',
    'pattern_outline' => '/media/delete',
    'fit' => '3',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:13:"/media/delete";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:5:"_form";s:42:"Drupal\Core\Entity\Form\DeleteMultipleForm";s:14:"entity_type_id";s:5:"media";}s:12:"requirements";a:1:{s:30:"_entity_delete_multiple_access";s:5:"media";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:35:"access_check.entity_delete_multiple";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:20:"{^/media/delete$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:13:"/media/delete";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:3;s:14:"patternOutline";s:13:"/media/delete";s:8:"numParts";i:2;}}',
    'number_parts' => '2',
  ))
  ->values(array(
    'name' => 'entity.media.edit_form',
    'path' => '/media/{media}/edit',
    'pattern_outline' => '/media/%/edit',
    'fit' => '5',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:19:"/media/{media}/edit";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:10:"media.edit";s:15:"_title_callback";s:58:"\Drupal\Core\Entity\Controller\EntityController::editTitle";}s:12:"requirements";a:2:{s:14:"_entity_access";s:12:"media.update";s:5:"media";s:3:"\d+";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:5:"media";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:33:"{^/media/(?P<media>\d+)/edit$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:5:"/edit";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:3:"\d+";i:3;s:5:"media";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:1:{i:0;s:5:"media";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:5;s:14:"patternOutline";s:13:"/media/%/edit";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media.field_ui_fields',
    'path' => '/admin/structure/media/manage/{media_type}/fields',
    'pattern_outline' => '/admin/structure/media/manage/%/fields',
    'fit' => '61',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:49:"/admin/structure/media/manage/{media_type}/fields";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:11:"_controller";s:62:"\Drupal\field_ui\Controller\FieldConfigListController::listing";s:6:"_title";s:13:"Manage fields";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:11:"_permission";s:23:"administer media fields";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:66:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/fields$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:7:"/fields";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:61;s:14:"patternOutline";s:38:"/admin/structure/media/manage/%/fields";s:8:"numParts";i:6;}}',
    'number_parts' => '6',
  ))
  ->values(array(
    'name' => 'entity.media.revision',
    'path' => '/media/{media}/revisions/{media_revision}/view',
    'pattern_outline' => '/media/%/revisions/%/view',
    'fit' => '21',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:46:"/media/{media}/revisions/{media_revision}/view";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:11:"_controller";s:58:"Drupal\Core\Entity\Controller\EntityRevisionViewController";s:15:"_title_callback";s:65:"Drupal\Core\Entity\Controller\EntityRevisionViewController::title";}s:12:"requirements";a:1:{s:14:"_entity_access";s:28:"media_revision.view revision";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:2:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}s:14:"media_revision";a:2:{s:4:"type";s:21:"entity_revision:media";s:9:"converter";s:30:"paramconverter.entity_revision";}}s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:5:"media";i:1;s:14:"media_revision";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:73:"{^/media/(?P<media>[^/]++)/revisions/(?P<media_revision>[^/]++)/view$}sDu";s:11:"path_tokens";a:5:{i:0;a:2:{i:0;s:4:"text";i:1;s:5:"/view";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:14:"media_revision";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:10:"/revisions";}i:3;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:5:"media";i:4;b:1;}i:4;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:2:{i:0;s:5:"media";i:1;s:14:"media_revision";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:21;s:14:"patternOutline";s:25:"/media/%/revisions/%/view";s:8:"numParts";i:5;}}',
    'number_parts' => '5',
  ))
  ->values(array(
    'name' => 'entity.media.revision_delete_form',
    'path' => '/media/{media}/revision/{media_revision}/delete',
    'pattern_outline' => '/media/%/revision/%/delete',
    'fit' => '21',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:47:"/media/{media}/revision/{media_revision}/delete";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:21:"media.revision-delete";s:6:"_title";s:15:"Delete revision";}s:12:"requirements";a:1:{s:14:"_entity_access";s:30:"media_revision.delete revision";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:12:"_admin_route";b:1;s:10:"parameters";a:2:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}s:14:"media_revision";a:2:{s:4:"type";s:21:"entity_revision:media";s:9:"converter";s:30:"paramconverter.entity_revision";}}s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:5:"media";i:1;s:14:"media_revision";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:74:"{^/media/(?P<media>[^/]++)/revision/(?P<media_revision>[^/]++)/delete$}sDu";s:11:"path_tokens";a:5:{i:0;a:2:{i:0;s:4:"text";i:1;s:7:"/delete";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:14:"media_revision";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:9:"/revision";}i:3;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:5:"media";i:4;b:1;}i:4;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:2:{i:0;s:5:"media";i:1;s:14:"media_revision";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:21;s:14:"patternOutline";s:26:"/media/%/revision/%/delete";s:8:"numParts";i:5;}}',
    'number_parts' => '5',
  ))
  ->values(array(
    'name' => 'entity.media.revision_revert_form',
    'path' => '/media/{media}/revision/{media_revision}/revert',
    'pattern_outline' => '/media/%/revision/%/revert',
    'fit' => '21',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:47:"/media/{media}/revision/{media_revision}/revert";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:21:"media.revision-revert";s:6:"_title";s:15:"Revert revision";}s:12:"requirements";a:1:{s:14:"_entity_access";s:21:"media_revision.revert";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:12:"_admin_route";b:1;s:10:"parameters";a:2:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}s:14:"media_revision";a:2:{s:4:"type";s:21:"entity_revision:media";s:9:"converter";s:30:"paramconverter.entity_revision";}}s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:2:{i:0;s:5:"media";i:1;s:14:"media_revision";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:74:"{^/media/(?P<media>[^/]++)/revision/(?P<media_revision>[^/]++)/revert$}sDu";s:11:"path_tokens";a:5:{i:0;a:2:{i:0;s:4:"text";i:1;s:7:"/revert";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:14:"media_revision";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:9:"/revision";}i:3;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:5:"media";i:4;b:1;}i:4;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:2:{i:0;s:5:"media";i:1;s:14:"media_revision";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:21;s:14:"patternOutline";s:26:"/media/%/revision/%/revert";s:8:"numParts";i:5;}}',
    'number_parts' => '5',
  ))
  ->values(array(
    'name' => 'entity.media.version_history',
    'path' => '/media/{media}/revisions',
    'pattern_outline' => '/media/%/revisions',
    'fit' => '5',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:24:"/media/{media}/revisions";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:11:"_controller";s:54:"Drupal\Core\Entity\Controller\VersionHistoryController";s:6:"_title";s:9:"Revisions";}s:12:"requirements";a:1:{s:14:"_entity_access";s:24:"media.view all revisions";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:14:"entity_type_id";s:5:"media";s:12:"_admin_route";b:1;s:10:"parameters";a:1:{s:5:"media";a:2:{s:4:"type";s:12:"entity:media";s:9:"converter";s:21:"paramconverter.entity";}}s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:5:"media";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:41:"{^/media/(?P<media>[^/]++)/revisions$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:10:"/revisions";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:5:"media";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:1:{i:0;s:5:"media";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:5;s:14:"patternOutline";s:18:"/media/%/revisions";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media_type.add_form',
    'path' => '/admin/structure/media/add',
    'pattern_outline' => '/admin/structure/media/add',
    'fit' => '15',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:26:"/admin/structure/media/add";s:4:"host";s:0:"";s:8:"defaults";a:3:{s:12:"_entity_form";s:14:"media_type.add";s:14:"entity_type_id";s:10:"media_type";s:15:"_title_callback";s:56:"Drupal\Core\Entity\Controller\EntityController::addTitle";}s:12:"requirements";a:1:{s:21:"_entity_create_access";s:10:"media_type";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:26:"access_check.entity_create";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:33:"{^/admin/structure/media/add$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:26:"/admin/structure/media/add";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:15;s:14:"patternOutline";s:26:"/admin/structure/media/add";s:8:"numParts";i:4;}}',
    'number_parts' => '4',
  ))
  ->values(array(
    'name' => 'entity.media_type.collection',
    'path' => '/admin/structure/media',
    'pattern_outline' => '/admin/structure/media',
    'fit' => '7',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:22:"/admin/structure/media";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:12:"_entity_list";s:10:"media_type";s:6:"_title";s:11:"Media types";s:16:"_title_arguments";a:0:{}s:14:"_title_context";s:0:"";}s:12:"requirements";a:1:{s:11:"_permission";s:22:"administer media types";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:29:"{^/admin/structure/media$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:22:"/admin/structure/media";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:7;s:14:"patternOutline";s:22:"/admin/structure/media";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'entity.media_type.delete_form',
    'path' => '/admin/structure/media/manage/{media_type}/delete',
    'pattern_outline' => '/admin/structure/media/manage/%/delete',
    'fit' => '61',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:49:"/admin/structure/media/manage/{media_type}/delete";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:17:"media_type.delete";s:15:"_title_callback";s:60:"\Drupal\Core\Entity\Controller\EntityController::deleteTitle";}s:12:"requirements";a:1:{s:14:"_entity_access";s:17:"media_type.delete";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:66:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/delete$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:7:"/delete";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:61;s:14:"patternOutline";s:38:"/admin/structure/media/manage/%/delete";s:8:"numParts";i:6;}}',
    'number_parts' => '6',
  ))
  ->values(array(
    'name' => 'entity.media_type.edit_form',
    'path' => '/admin/structure/media/manage/{media_type}',
    'pattern_outline' => '/admin/structure/media/manage/%',
    'fit' => '30',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:42:"/admin/structure/media/manage/{media_type}";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:12:"_entity_form";s:15:"media_type.edit";s:15:"_title_callback";s:58:"\Drupal\Core\Entity\Controller\EntityController::editTitle";}s:12:"requirements";a:1:{s:14:"_entity_access";s:17:"media_type.update";}s:7:"options";a:5:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:19:"access_check.entity";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:59:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)$}sDu";s:11:"path_tokens";a:2:{i:0;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:1;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:30;s:14:"patternOutline";s:31:"/admin/structure/media/manage/%";s:8:"numParts";i:5;}}',
    'number_parts' => '5',
  ))
  ->values(array(
    'name' => 'entity.media_type.entity_permissions_form',
    'path' => '/admin/structure/media/manage/{media_type}/permissions',
    'pattern_outline' => '/admin/structure/media/manage/%/permissions',
    'fit' => '61',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:54:"/admin/structure/media/manage/{media_type}/permissions";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:6:"_title";s:18:"Manage permissions";s:5:"_form";s:38:"Drupal\user\Form\EntityPermissionsForm";s:14:"entity_type_id";s:5:"media";s:18:"bundle_entity_type";s:10:"media_type";}s:12:"requirements";a:1:{s:11:"_permission";s:22:"administer permissions";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:9:"_field_ui";b:1;s:10:"parameters";a:1:{s:10:"media_type";a:3:{s:4:"type";s:17:"entity:media_type";s:21:"with_config_overrides";b:1;s:9:"converter";s:21:"paramconverter.entity";}}s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:71:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/permissions$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:12:"/permissions";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:61;s:14:"patternOutline";s:43:"/admin/structure/media/manage/%/permissions";s:8:"numParts";i:6;}}',
    'number_parts' => '6',
  ))
  ->values(array(
    'name' => 'field_ui.field_add_media',
    'path' => '/admin/structure/media/manage/{media_type}/add-field/{entity_type}/{field_name}',
    'pattern_outline' => '/admin/structure/media/manage/%/add-field/%/%',
    'fit' => '244',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:79:"/admin/structure/media/manage/{media_type}/add-field/{entity_type}/{field_name}";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:11:"_controller";s:80:"Drupal\field_ui\Controller\FieldConfigAddController::fieldConfigAddConfigureForm";s:6:"_title";s:9:"Add field";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:11:"_permission";s:23:"administer media fields";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:3:{i:0;s:10:"media_type";i:1;s:11:"entity_type";i:2;s:10:"field_name";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:117:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/add\-field/(?P<entity_type>[^/]++)/(?P<field_name>[^/]++)$}sDu";s:11:"path_tokens";a:5:{i:0;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"field_name";i:4;b:1;}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:11:"entity_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:10:"/add-field";}i:3;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:4;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:3:{i:0;s:10:"media_type";i:1;s:11:"entity_type";i:2;s:10:"field_name";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:244;s:14:"patternOutline";s:45:"/admin/structure/media/manage/%/add-field/%/%";s:8:"numParts";i:8;}}',
    'number_parts' => '8',
  ))
  ->values(array(
    'name' => 'field_ui.field_storage_config_add_media',
    'path' => '/admin/structure/media/manage/{media_type}/fields/add-field',
    'pattern_outline' => '/admin/structure/media/manage/%/fields/add-field',
    'fit' => '123',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:59:"/admin/structure/media/manage/{media_type}/fields/add-field";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:5:"_form";s:41:"\Drupal\field_ui\Form\FieldStorageAddForm";s:6:"_title";s:9:"Add field";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:11:"_permission";s:23:"administer media fields";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:77:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/fields/add\-field$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:17:"/fields/add-field";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:123;s:14:"patternOutline";s:48:"/admin/structure/media/manage/%/fields/add-field";s:8:"numParts";i:7;}}',
    'number_parts' => '7',
  ))
  ->values(array(
    'name' => 'field_ui.field_storage_config_reuse_media',
    'path' => '/admin/structure/media/manage/{media_type}/fields/reuse',
    'pattern_outline' => '/admin/structure/media/manage/%/fields/reuse',
    'fit' => '123',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:55:"/admin/structure/media/manage/{media_type}/fields/reuse";s:4:"host";s:0:"";s:8:"defaults";a:4:{s:5:"_form";s:43:"\Drupal\field_ui\Form\FieldStorageReuseForm";s:6:"_title";s:24:"Re-use an existing field";s:14:"entity_type_id";s:5:"media";s:6:"bundle";s:0:"";}s:12:"requirements";a:1:{s:28:"_field_ui_field_reuse_access";s:23:"administer media fields";}s:7:"options";a:6:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:10:"parameters";a:1:{s:10:"media_type";a:2:{s:4:"type";s:17:"entity:media_type";s:9:"converter";s:63:"drupal.proxy_original_service.paramconverter.configentity_admin";}}s:9:"_field_ui";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:33:"access_check.field_ui.field_reuse";}s:4:"utf8";b:1;}s:7:"schemes";a:0:{}s:7:"methods";a:0:{}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:10:"media_type";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:72:"{^/admin/structure/media/manage/(?P<media_type>[^/]++)/fields/reuse$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:13:"/fields/reuse";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:10:"media_type";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:29:"/admin/structure/media/manage";}}s:9:"path_vars";a:1:{i:0;s:10:"media_type";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:123;s:14:"patternOutline";s:44:"/admin/structure/media/manage/%/fields/reuse";s:8:"numParts";i:7;}}',
    'number_parts' => '7',
  ))
  ->values(array(
    'name' => 'media.filter.preview',
    'path' => '/media/{filter_format}/preview',
    'pattern_outline' => '/media/%/preview',
    'fit' => '5',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:30:"/media/{filter_format}/preview";s:4:"host";s:0:"";s:8:"defaults";a:1:{s:11:"_controller";s:55:"\Drupal\media\Controller\MediaFilterController::preview";}s:12:"requirements";a:2:{s:14:"_entity_access";s:17:"filter_format.use";s:14:"_custom_access";s:74:"\Drupal\media\Controller\MediaFilterController::formatUsesMediaEmbedFilter";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:4:"utf8";b:1;s:10:"parameters";a:1:{s:13:"filter_format";a:2:{s:4:"type";s:20:"entity:filter_format";s:9:"converter";s:21:"paramconverter.entity";}}s:14:"_access_checks";a:2:{i:0;s:19:"access_check.entity";i:1;s:19:"access_check.custom";}}s:7:"schemes";a:0:{}s:7:"methods";a:1:{i:0;s:3:"GET";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:1:{i:0;s:13:"filter_format";}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:47:"{^/media/(?P<filter_format>[^/]++)/preview$}sDu";s:11:"path_tokens";a:3:{i:0;a:2:{i:0;s:4:"text";i:1;s:8:"/preview";}i:1;a:5:{i:0;s:8:"variable";i:1;s:1:"/";i:2;s:6:"[^/]++";i:3;s:13:"filter_format";i:4;b:1;}i:2;a:2:{i:0;s:4:"text";i:1;s:6:"/media";}}s:9:"path_vars";a:1:{i:0;s:13:"filter_format";}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:5;s:14:"patternOutline";s:16:"/media/%/preview";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->values(array(
    'name' => 'media.oembed_iframe',
    'path' => '/media/oembed',
    'pattern_outline' => '/media/oembed',
    'fit' => '3',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:13:"/media/oembed";s:4:"host";s:0:"";s:8:"defaults";a:1:{s:11:"_controller";s:55:"\Drupal\media\Controller\OEmbedIframeController::render";}s:12:"requirements";a:1:{s:11:"_permission";s:10:"view media";}s:7:"options";a:3:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:4:"utf8";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:20:"{^/media/oembed$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:13:"/media/oembed";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:3;s:14:"patternOutline";s:13:"/media/oembed";s:8:"numParts";i:2;}}',
    'number_parts' => '2',
  ))
  ->values(array(
    'name' => 'media.settings',
    'path' => '/admin/config/media/media-settings',
    'pattern_outline' => '/admin/config/media/media-settings',
    'fit' => '15',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:34:"/admin/config/media/media-settings";s:4:"host";s:0:"";s:8:"defaults";a:2:{s:5:"_form";s:36:"\Drupal\media\Form\MediaSettingsForm";s:6:"_title";s:14:"Media settings";}s:12:"requirements";a:1:{s:11:"_permission";s:16:"administer media";}s:7:"options";a:4:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:4:"utf8";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:42:"{^/admin/config/media/media\-settings$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:34:"/admin/config/media/media-settings";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:15;s:14:"patternOutline";s:34:"/admin/config/media/media-settings";s:8:"numParts";i:4;}}',
    'number_parts' => '4',
  ))
  ->values(array(
    'name' => 'view.media.media_page_list',
    'path' => '/admin/content/media',
    'pattern_outline' => '/admin/content/media',
    'fit' => '7',
    'route' => 'O:31:"Symfony\Component\Routing\Route":9:{s:4:"path";s:20:"/admin/content/media";s:4:"host";s:0:"";s:8:"defaults";a:5:{s:11:"_controller";s:47:"Drupal\views\Routing\ViewPageController::handle";s:15:"_title_callback";s:49:"Drupal\views\Routing\ViewPageController::getTitle";s:7:"view_id";s:5:"media";s:10:"display_id";s:15:"media_page_list";s:30:"_view_display_show_admin_links";b:1;}s:12:"requirements";a:2:{s:11:"_permission";s:21:"access media overview";s:7:"_format";s:4:"html";}s:7:"options";a:9:{s:14:"compiler_class";s:33:"Drupal\Core\Routing\RouteCompiler";s:18:"_view_argument_map";a:0:{}s:23:"_view_display_plugin_id";s:4:"page";s:26:"_view_display_plugin_class";s:38:"Drupal\views\Plugin\views\display\Page";s:30:"_view_display_show_admin_links";b:1;s:16:"returns_response";b:0;s:4:"utf8";b:1;s:12:"_admin_route";b:1;s:14:"_access_checks";a:1:{i:0;s:23:"access_check.permission";}}s:7:"schemes";a:0:{}s:7:"methods";a:2:{i:0;s:3:"GET";i:1;s:4:"POST";}s:9:"condition";s:0:"";s:8:"compiled";O:33:"Drupal\Core\Routing\CompiledRoute":11:{s:4:"vars";a:0:{}s:11:"path_prefix";s:0:"";s:10:"path_regex";s:27:"{^/admin/content/media$}sDu";s:11:"path_tokens";a:1:{i:0;a:2:{i:0;s:4:"text";i:1;s:20:"/admin/content/media";}}s:9:"path_vars";a:0:{}s:10:"host_regex";N;s:11:"host_tokens";a:0:{}s:9:"host_vars";a:0:{}s:3:"fit";i:7;s:14:"patternOutline";s:20:"/admin/content/media";s:8:"numParts";i:3;}}',
    'number_parts' => '3',
  ))
  ->execute();
