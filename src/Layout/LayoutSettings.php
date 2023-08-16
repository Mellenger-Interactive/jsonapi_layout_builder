<?php

namespace Drupal\jsonapi_layout_builder\Layout;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Layout\LayoutInterface;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a default class for Layout plugins.
 */
class LayoutSettings extends LayoutDefault implements LayoutInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManager
   */
  public $layoutPluginManager;

  /**
   * The uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutPluginManager $layout_plugin_manager, UuidInterface $uuid) {
    $this->uuid = $uuid;
    $this->layoutPluginManager = $layout_plugin_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.core.layout'),
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $definition = $this->getPluginDefinition();
    $original_class = $definition->get('base_class');
    if (!empty($original_class)) {
      $original_layout_plugin = $this->createOriginalInstance($original_class);
      if ($original_layout_plugin instanceof PluginFormInterface) {
        $configuration = $original_layout_plugin->defaultConfiguration();
      }
    }

    $configuration['section_uuid'] = $this->uuid->generate();

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $definition = $this->getPluginDefinition();
    $original_class = $definition->get('base_class');
    if (!empty($original_class)) {
      $original_layout_plugin = $this->createOriginalInstance($original_class);
      if ($original_layout_plugin instanceof PluginFormInterface) {
        $form += $original_layout_plugin->buildConfigurationForm($form, $form_state);
      }
    }
    return $form;
  }

  public function createOriginalInstance($plugin_class) {
    $plugin_definition = $this->pluginDefinition;

    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      return $plugin_class::create(\Drupal::getContainer(), $this->configuration, $this->pluginId, $this->pluginDefinition);
    }

    // Otherwise, create the plugin directly.
    return new $plugin_class($this->configuration, $this->pluginId, $plugin_definition);
  }

  /**
   * @inheritdoc
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $definition = $this->getPluginDefinition();
    $original_class = $definition->get('base_class');
    if (!empty($original_class)) {
      $original_layout_plugin = $this->createOriginalInstance($original_class);
      if ($original_layout_plugin instanceof PluginFormInterface) {
        $original_layout_plugin->validateConfigurationForm($form, $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $definition = $this->getPluginDefinition();
    $original_class = $definition->get('base_class');
    if (!empty($original_class)) {
      $original_layout_plugin = $this->createOriginalInstance($original_class);
      if ($original_layout_plugin instanceof PluginFormInterface) {
        $original_layout_plugin->submitConfigurationForm($form, $form_state);
        $this->configuration = $original_layout_plugin->configuration;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $build = parent::build($regions);
    $definition = $this->getPluginDefinition();
    $original_class = $definition->get('base_class');
    if (!empty($original_class)) {
      $original_layout_plugin = $this->createOriginalInstance($original_class);
      if ($original_layout_plugin instanceof PluginFormInterface) {
        $build = $original_layout_plugin->build($regions);
      }
    }
    return $build;
  }

}
