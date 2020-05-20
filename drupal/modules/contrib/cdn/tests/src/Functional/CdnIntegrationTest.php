<?php

namespace Drupal\Tests\cdn\Functional;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;

/**
 * @group cdn
 */
class CdnIntegrationTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'cdn', 'file', 'editor'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a text format that uses editor_file_reference, a node type with a
    // body field and image.
    $format = $this->randomMachineName();
    FilterFormat::create([
      'format' => $format,
      'name' => $this->randomString(),
      'weight' => 0,
      'filters' => [
        'editor_file_reference' => [
          'status' => 1,
          'weight' => 0,
        ],
      ],
    ])->save();
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    file_put_contents('public://druplicon ❤️.png', $this->randomMachineName());
    $image = File::create(['uri' => 'public://druplicon ❤️.png']);
    $image->save();
    $uuid = $image->uuid();

    // Create a node of the above node type using the above text format and
    // referencing the above image.
    $this->drupalCreateNode([
      'type' => 'article',
      'body' => [
        0 => [
          'value' => '<p>Do you also love Drupal?</p><img src="druplicon ❤️.png" data-caption="Druplicon" data-entity-type="file" data-entity-uuid="' . $uuid . '" />',
          'format' => $format,
        ],
      ],
    ]);

    // Configure CDN integration.
    $this->config('cdn.settings')
      ->set('mapping', ['type' => 'simple', 'domain' => 'cdn.example.com'])
      ->set('status', TRUE)
      // Disable the farfuture functionality: simpler file URL assertions.
      ->set('farfuture', ['status' => FALSE])
      ->save();

    // \Drupal\Tests\BrowserTestBase::installDrupal() overrides some of the
    // defaults for easier test debugging. But for a CDN integration test, we do
    // want the defaults to be applied, because that is what we want to test.
    $this->config('system.performance')
      ->set('css.preprocess', TRUE)
      ->set('js.preprocess', TRUE)
      ->save();
  }

  /**
   * Tests that CSS aggregates never use CDN URLs, and changes are immediate.
   *
   * @see \Drupal\cdn\Asset\CssOptimizer
   */
  public function testCss() {
    $session = $this->getSession();

    // Verify Page Cache is enabled.
    $this->drupalGet('<front>');
    $this->assertSame('MISS', $session->getResponseHeader('X-Drupal-Cache'));
    $this->drupalGet('<front>');
    $this->assertSame('HIT', $session->getResponseHeader('X-Drupal-Cache'));

    // CDN disabled.
    $this->config('cdn.settings')->set('status', FALSE)->save();
    $this->drupalGet('<front>');
    $this->assertSame('MISS', $session->getResponseHeader('X-Drupal-Cache'), 'Changing CDN settings causes Page Cache miss: setting changes have immediate effect.');
    $href = $this->cssSelect('link[rel=stylesheet]')[0]->getAttribute('href');
    $regexp = '#/' . $this->siteDirectory . '/files/css/css_[a-zA-Z0-9_-]{43}\.css#';
    $this->assertSame(1, preg_match($regexp, $href));
    $this->assertCssFileUsesRootRelativeUrl($this->baseUrl . $href);

    // CDN enabled, "Forever cacheable files" disabled.
    $this->config('cdn.settings')->set('status', TRUE)->save();
    $this->drupalGet('<front>');
    $this->assertSame('MISS', $session->getResponseHeader('X-Drupal-Cache'), 'Changing CDN settings causes Page Cache miss: setting changes have immediate effect.');
    $href = $this->cssSelect('link[rel=stylesheet]')[0]->getAttribute('href');
    $regexp = '#//cdn.example.com' . base_path() . $this->siteDirectory . '/files/css/css_[a-zA-Z0-9_-]{43}\.css#';
    $this->assertSame(1, preg_match($regexp, $href));
    $this->assertCssFileUsesRootRelativeUrl($this->baseUrl . str_replace('//cdn.example.com', '', $href));

    // CDN enabled, "Forever cacheable files" enabled.
    $this->config('cdn.settings')->set('farfuture.status', TRUE)->save();
    $this->drupalGet('<front>');
    $this->assertSame('MISS', $session->getResponseHeader('X-Drupal-Cache'), 'Changing CDN settings causes Page Cache miss: setting changes have immediate effect.');
    $href = $this->cssSelect('link[rel=stylesheet]')[0]->getAttribute('href');
    $regexp = '#//cdn.example.com' . base_path() . 'cdn/ff/[a-zA-Z0-9_-]{43}/[0-9]{10}/public/css/css_[a-zA-Z0-9_-]{43}\.css#';
    $this->assertSame(1, preg_match($regexp, $href));
    $this->assertCssFileUsesRootRelativeUrl($this->baseUrl . str_replace('//cdn.example.com', '', $href));
  }

  /**
   * Downloads the given CSS file and verifies its file URLs are root-relative.
   *
   * @param string $css_file_url
   *   The URL to a CSS file.
   */
  protected function assertCssFileUsesRootRelativeUrl($css_file_url) {
    $this->drupalGet($css_file_url);
    $this->assertSession()->responseContains('url(', 'CSS references other files.');
    $this->assertSession()->responseContains('url(' . base_path() . 'core/misc/tree.png)', 'CSS references other files by root-relative URL, not CDN URL.');
  }

  /**
   * Tests that CDN module never runs for update.php.
   */
  public function testUpdatePhp() {
    $session = $this->getSession();

    // Allow anonymous users to access update.php.
    $this->writeSettings([
      'settings' => [
        'update_free_access' => (object) [
          'value' => TRUE,
          'required' => TRUE,
        ],
      ],
    ]);

    $this->drupalGet('update.php');
    foreach ($session->getPage()->findAll('css', 'html > head > link[rel=stylesheet],link[rel="shortcut icon"]') as $node) {
      /* \Behat\Mink\Element\NodeElement $node */
      $this->assertStringStartsNotWith('//cdn.example.com', $node->getAttribute('href'));
    }
  }

  /**
   * Tests that uninstalling the CDN module causes CDN file URLs to disappear.
   */
  public function testUninstall() {
    $session = $this->getSession();

    $this->drupalGet('/node/1');
    $this->assertSame('MISS', $session->getResponseHeader('X-Drupal-Cache'));
    $this->assertSession()->responseContains('src="//cdn.example.com' . base_path() . $this->siteDirectory . '/files/' . UrlHelper::encodePath('druplicon ❤️.png') . '"');
    $this->drupalGet('/node/1');
    $this->assertSame('HIT', $session->getResponseHeader('X-Drupal-Cache'));

    \Drupal::service('module_installer')->uninstall(['cdn']);
    $this->assertTrue(TRUE, 'Uninstalled CDN module.');

    $this->drupalGet('/node/1');
    $this->assertSame('MISS', $session->getResponseHeader('X-Drupal-Cache'));
    $this->assertSession()->responseContains('src="' . base_path() . $this->siteDirectory . '/files/' . UrlHelper::encodePath('druplicon ❤️.png') . '"');
  }

  /**
   * Tests the legacy far future path.
   *
   * @group legacy
   * @todo Remove before CDN 4.0.
   */
  public function testOldFarfuture() {
    $druplicon_png_mtime = filemtime('public://druplicon ❤️.png');
    $druplicon_png_security_token = Crypt::hmacBase64($druplicon_png_mtime . '/' . $this->siteDirectory . '/files/' . UrlHelper::encodePath('druplicon ❤️.png'), \Drupal::service('private_key')->get() . Settings::getHashSalt());

    $this->drupalGet('/cdn/farfuture/' . $druplicon_png_security_token . '/' . $druplicon_png_mtime . '/' . $this->siteDirectory . '/files/druplicon ❤️.png');
    $this->assertSession()->statusCodeEquals(200);
    // Assert presence of headers that \Drupal\cdn\CdnFarfutureController sets.
    $this->assertSame('Wed, 20 Jan 1988 04:20:42 GMT', $this->getSession()->getResponseHeader('Last-Modified'));
    // Assert presence of headers that Symfony's BinaryFileResponse sets.
    $this->assertSame('bytes', $this->getSession()->getResponseHeader('Accept-Ranges'));

    // Any chance to the security token should cause a 403.
    $this->drupalGet('/cdn/farfuture/' . substr($druplicon_png_security_token, 1) . '/' . $druplicon_png_mtime . '/sites/default/files/druplicon ❤️.png');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the cdn.farfuture.download route/controller work as expected.
   */
  public function testFarfuture() {
    $druplicon_png_mtime = filemtime('public://druplicon ❤️.png');
    $druplicon_png_security_token = Crypt::hmacBase64($druplicon_png_mtime . 'public' . UrlHelper::encodePath('/druplicon ❤️.png'), \Drupal::service('private_key')->get() . Settings::getHashSalt());
    $druplicon_png_relative_security_token = Crypt::hmacBase64($druplicon_png_mtime . ':relative:' . UrlHelper::encodePath('/' . $this->siteDirectory . '/files/druplicon ❤️.png'), \Drupal::service('private_key')->get() . Settings::getHashSalt());
    $this->drupalGet('/cdn/ff/' . $druplicon_png_security_token . '/' . $druplicon_png_mtime . '/public/druplicon ❤️.png');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/cdn/ff/' . $druplicon_png_relative_security_token . '/' . $druplicon_png_mtime . '/:relative:/' . $this->siteDirectory . '/files/druplicon ❤️.png');
    $this->assertSession()->statusCodeEquals(200);
    // Assert presence of headers that \Drupal\cdn\CdnFarfutureController sets.
    $this->assertSame('Wed, 20 Jan 1988 04:20:42 GMT', $this->getSession()->getResponseHeader('Last-Modified'));
    // Assert presence of headers that Symfony's BinaryFileResponse sets.
    $this->assertSame('bytes', $this->getSession()->getResponseHeader('Accept-Ranges'));

    // Any chance to the security token should cause a 403.
    $this->drupalGet('/cdn/ff/' . substr($druplicon_png_security_token, 1) . '/' . $druplicon_png_mtime . '/public/druplicon ❤️.png');
    $this->assertSession()->statusCodeEquals(403);
  }

}
