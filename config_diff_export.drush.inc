<?php

use Drupal\config\StorageReplaceDataWrapper;
use Drush\Log\LogLevel;
use Drupal\Core\Config\StorageComparer;
use Drush\Config\StorageWrapper;
use Drush\Config\CoreExtensionFilter;
use Drupal\Core\Config\FileStorage;

function config_diff_export_drush_command() {
  $items = array();
  $items['config_diff_export'] = [
    'description' => 'Export diff of your configuration',
    'argument' => array(
      'folder' => 'Folder where export configuration ',
    ),
    'aliases' => ['cde'],
  ];
  return $items;
}

function drush_config_diff_export($folder = null) {

  // Option
  if($folder == NULL) {
    return drush_set_error(dt('The destination folder are mandatory.'));
  }
  if( !@dir($folder) ) {
    if(!mkdir($folder, 0775, TRUE)) {
      return drush_set_error(dt('The destination folder @folder not writable.', array('@folder' => $folder)));
    }
  }
  if(!is_writable($folder)) {
    return drush_set_error(dt('The destination folder @folder not writable.', array('@folder' => $folder)));
  }

  $source_storage = \Drupal::service('config.storage.sync');

  $active_storage = \Drupal::service('config.storage');
  $replacement_storage = new StorageReplaceDataWrapper($active_storage);
  foreach ($source_storage->listAll() as $name) {
    $data = $source_storage->read($name);
    $replacement_storage->replaceData($name, $data);
  }
  $source_storage = $replacement_storage;


  /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
  $config_manager = \Drupal::service('config.manager');
  $storage_comparer = new StorageComparer($source_storage, $active_storage, $config_manager);

  if (!$storage_comparer->createChangelist()->hasChanges()) {
    return drush_log(dt('There are no changes to export.'), LogLevel::OK);
  }

  $change_list = array();
  $out_list = array();
  foreach ($storage_comparer->getAllCollectionNames() as $collection) {
    foreach ($storage_comparer->getChangelist(NULL, $collection) as $config_change_type => $config_names) {
      if($config_change_type == 'update') {
        $change_list[$collection][$config_change_type] = $config_names;
        $out_list[] = $config_names;
      }
    }
  }
  _drush_print_config_changes_table($change_list);

  if (drush_confirm(dt('Export the listed configuration changes ?'))) {
    return drush_op('_run_export_diff', $out_list, $folder);
  }
}

function _run_export_diff($out_list, $folder) {

  $destination_storage = new FileStorage($folder);

  $source_storage = \Drupal::service('config.storage');

  foreach($out_list as $collection) {
    foreach($collection as $name) {
      $files[] = $name;
      $destination_storage->write($name, $source_storage->read($name));
    }
  }

  foreach (\Drupal::service('config.storage')->getAllCollectionNames() as $collection) {
    $source_storage = $source_storage->createCollection($collection);
    $destination_storage = $destination_storage->createCollection($collection);
    foreach ($source_storage->listAll() as $name) {
      if(in_array($name, $files)){
        $destination_storage->write($name, $source_storage->read($name));
      }
    }
  }
}
