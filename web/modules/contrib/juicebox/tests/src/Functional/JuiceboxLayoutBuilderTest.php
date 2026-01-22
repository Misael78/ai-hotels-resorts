<?php

namespace Drupal\Tests\juicebox\Functional;

/**
 * Tests integration with Views module.
 *
 * @group juicebox
 */
class JuiceboxLayoutBuilderTest extends JuiceboxCaseTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'field_ui',
    'juicebox',
    'juicebox_test_layout_builder',
  ];

  public function testLayoutBuilderIntegration() {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
                         //admin/structure/types/manage/bundle_with_section_field/display/default
    $this->drupalGet('admin/structure/types/manage/juicebox_image_gallery/display/default/layout');
    $this->assertSession()->statusCodeEquals(200);
  }

}
