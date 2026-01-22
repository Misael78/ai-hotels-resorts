<?php

/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

// Primary records for Registration data.
$connection->schema()->createTable('registration', [
  'fields' => [
    'registration_id' => [
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ],
    'type' => [
      'type' => 'varchar',
      'length' => 32,
      'not null' => TRUE,
      'default' => '',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => 0,
      'unsigned' => TRUE,
    ],
    'entity_type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'anon_mail' => [
      'type' => 'varchar',
      'length' => 254,
      'not null' => FALSE,
    ],
    'count' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
      'unsigned' => TRUE,
    ],
    'user_uid' => [
      'type' => 'int',
      'not null' => FALSE,
      'default' => 0,
      'unsigned' => TRUE,
    ],
    'author_uid' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'unsigned' => TRUE,
    ],
    'state' => [
      'type' => 'varchar',
      'length' => 32,
      'not null' => TRUE,
      'default' => '',
    ],
    'created' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'unsigned' => TRUE,
    ],
    'updated' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'unsigned' => TRUE,
    ],
  ],
  'primary key' => [
    'registration_id',
  ],
  'indexes' => [
    'registration_updated' => [
      'updated',
    ],
    'registration_created' => [
      'created',
    ],
    'registration_type' => [
      ['type', 4],
    ],
    'registration_state' => [
      'state',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('registration')
  ->fields([
    'registration_id',
    'type',
    'entity_id',
    'entity_type',
    'anon_mail',
    'count',
    'author_uid',
    'state',
    'created',
    'updated',
  ])
  ->values([
    'registration_id' => 777,
    'type' => 'tradeshow',
    'entity_id' => 998,
    'entity_type' => 'node',
    'anon_mail' => 'test@example.org',
    'count' => 1,
    'author_uid' => 1,
    'state' => 'pending',
    'created' => '1715360510',
    'updated' => '1715382038',
  ])
  ->execute();

$connection->schema()->createTable('registration_entity', [
  'fields' => [
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
    ],
    'entity_type' => [
      'type' => 'varchar',
      'length' => 32,
      'not null' => TRUE,
      'default' => '',
    ],
    'capacity' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 0,
    ],
    'status' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 1,
    ],
    'send_reminder' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
    ],
    'reminder_date' => [
      'type' => 'datetime',
      'mysql_type' => 'datetime',
      'pgsql_type' => 'timestamp',
      'sqlite_type' => 'varchar',
      'sqlsrv_type' => 'smalldatetime',
      'not null' => FALSE,
    ],
    'reminder_template' => [
      'type' => 'text',
      'size' => 'big',
      'not null' => FALSE,
    ],
    'open' => [
      'type' => 'datetime',
      'mysql_type' => 'datetime',
      'pgsql_type' => 'timestamp',
      'sqlite_type' => 'varchar',
      'sqlsrv_type' => 'smalldatetime',
      'not null' => FALSE,
    ],
    'close' => [
      'type' => 'datetime',
      'mysql_type' => 'datetime',
      'pgsql_type' => 'timestamp',
      'sqlite_type' => 'varchar',
      'sqlsrv_type' => 'smalldatetime',
      'not null' => FALSE,
    ],
    'settings' => [
      'type' => 'blob',
      'not null' => TRUE,
      'size' => 'big',
      'serialize' => TRUE,
    ],
  ],
  'primary key' => ['entity_id', 'entity_type'],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('registration_entity')
  ->fields([
    'entity_id',
    'entity_type',
    'capacity',
    'status',
    'send_reminder',
    'open',
    'close',
    'settings',
  ])
  ->values([
    'entity_id' => 998,
    'entity_type' => 'node',
    'capacity' => 30,
    'status' => 1,
    'send_reminder' => 0,
    'open' => '2024-01-01 20:00:00',
    'close' => '2084-12-31 20:00:00',
    'settings' => serialize([
      'maximum_spaces' => '1',
      'multiple_registrations' => 0,
      'from_address' => 'mail@example.org',
      'confirmation' => 'Your registration has been saved.',
      'confirmation_redirect' => '',
      'hide_from_display' => 0,
    ]),
  ])
  ->execute();

$connection->schema()->createTable('registration_type', [
  'fields' => [
    'id' => [
      'type' => 'serial',
      'not null' => TRUE,
    ],
    'name' => [
      'type' => 'varchar',
      'length' => 32,
      'not null' => TRUE,
    ],
    'label' => [
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
      'default' => '',
    ],
    'weight' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'size' => 'tiny',
    ],
    'locked' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'size' => 'tiny',
    ],
    'default_state' => [
      'type' => 'varchar',
      'length' => 128,
      'not null' => FALSE,
    ],
    'data' => [
      'type' => 'text',
      'not null' => FALSE,
      'size' => 'big',
      'serialize' => TRUE,
      'merge' => TRUE,
    ],
    'status' => [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0x01,
      'size' => 'tiny',
    ],
    'module' => [
      'type' => 'varchar',
      'length' => 255,
      'not null' => FALSE,
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'name' => ['name'],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('registration_type')
  ->fields([
    'name',
    'label',
    'status',
  ])
  ->values([
    'name' => 'tradeshow',
    'label' => 'Trade show',
    'status' => 1,
  ])
  ->execute();

$connection->insert('node')
  ->fields([
    'nid',
    'vid',
    'type',
    'language',
    'title',
    'uid',
    'status',
    'created',
    'changed',
    'comment',
    'promote',
    'sticky',
    'tnid',
    'translate',
  ])
  ->values([
    'nid' => '998',
    'vid' => '999',
    'type' => 'test_content_type',
    'language' => 'en',
    'title' => 'An Edited Node',
    'uid' => '2',
    'status' => '1',
    'created' => '1421727515',
    'changed' => '1441032132',
    'comment' => '2',
    'promote' => '1',
    'sticky' => '0',
    'tnid' => '0',
    'translate' => '0',
  ])
  ->execute();

$connection->insert('node_revision')
  ->fields([
    'nid',
    'vid',
    'uid',
    'title',
    'log',
    'timestamp',
    'status',
    'comment',
    'promote',
    'sticky',
  ])
  ->values([
    'nid' => '998',
    'vid' => '998',
    'uid' => '1',
    'title' => 'A Node',
    'log' => '',
    'timestamp' => '1441032131',
    'status' => '1',
    'comment' => '2',
    'promote' => '1',
    'sticky' => '0',
  ])
  ->values([
    'nid' => '998',
    'vid' => '999',
    'uid' => '1',
    'title' => 'An Edited Node',
    'log' => '',
    'timestamp' => '1441032132',
    'status' => '1',
    'comment' => '2',
    'promote' => '1',
    'sticky' => '0',
  ])
  ->execute();

$connection->insert('field_config')
  ->fields([
    'id',
    'field_name',
    'type',
    'module',
    'active',
    'storage_type',
    'storage_module',
    'storage_active',
    'locked',
    'data',
    'cardinality',
    'translatable',
    'deleted',
  ])
  ->values([
    'id' => '1000',
    'field_name' => 'field_registration',
    'type' => 'registration',
    'module' => 'registration',
    'active' => '1',
    'storage_type' => 'field_sql_storage',
    'storage_module' => 'field_sql_storage',
    'storage_active' => '1',
    'locked' => '0',
    'data' => 'a:7:{s:12:"translatable";s:1:"0";s:12:"entity_types";a:0:{}s:8:"settings";a:0:{}s:7:"storage";a:5:{s:4:"type";s:17:"field_sql_storage";s:8:"settings";a:0:{}s:6:"module";s:17:"field_sql_storage";s:6:"active";s:1:"1";s:7:"details";a:1:{s:3:"sql";a:2:{s:18:"FIELD_LOAD_CURRENT";a:1:{s:29:"field_data_field_registration";a:1:{s:17:"registration_type";s:36:"field_registration_registration_type";}}s:19:"FIELD_LOAD_REVISION";a:1:{s:33:"field_revision_field_registration";a:1:{s:17:"registration_type";s:36:"field_registration_registration_type";}}}}}s:12:"foreign keys";a:1:{s:17:"registration_type";a:2:{s:5:"table";s:17:"registration_type";s:7:"columns";a:1:{s:17:"registration_type";s:4:"name";}}}s:7:"indexes";a:1:{s:17:"registration_type";a:1:{i:0;s:17:"registration_type";}}s:2:"id";s:2:"31";}',
    'cardinality' => '1',
    'translatable' => '0',
    'deleted' => '0',
  ])
  ->execute();

$connection->insert('field_config_instance')
  ->fields([
    'id',
    'field_id',
    'field_name',
    'entity_type',
    'bundle',
    'data',
    'deleted',
  ])
  ->values([
    'id' => '1000',
    'field_id' => '1000',
    'field_name' => 'field_registration',
    'entity_type' => 'node',
    'bundle' => 'test_content_type',
    'data' => 'a:7:{s:5:"label";s:12:"Registration";s:6:"widget";a:5:{s:6:"weight";s:1:"6";s:4:"type";s:19:"registration_select";s:6:"module";s:12:"registration";s:6:"active";i:0;s:8:"settings";a:0:{}}s:8:"settings";a:2:{s:29:"default_registration_settings";a:5:{s:6:"status";i:1;s:8:"capacity";s:3:"100";s:10:"scheduling";a:2:{s:4:"open";N;s:5:"close";N;}s:8:"reminder";a:2:{s:13:"send_reminder";i:0;s:17:"reminder_settings";a:2:{s:13:"reminder_date";N;s:17:"reminder_template";s:0:"";}}s:8:"settings";a:3:{s:14:"maximum_spaces";s:1:"1";s:22:"multiple_registrations";i:0;s:12:"from_address";s:16:"test@example.org";}}s:18:"user_register_form";b:0;}s:7:"display";a:2:{s:7:"default";a:5:{s:5:"label";s:5:"above";s:4:"type";s:17:"registration_link";s:6:"weight";s:2:"14";s:8:"settings";a:2:{s:5:"label";N;s:15:"i18n_string_key";N;}s:6:"module";s:12:"registration";}s:9:"line_item";a:4:{s:5:"label";s:5:"above";s:4:"type";s:6:"hidden";s:6:"weight";s:2:"10";s:8:"settings";a:0:{}}}s:8:"required";i:1;s:11:"description";s:0:"";s:13:"default_value";a:1:{i:0;a:1:{s:17:"registration_type";s:9:"tradeshow";}}}',
    'deleted' => '0',
  ])
  ->execute();

$connection->schema()->createTable('field_data_field_registration', [
  'fields' => [
    'entity_type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'bundle' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'language' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_registration_registration_type' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '32',
    ],
  ],
  'primary key' => [
    'entity_type',
    'entity_id',
    'deleted',
    'delta',
    'language',
  ],
  'indexes' => [
    'entity_type' => [
      'entity_type',
    ],
    'bundle' => [
      'bundle',
    ],
    'deleted' => [
      'deleted',
    ],
    'entity_id' => [
      'entity_id',
    ],
    'revision_id' => [
      'revision_id',
    ],
    'language' => [
      'language',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('field_data_field_registration')
  ->fields([
    'entity_type',
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'language',
    'delta',
    'field_registration_registration_type',
  ])
  ->values([
    'entity_type' => 'node',
    'bundle' => 'test_content_type',
    'deleted' => '0',
    'entity_id' => '998',
    'revision_id' => '999',
    'language' => 'en',
    'delta' => '0',
    'field_registration_registration_type' => 'tradeshow',
  ])
  ->execute();

$connection->schema()->createTable('field_revision_field_registration', [
  'fields' => [
    'entity_type' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'bundle' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ],
    'deleted' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'tiny',
      'default' => '0',
    ],
    'entity_id' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'revision_id' => [
      'type' => 'int',
      'not null' => FALSE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'language' => [
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '32',
      'default' => '',
    ],
    'delta' => [
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'unsigned' => TRUE,
    ],
    'field_registration_registration_type' => [
      'type' => 'varchar',
      'not null' => FALSE,
      'length' => '32',
    ],
  ],
  'primary key' => [
    'entity_type',
    'entity_id',
    'deleted',
    'delta',
    'language',
  ],
  'indexes' => [
    'entity_type' => [
      'entity_type',
    ],
    'bundle' => [
      'bundle',
    ],
    'deleted' => [
      'deleted',
    ],
    'entity_id' => [
      'entity_id',
    ],
    'revision_id' => [
      'revision_id',
    ],
    'language' => [
      'language',
    ],
  ],
  'mysql_character_set' => 'utf8',
]);

$connection->insert('field_revision_field_registration')
  ->fields([
    'entity_type',
    'bundle',
    'deleted',
    'entity_id',
    'revision_id',
    'language',
    'delta',
    'field_registration_registration_type',
  ])
  ->values([
    'entity_type' => 'node',
    'bundle' => 'test_content_type',
    'deleted' => '0',
    'entity_id' => '998',
    'revision_id' => '999',
    'language' => 'en',
    'delta' => '0',
    'field_registration_registration_type' => 'tradeshow',
  ])
  ->execute();

$connection->insert('system')
  ->fields([
    'filename',
    'name',
    'type',
    'owner',
    'status',
    'bootstrap',
    'schema_version',
    'weight',
    'info',
  ])
  ->values([
    'filename' => 'sites/all/modules/contrib/registration/registration.module',
    'name' => 'registration',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7108',
    'weight' => '0',
    'info' => 'a:15:{s:4:"name";s:12:"Registration";s:11:"description";s:33:"Entity based registration system.";s:7:"package";s:12:"Registration";s:4:"core";s:3:"7.x";s:9:"configure";s:28:"admin/structure/registration";s:12:"dependencies";a:1:{i:0;s:6:"entity";}s:5:"files";a:9:{i:0;s:23:"tests/registration.test";i:1;s:27:"lib/registration.entity.inc";i:2;s:29:"lib/registration.metadata.inc";i:3;s:37:"lib/registration_state.controller.inc";i:4;s:33:"lib/registration_state.entity.inc";i:5;s:40:"lib/registration_state.ui_controller.inc";i:6;s:36:"lib/registration_type.controller.inc";i:7;s:32:"lib/registration_type.entity.inc";i:8;s:39:"lib/registration_type.ui_controller.inc";}s:7:"version";s:7:"7.x-1.7";s:7:"project";s:12:"registration";s:9:"datestamp";s:10:"1550009596";s:5:"mtime";i:1593799183;s:3:"php";s:5:"5.2.4";s:9:"bootstrap";i:0;s:8:"required";b:1;s:11:"explanation";s:73:"Field type(s) in use - see <a href="/admin/reports/fields">Field list</a>";}',
  ])
  ->execute();
