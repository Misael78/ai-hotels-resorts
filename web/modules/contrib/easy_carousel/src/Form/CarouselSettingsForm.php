<?php

declare(strict_types=1);

namespace Drupal\easy_carousel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for a carousel entity type.
 */
final class CarouselSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carousel_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['delete_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete all carousels'),
    ];

    $form['delete_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete all carousels slides also'),
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
      if ($form_state->getValue('delete_items')) {
        $data = \Drupal::entityTypeManager()->getStorage('carousel_item')->loadMultiple();
        foreach ($data as $entity) {
          $entity->delete();
        }
        $this->messenger()->addStatus('Deleted ' . count($data) . ' slides');
      }

      $data = \Drupal::entityTypeManager()->getStorage('carousel')->loadMultiple();
      foreach ($data as $entity) {
        $entity->delete();
      }
      $this->messenger()->addStatus('Deleted ' . count($data) . ' carousels');
    }
  }

}
