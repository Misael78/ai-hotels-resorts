<?php

namespace Drupal\easy_carousel\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export easy carousel entities.
 */
class ExportForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an ExportForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easy_carousel_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export Carousels'),
      '#submit' => ['::exportJson'],
    ];
    return $form;
  }

  /**
   * Submit handler to generate and download JSON.
   */
  public function exportJson(array &$form, FormStateInterface $form_state) {
    $items = [];
    $entities = $this->entityTypeManager->getStorage('carousel_item')->loadMultiple();
    foreach ($entities as $entity) {
      $items[] = $entity->toArray();
    }

    $entities = $this->entityTypeManager->getStorage('carousel')->loadMultiple();
    $carousels = [];
    foreach ($entities as $entity) {
      $carousels[] = $entity->toArray();
    }

    $items_json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $carousels_json = json_encode($carousels, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Create a timestamp-based ZIP file name.
    $timestamp = date('dmY_Hi');
    $zip_file_name = "easy_carousel_export_$timestamp.zip";
    $zip_file_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_file_name;
    $zip = new \ZipArchive();
    $zip->open($zip_file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    // Add JSON files to the ZIP.
    $zip->addFromString('items.json', $items_json);
    $zip->addFromString('carousels.json', $carousels_json);

    // Close the ZIP file.
    $zip->close();

    // Prepare the response to download the ZIP.
    $response = new Response(file_get_contents($zip_file_path), 200, [
      'Content-Type' => 'application/zip',
      'Content-Disposition' => 'attachment; filename="' . $zip_file_name . '"',
    ]);

    // Clean up the temporary file.
    unlink($zip_file_path);

    // Send the response and stop further processing.
    $response->send();
    exit;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

}
