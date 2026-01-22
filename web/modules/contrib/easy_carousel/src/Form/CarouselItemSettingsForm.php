<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for a carousel item entity type.
 */
final class CarouselItemSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carousel_item_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['delete_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete all carousel slides'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('delete_all')) {
      $data = \Drupal::entityTypeManager()->getStorage('carousel_item')->loadMultiple();
      foreach ($data as $entity) {
        $entity->delete();
      }
      $this->messenger()->addStatus('Deleted ' . count($data) . ' slides');
    }
  }

}
