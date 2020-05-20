<?php

namespace Drupal\cdn\EventSubscriber;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates cache tags & rebuilds container when necessary.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $drupalKernel;

  /**
   * The config installer.
   *
   * @var \Drupal\Core\Config\ConfigInstallerInterface
   */
  protected $configInstaller;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\DrupalKernelInterface $drupal_kernel
   *   The Drupal kernel.
   * @param \Drupal\Core\Config\ConfigInstallerInterface $config_installer
   *   The config installer.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator, TypedConfigManagerInterface $typed_config_manager, DrupalKernelInterface $drupal_kernel, ConfigInstallerInterface $config_installer) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->typedConfigManager = $typed_config_manager;
    $this->drupalKernel = $drupal_kernel;
    $this->configInstaller = $config_installer;
  }

  /**
   * Invalidates all render caches when CDN settings are modified.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event) {
    // Stream wrappers may be provided by contrib modules, e.g. Flysystem.
    // In the case of modules, there is no API to determine and dynamically add
    // the module dependency. If Drupal is installed from configuration, this
    // could result in CDN rejecting the saved config which references a stream
    // wrapper from a not-yet-installed module.
    if ($this->configInstaller->isSyncing()) {
      return;
    }

    if ($event->getConfig()->getName() === 'cdn.settings') {
      $this->cacheTagsInvalidator->invalidateTags([
        // Rendered output that is cached. (HTML containing URLs.)
        'rendered',
      ]);

      $this->validate($event->getConfig());

      // Rebuild the container whenever the 'status' configuration changes.
      // @see \Drupal\cdn\CdnServiceProvider
      if ($event->isChanged('status')) {
        $this->drupalKernel->invalidateContainer();
      }
    }
  }

  /**
   * Validates the given config.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The CDN settings configuration to validate.
   *
   * @throws \DomainException
   *   When invalid CDN settings were saved.
   */
  protected function validate(Config $config) {
    $typed_updated_config = $this->typedConfigManager->createFromNameAndData('cdn.settings', $config->getRawData());
    $violations = $typed_updated_config->validate();
    if ($violations->count() > 0) {
      $message = "Invalid CDN settings.\n";
      foreach ($violations as $violation) {
        $message .= $violation->getPropertyPath() . ': ' . PlainTextOutput::renderFromHtml($violation->getMessage()) . "\n";
      }
      throw new \DomainException($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
