<?php

namespace Drupal\ai_audio_field\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\PluginBaseClasses\ExternalBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\Traits\FileHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for an ai_audio_file field.
 */
#[AiAutomatorType(
  id: 'ai_audio_file_merge',
  label: new TranslatableMarkup('AI Audio Field: Merge Audio Files'),
  field_rule: 'file',
  target: 'file',
)]
class AiAudioFieldMerge extends ExternalBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  use FileHelperTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'AI Audio Field: Merge Audio Files';

  /**
   * Construct a merge audio files.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File system interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    // Checks system for ffmpeg, otherwise this rule does not exist.
    $command = (PHP_OS == 'WINNT') ? 'where ffmpeg' : 'which ffmpeg';
    $result = shell_exec($command);
    return $result ? TRUE : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'ai_audio_file',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    $form['automator_file_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Name'),
      '#description' => $this->t('The name of the file to be created. Will be audio.mp3 if left blank.'),
      '#default_value' => $defaultValues['automator_file_name'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Get all the files of the source value.
    $input = '';
    foreach ($entity->{$automatorConfig['base_field']} as $entityWrapper) {
      $uri = $entityWrapper->entity->getFileUri();
      // Get absolute path on server.
      $input .= "file '" . $this->fileSystem->realpath($uri) . "'\n";
    }
    // Create a temporary list file for this.
    $list_file = tempnam($this->fileSystem->getTempDirectory(), 'audio_merge_list');
    $tmp_file = $this->fileSystem->getTempDirectory() . '/' . md5(uniqid()) . '.mp3';
    // Store the list file.
    file_put_contents($list_file, $input);
    // Create the command.
    $command = "-y -f concat -safe 0 -i {input_list} -c copy {tmp_file}";
    $tokens = [
      'input_list' => $list_file,
      'tmp_file' => $tmp_file,
    ];
    $this->runFfmpegCommand($command, $tokens, 'Could not merge audio files.');
    return [
      'file' => $tmp_file,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $fileName = $automatorConfig['file_name'] ?? 'audio.mp3';
    $path = $this->getFileHelper()->createFilePathFromFieldConfig($fileName, $fieldDefinition, $entity);
    // Copy the file to the correct location.
    $path = $this->fileSystem->copy($values['file'], $path, FileExists::Rename);
    // Create the file entity.
    $file = $this->entityTypeManager->getStorage('file')->create([
      'uri' => $path,
      'status' => 1,
    ]);
    $file->save();
    // Values.
    $values = [
      'target_id' => $file->id(),
      'display' => 1,
    ];
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

  /**
   * Run FFMPEG command.
   *
   * @param string $command
   *   The command to run.
   * @param array $tokens
   *   The tokens to replace.
   * @param string $error_message
   *   Error message to throw if it fails.
   */
  public function runFfmpegCommand($command, array $tokens, $error_message) {
    $command = $this->prepareFfmpegCommand($command, $tokens);
    exec($command, $status);
    if ($status) {
      throw new AiAutomatorResponseErrorException($error_message);
    }
  }

  /**
   * Prepare the FFMPEG command.
   *
   * @param string $command
   *   The command to run.
   * @param array $tokens
   *   The tokens to replace.
   *
   * @return string
   *   The prepared command.
   */
  public function prepareFfmpegCommand($command, array $tokens) {
    foreach ($tokens as $token => $value) {
      // Only escape if it is not empty.
      if (empty($value)) {
        continue;
      }
      $escaped_value = escapeshellarg($value);
      $command = str_replace("{{$token}}", $escaped_value, $command);
    }
    // @todo Add full path to ffmpeg.
    return "ffmpeg $command";
  }

}
