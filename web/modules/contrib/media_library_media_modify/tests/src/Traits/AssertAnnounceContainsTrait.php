<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Traits;

trait AssertAnnounceContainsTrait {

  /**
   * Checks for inclusion of text in #drupal-live-announce.
   *
   * @param string $expected_message
   *   The text that is expected to be present in the #drupal-live-announce element.
   */
  protected function assertAnnounceContains(string $expected_message): void {
    $assert_session = $this->assertSession();
    $this->assertNotEmpty($assert_session->waitForElement('css', "#drupal-live-announce:contains('$expected_message')"));
  }

}
