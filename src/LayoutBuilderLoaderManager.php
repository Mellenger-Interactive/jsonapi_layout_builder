<?php

namespace Drupal\jsonapi_layout_builder;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LayoutBuilderLoaderManager {

  const VIEW_MODE_PARAM = 'rlb.view_mode.teaser';

  /**
   * @var \Symfony\Component\Routing\Route
   */
  protected $route;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * A request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * LayoutBuilderLoaderManager constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   */
  public function __construct(RouteMatchInterface $route_match, SectionStorageManagerInterface $section_storage_manager, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, RequestStack $request_stack) {
    $this->route = $route_match->getRouteObject();
    $this->sectionStorageManager = $section_storage_manager;
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->request = $request_stack->getCurrentRequest();
  }

  public function isJsonapiRequest() {
    if (!$this->route instanceof \Symfony\Component\Routing\Route || !$this->route->hasDefault('_is_jsonapi')) {
      return FALSE;
    }
    return TRUE;
  }

  public function loadMultiple(array $entities, $entity_type_id) {
    $view_mode = $this->route->getDefault('view_mode');
    foreach ($entities as $entity) {
      if (!$entity instanceof FieldableEntityInterface || !$entity->hasField(OverridesSectionStorage::FIELD_NAME) || !$entity->get(OverridesSectionStorage::FIELD_NAME)
          ->isEmpty()) {
        continue;
      }

      switch ($view_mode) {
        case 'full':
        case 'default':
          $this->loadDefaultViewMode($entity);
          break;
        default:
          $this->loadViewMode($entity, $view_mode);
          break;
      }
    }
  }

  protected function loadDefaultViewMode(ContentEntityBase $entity) {
    $type = 'overrides';
    $contexts['entity'] = EntityContext::fromEntity($entity);
    // @todo Expand to work for all view modes in
    //   https://www.drupal.org/node/2907413.
    $view_mode = 'default';
    // Retrieve the actual view mode from the returned view display as the
    // requested view mode may not exist and a fallback will be used.
    $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($entity, $view_mode)
      ->getMode();
    $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    $this->setSections($entity, $type, $contexts);
  }

  protected function loadViewMode(ContentEntityBase $entity, $view_mode) {
    $type = 'defaults';
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $defaults = [
      'entity_type_id' => $entity_type_id,
      'bundle_key' => 'node_type',
      'section_storage_type' => 'defaults',
      'section_storage' => '',
      '_entity_form' => 'entity_view_display.layout_builder',
      'bundle' => '',
      'node_type' => $bundle,
      'view_mode_name' => $view_mode,
      '_route' => 'layout_builder.defaults.node.view',
    ];
    $definition = [
      'layout_builder_tempstore' => TRUE,
      'converter' => 'layout_builder.param_converter',
    ];
    $contexts = $this->sectionStorageManager->loadEmpty($type)
      ->deriveContextsFromRoute('', $definition, 'section_storage', $defaults);
    $this->setSections($entity, $type, $contexts);
  }

  protected function setSections(ContentEntityBase $entity, $type, $contexts) {
    if ($section_storage = $this->sectionStorageManager->load($type, $contexts)) {
      $sections = $this->layoutTempstoreRepository->get($section_storage);
      $entity->get(OverridesSectionStorage::FIELD_NAME)
        ->setValue($sections->getSections());
    }
  }

}