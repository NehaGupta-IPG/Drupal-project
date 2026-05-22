<?php

namespace Drupal\user;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the user entity type.
 */
class UserViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    if ($this->connection->driver() == 'mongodb') {
      $data_table = 'users';
      $roles_table = 'users';
    }
    else {
      $data_table = 'users_field_data';
      $roles_table = 'user__roles';
    }

    $data[$data_table]['table']['base']['help'] = $this->t('Users who have created accounts on your site.');
    $data[$data_table]['table']['base']['access query tag'] = 'user_access';

    $data[$data_table]['table']['wizard_id'] = 'user';

    $data[$data_table]['uid']['argument']['id'] = 'user_uid';
    $data[$data_table]['uid']['argument'] += [
      'name table' => $data_table,
      'name field' => 'name',
      'empty field name' => \Drupal::config('user.settings')->get('anonymous'),
    ];
    $data[$data_table]['uid']['filter']['id'] = 'user_name';
    $data[$data_table]['uid']['filter']['title'] = $this->t('Name (autocomplete)');
    $data[$data_table]['uid']['filter']['help'] = $this->t('The user or author name. Uses an autocomplete widget to find a user name, the actual filter uses the resulting user ID.');
    $data[$data_table]['uid']['relationship'] = [
      'title' => $this->t('Content authored'),
      'help' => $this->t('Relate content to the user who created it. This relationship will create one record for each content item created by the user.'),
      'id' => 'standard',
      'base' => ($this->connection->driver() == 'mongodb' ? 'node' : 'node_field_data'),
      'base field' => 'uid',
      'field' => 'uid',
      'label' => $this->t('nodes'),
    ];

    $data[$data_table]['uid_raw'] = [
      'help' => $this->t('The raw numeric user ID.'),
      'real field' => 'uid',
      'filter' => [
        'title' => $this->t('The user ID'),
        'id' => 'numeric',
      ],
    ];

    $data[$data_table]['uid_representative'] = [
      'relationship' => [
        'title' => $this->t('Representative node'),
        'label'  => $this->t('Representative node'),
        'help' => $this->t('Obtains a single representative node for each user, according to a chosen sort criterion.'),
        'id' => 'groupwise_max',
        'relationship field' => 'uid',
        'outer field' => "$data_table.uid",
        'argument table' => $data_table,
        'argument field' => 'uid',
        'base' => ($this->connection->driver() == 'mongodb' ? 'node' : 'node_field_data'),
        'field' => 'nid',
        'relationship' => ($this->connection->driver() == 'mongodb' ? 'node:uid' : 'node_field_data:uid'),
      ],
    ];

    $data['users']['uid_current'] = [
      'real field' => 'uid',
      'title' => $this->t('Current'),
      'help' => $this->t('Filter the view to the currently logged in user.'),
      'filter' => [
        'id' => 'user_current',
        'type' => 'yes-no',
      ],
    ];

    $data[$data_table]['name']['help'] = $this->t('The user or author name.');
    $data[$data_table]['name']['field']['default_formatter'] = 'user_name';
    $data[$data_table]['name']['filter']['title'] = $this->t('Name (raw)');
    $data[$data_table]['name']['filter']['help'] = $this->t('The user or author name. This filter does not check if the user exists and allows partial matching. Does not use autocomplete.');

    // Note that this field implements field level access control.
    $data[$data_table]['mail']['help'] = $this->t('Email address for a given user. This field is normally not shown to users, so be cautious when using it.');

    $data[$data_table]['langcode']['help'] = $this->t('Language of the translation of user information');

    $data[$data_table]['preferred_langcode']['title'] = $this->t('Preferred language');
    $data[$data_table]['preferred_langcode']['help'] = $this->t('Preferred language of the user');
    $data[$data_table]['preferred_admin_langcode']['title'] = $this->t('Preferred admin language');
    $data[$data_table]['preferred_admin_langcode']['help'] = $this->t('Preferred administrative language of the user');

    $data[$data_table]['created_fulldate'] = [
      'title' => $this->t('Created date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_fulldate',
      ],
    ];

    $data[$data_table]['created_year_month'] = [
      'title' => $this->t('Created year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_year_month',
      ],
    ];

    $data[$data_table]['created_year'] = [
      'title' => $this->t('Created year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_year',
      ],
    ];

    $data[$data_table]['created_month'] = [
      'title' => $this->t('Created month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_month',
      ],
    ];

    $data[$data_table]['created_day'] = [
      'title' => $this->t('Created day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_day',
      ],
    ];

    $data[$data_table]['created_week'] = [
      'title' => $this->t('Created week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'created',
        'id' => 'date_week',
      ],
    ];

    $data[$data_table]['status']['filter']['label'] = $this->t('Active');
    $data[$data_table]['status']['filter']['type'] = 'yes-no';

    $data[$data_table]['changed']['title'] = $this->t('Updated date');

    $data[$data_table]['changed_fulldate'] = [
      'title' => $this->t('Updated date'),
      'help' => $this->t('Date in the form of CCYYMMDD.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_fulldate',
      ],
    ];

    $data[$data_table]['changed_year_month'] = [
      'title' => $this->t('Updated year + month'),
      'help' => $this->t('Date in the form of YYYYMM.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year_month',
      ],
    ];

    $data[$data_table]['changed_year'] = [
      'title' => $this->t('Updated year'),
      'help' => $this->t('Date in the form of YYYY.'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_year',
      ],
    ];

    $data[$data_table]['changed_month'] = [
      'title' => $this->t('Updated month'),
      'help' => $this->t('Date in the form of MM (01 - 12).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_month',
      ],
    ];

    $data[$data_table]['changed_day'] = [
      'title' => $this->t('Updated day'),
      'help' => $this->t('Date in the form of DD (01 - 31).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_day',
      ],
    ];

    $data[$data_table]['changed_week'] = [
      'title' => $this->t('Updated week'),
      'help' => $this->t('Date in the form of WW (01 - 53).'),
      'argument' => [
        'field' => 'changed',
        'id' => 'date_week',
      ],
    ];

    $data['users']['data'] = [
      'title' => $this->t('Data'),
      'help' => $this->t('Provides access to the user data service.'),
      'real field' => 'uid',
      'field' => [
        'id' => 'user_data',
      ],
    ];

    $data['users']['user_bulk_form'] = [
      'title' => $this->t('Bulk update'),
      'help' => $this->t('Add a form element that lets you run operations on multiple users.'),
      'field' => [
        'id' => 'user_bulk_form',
      ],
    ];

    // Not sure if this is needed anymore.
    if ($this->connection->driver() == 'mongodb') {
      $data[$roles_table]['roles_target_id']['title'] = $this->t('Roles');
      $data[$roles_table]['roles_target_id']['help'] = $this->t('Roles that a user belongs to.');
    }

    // Alter the user roles target_id column.
    $data[$roles_table]['roles_target_id']['field']['id'] = 'user_roles';
    $data[$roles_table]['roles_target_id']['field']['no group by'] = TRUE;

    $data[$roles_table]['roles_target_id']['filter']['id'] = 'user_roles';
    $data[$roles_table]['roles_target_id']['filter']['allow empty'] = TRUE;

    $data[$roles_table]['roles_target_id']['argument'] = [
      'id' => 'user__roles_rid',
      'name table' => 'role',
      'name field' => 'name',
      'empty field name' => $this->t('No role'),
      'zero is null' => TRUE,
      'numeric' => FALSE,
    ];

    $data[$roles_table]['permission'] = [
      'title' => $this->t('Permission'),
      'help' => $this->t('The user permissions.'),
      'field' => [
        'id' => 'user_permissions',
        'no group by' => TRUE,
      ],
      'filter' => [
        'id' => 'user_permissions',
        'real field' => 'roles_target_id',
      ],
    ];

    // Unset the "pass" field because the access control handler for the user
    // entity type allows editing the password, but not viewing it.
    unset($data[$data_table]['pass']);

    return $data;
  }

}
