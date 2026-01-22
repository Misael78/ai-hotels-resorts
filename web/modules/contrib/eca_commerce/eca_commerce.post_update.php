<?php

/**
 * @file
 * Post update functions for ECA Commerce.
 */

/**
 * Implements hook_removed_post_updates().
 */
function eca_commerce_removed_post_updates(): array {
  return [
    'eca_commerce_post_update_rename_tokens_2_0_0' => '2.1.0',
  ];
}
