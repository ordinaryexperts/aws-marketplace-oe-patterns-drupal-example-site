<?php

namespace Drupal\Tests\cdn\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests that existing sites also get the new "scheme" setting.
 *
 * @see cdn_update_8003()
 * @see https://www.drupal.org/project/cdn/issues/2925819
 *
 * @group cdn
 * @group legacy
 */
class CdnSchemeSettingsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-8.8.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/drupal-8.cdn-cdn_update_8001.php',
    ];
  }

  /**
   * Tests default settings can be detected, and are updated.
   *
   * It's possible to automatically update the settings as long as the only
   * thing that's modified by the end user is the 'domain' (NULL by default).
   */
  public function testStreamWrapperSettingsAdded() {
    // Make sure we have the expected values before the update.
    $cdn_settings = $this->config('cdn.settings');
    $this->assertNull($cdn_settings->get('scheme'));

    $this->runUpdates();

    // Make sure we have the expected values after the update.
    $cdn_settings = $this->config('cdn.settings');
    $this->assertSame('//', $cdn_settings->get('scheme'));
  }

}
