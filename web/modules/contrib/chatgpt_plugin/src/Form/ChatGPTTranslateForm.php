<?php

namespace Drupal\chatgpt_plugin\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;

/**
 * Defines the class for ChatGPT Translate Form.
 */
class ChatGPTTranslateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chatgpt_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $build = NULL) {
    $form_state->set('entity', $build['#entity']);

    $overview = $build['content_translation_overview'];

    $form['#title'] = $this->t('Translations of @title', ['@title' => $build['#entity']->label()]);

    // Inject our additional column into the header.
    array_splice($overview['#header'], -1, 0, [$this->t('ChatGPT Translations')]);

    // Make this a tableselect form.
    $form['languages'] = [
      '#type' => 'tableselect',
      '#header' => $overview['#header'],
      '#options' => [],
    ];
    $languages = $this->languageManager->getLanguages();

    foreach ($languages as $langcode => $language) {
      $option = array_shift($overview['#rows']);
      $lang_name = $language->getName();
      $node_id = $form_state->get('entity')->id();
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
      $label = "Translate using ChatGPT";
      $row_title = Link::createFromRoute($label, 'chatgpt_plugin.translate_content', [
        'lang_code' => $langcode,
        'lang_name' => $lang_name,
        'node_id' => $node_id,
      ])->toString();

      if (!$node->hasTranslation($langcode)) {
        $additional = $row_title;
      }
      else {
        $additional = $this->t('NA');
      }

      // Inject the additional column into the array.
      // The generated form structure has changed, support both an additional
      // 'data' key (that is not supported by tableselect) and the old version
      // without.
      if (isset($option['data'])) {
        array_splice($option['data'], -1, 0, [$additional]);
        // Append the current option array to the form.
        $form['languages']['#options'][$langcode] = $option['data'];
      }
      else {
        array_splice($option, -1, 0, [$additional]);
        // Append the current option array to the form.
        $form['languages']['#options'][$langcode] = $option;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Function to call the Chat GPT API and get the result.
   */
  public function chatgptTranslateResult(array &$form, FormStateInterface $form_state) {

  }

}
