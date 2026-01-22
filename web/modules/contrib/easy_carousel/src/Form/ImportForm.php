<?php

namespace Drupal\easy_carousel\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import easy carousel entities.
 */
class ImportForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Filesystem service.
   *
   * @var FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructs an ExportForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $fileSystem) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easy_carousel_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['info'] = [
      '#type' => 'label',
      '#title' => $this->t('ATTENTION: Note that all existent carousels/slides will be deleted before the import operation.')
    ];

    // Add compatibility for Drupal 11
    $uploadValidators = version_compare(\Drupal::VERSION, '11.0.0', '>=')
      ? ['FileExtension' => 'zip']
      : ['file_validate_extensions' => ['zip']];

    // File chooser field.
    $form['upload'] = [
      '#type' => 'managed_file',
      '#required' => TRUE,
      '#title' => $this->t('Upload exported ZIP file'),
      '#upload_location' => 'temporary://',
      '#description' => $this->t('Upload a previously zipped file containing the carousel entities.'),
      '#upload_validators' => $uploadValidators
    ];

    // Process selected zip file
    $form['process'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process ZIP'),
      '#submit' => ['::submitProcess'],
    ];
    return $form;
  }

  /**
   * Submit handler for the process button.
   */
  public function submitProcess(array &$form, FormStateInterface $form_state) {
    $file_id = $form_state->getValue('upload');
    if ($file_id) {
      $file = $this->entityTypeManager->getStorage('file')->load($file_id[0]);
      if ($file) {
        $file_path = $file->getFileUri();
        $this->processZipFile($file_path);
      }
    } else {
      $this->messenger()->addError($this->t('No file was uploaded.'));
    }
  }


  /**
   * Processes the uploaded ZIP file and extracts JSON files.
   *
   * @param string $file_path
   *   The path to the uploaded ZIP file.
   */
  protected function processZipFile($file_path) {
    $temp_dir = sys_get_temp_dir();

    // Convert the schemed path to a real system path
    $real_path = $this->fileSystem->realpath($file_path);

    $zip = new \ZipArchive();
    if ($zip->open($real_path) === TRUE) {
      $zip->extractTo($temp_dir);
      $zip->close();

      $items = json_decode(file_get_contents($temp_dir . DIRECTORY_SEPARATOR . 'items.json'), TRUE);
      $carousels = json_decode(file_get_contents($temp_dir . DIRECTORY_SEPARATOR . 'carousels.json'), TRUE);

      // Delete existent entitities before import the new ones
      $this->deletePreviousEntities('carousel_item');
      $this->deletePreviousEntities('carousel');

      // Import
      $totalItems = $this->createNewEntities($items, 'carousel_item');
      $totalCarousels = $this->createNewEntities($carousels, 'carousel');

      $this->messenger()->addMessage($this->t('Carousels created: %carousels. Carousel items created: %items', [
        '%items' => $totalItems,
        '%carousels' => $totalCarousels
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to open the ZIP file.'));
    }
  }

  /**
   * Create the specified entities,
   *
   * @param array $entities
   *   Entity array.
   * @param string $entityType
   *   The entity type.
   *
   * @return int
   *   Total created entities.
   */
  protected function createNewEntities(array $entities, string $entityType): int {
    $total = 0;
    foreach ($entities as $entity) {
      $result = $this->entityTypeManager->getStorage($entityType)
        ->create($entity)
        ->save() === SAVED_NEW;
      if ($result) {
        $total++;
      }
    }
    return $total;
  }

  /**
   * Delete existent entities.
   *
   * @param string entity_type
   *   The entity type.
   *
   * @return void
   */
  protected function deletePreviousEntities(string $entity_type): void {
    $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple();
    foreach ($entities as $entity) {
      $entity->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

}
