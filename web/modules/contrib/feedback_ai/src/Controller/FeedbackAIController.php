<?php

namespace Drupal\feedback_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for the Feedback AI module.
 */
class FeedbackAIController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a FeedbackAIController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Returns a render array for the Feedback AI form.
   */
  public function content() {
    $build = $this->formBuilder->getForm('\Drupal\feedback_ai\Form\FeedbackAIForm');

    // Set the cache max-age to 0.
    $build['#cache'] = [
      'max-age' => 0,
    ];

    return $build;

  }

}
