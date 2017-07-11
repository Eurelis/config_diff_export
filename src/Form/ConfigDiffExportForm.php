<?php

namespace Drupal\config_diff_export\Form;

use Drupal\config\Form\ConfigSync;
use Drupal\Core\Form\FormStateInterface;

/**
 * Construct the storage changes in a configuration synchronization form.
 */
class ConfigDiffExportForm extends ConfigSync {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_diff_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Let parent building Form
    $form = parent::buildForm($form, $form_state);
    $export = FALSE;
    // Prepare new Titles
    $types_rename = [
      'update' => t('@count configuration changed'),
      'delete' => t('@count configuration localy new, not staged'),
      'create' => t('@count configuration localy not exportable'),
      'rename' => t('@count configuration rename'),
    ];

    // For Each group of modified configuration
    foreach(array_keys($form['']) as $type_name) {

      // We change title
      $count_configs = count($form[''][$type_name]['list']['#rows']);
      $form[''][$type_name]['heading']['#value'] = $this->formatPlural($count_configs, $types_rename[$type_name], $types_rename[$type_name]);


      // We don't care about staged new configurations here.
      // The plot are exported localy overided configuration.
      // We cannot export a "localy non existant" configuration.
      if($type_name == 'create') {
        $list = [];
        foreach ($form[''][$type_name]['list']['#rows'] as $row_key => $row) {
          $list[] = $row['name'];
        }

        $message = $this->t('The following configurations are not present in the database, only in staged configuration files which waiting to be imported');

        $form[''][$type_name]['list'] = ['#markup' => $message. ' :<br>' . implode(', ', $list)];
      }
      else {
        $export = TRUE;
        // Make the table "form_state valuable"
        $form[''][$type_name]['list']['#header'][] = $this->t('Export Me');
        foreach ($form[''][$type_name]['list']['#rows'] as $row_key => $row) {

          $row_item = &$form[''][$type_name]['list']['#rows'][$row_key];

          $name = $row['name'];
          $row_name = [
            '#markup' => $name,
          ];
          $row_item['name'] = ['data' => $row_name];
          $row_item[$name] = [
            '#type' => 'checkbox'
          ];

          $form[''][$type_name]['list'][] = $row_item;
        }
        unset($form[''][$type_name]['list']['#rows']);
      }

    }


    // Print submit button only if there are something to export.
    if($export === TRUE) {
      // Rename submit action
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Export Overrided configuration Files'),
      ];
    }
    else {
      unset($form['actions']['submit']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get all files marked to export
    $list = $form_state->getValue('list');
    $exportable_config_files = [];
    foreach($list as $data) {
      foreach($data as $config_file_name => $checked) {
        if($checked == 1) {
          $exportable_config_files[] = $config_file_name;
        }
      }
    }

    // If there is less than 1 files, we do nothing
    if(count($exportable_config_files) < 1) {
      return;
    }

    // Redirect to download controller
    $form_state->setRedirect('config_export_diff.export', ['files' => $exportable_config_files]);
  }


}
