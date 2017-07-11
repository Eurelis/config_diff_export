<?php

namespace Drupal\config_diff_export\Controller;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Drupal\config\Controller\ConfigController;

/**
 * Returns responses for config module routes.
 */
class ConfigDiffExportController extends ConfigController implements ContainerInjectionInterface {

  /**
   * Downloads a tarball of the site diff configurations.
   */
  public function downloadDiff() {
    $dl_file_name = 'config_diff.tar.gz';
    $files_to_export = \Drupal::request()->query->get('files');

    file_unmanaged_delete(file_directory_temp() . '/' . $dl_file_name);

    $archiver = new ArchiveTar(file_directory_temp() . '/' . $dl_file_name, 'gz');

    // Get raw configuration data without overrides.
    foreach ($this->configManager->getConfigFactory()->listAll() as $name) {
      if(in_array($name, $files_to_export)) {
        $archiver->addString("$name.yml", Yaml::encode($this->configManager->getConfigFactory()->get($name)->getRawData()));
      }
    }

    // Get all override data from the remaining collections.
    foreach ($this->targetStorage->getAllCollectionNames() as $collection) {
      $collection_storage = $this->targetStorage->createCollection($collection);
      foreach ($collection_storage->listAll() as $name) {
        if(in_array($name, $files_to_export)) {
          $archiver->addString(str_replace('.', '/', $collection) . "/$name.yml", Yaml::encode($collection_storage->read($name)));
        }
      }
    }

    $request = new Request(['file' => $dl_file_name]);
    return $this->fileDownloadController->download($request, 'temporary');
  }
}
