<?php

namespace Drupal\hotel_ai\Service;

use Drupal\ai_provider_openai\OpenAiHelper;
use Drupal\node\NodeInterface;

/**
 * Generates AI content for Room nodes.
 */
class HotelAIGenerator {

  protected $openai;

  public function __construct(OpenAiHelper $openai) {
    $this->openai = $openai;
  }

  public function generateDraftForRoom(NodeInterface $node): bool {

    if ($node->bundle() !== 'room') {
      return FALSE;
    }

    if (!$node->hasField('field_body')) {
      return FALSE;
    }

    $body = $node->get('field_body')->value ?? '';
    if (trim($body) === '') {
      return FALSE;
    }

    $prompt = <<<PROMPT
You are a professional hotel marketing copywriter.

Based on the room description below, generate a JSON with:
- title (max 80 chars)
- summary (2 short sentences)
- features (5 bullet points, separated by line breaks)
- meta (SEO meta description under 155 chars)
- price (numeric, no currency sign)

Room description:
$body

Return ONLY JSON.
PROMPT;

    try {
      $response = $this->openai->request('chat.completions', [
        'model' => 'gpt-4.1-mini',
        'messages' => [
          ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.6,
      ]);

      $content = $response['choices'][0]['message']['content'] ?? '';

    } catch (\Throwable $e) {
      \Drupal::logger('hotel_ai')->error('OpenAI: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }

    $data = json_decode($content, TRUE);
    if (!is_array($data)) {
      \Drupal::logger('hotel_ai')->error('Invalid OpenAI JSON: @json', ['@json' => $content]);
      return FALSE;
    }

    $changed = FALSE;

    $fields = [
      'field_ai_title' => 'title',
      'field_ai_summary' => 'summary',
      'field_ai_features' => 'features',
      'field_ai_meta_desc' => 'meta',
      'field_ai_price_ai' => 'price',
    ];

    foreach ($fields as $field => $key) {
      if ($node->hasField($field) && $node->get($field)->isEmpty() && !empty($data[$key])) {
        $node->set($field, $data[$key]);
        $changed = TRUE;
      }
    }

    return $changed;
  }
}

