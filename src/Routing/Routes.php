<?php

namespace Drupal\jsonapi_layout_builder\Routing;

use Drupal\jsonapi\Access\RelationshipFieldAccess;
use Drupal\jsonapi\ParamConverter\ResourceTypeConverter;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Drupal\jsonapi\Routing\Routes as CoreRoutes;

class Routes extends CoreRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();
    $upload_routes = new RouteCollection();

    // JSON:API's routes: entry point + routes for every resource type.
    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      if (!$resource_type->isMutable()) {
        continue;
      }
      $routes->addCollection(static::getRoutesForResourceType($resource_type, $this->jsonApiBasePath));
    }
    // Require the JSON:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    $routes->addRequirements(['_content_type_format' => 'api_json']);
    $upload_routes->addRequirements(['_content_type_format' => 'bin']);

    $routes->addCollection($upload_routes);

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([static::JSON_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  protected static function getRoutesForResourceType(ResourceType $resource_type, $path_prefix) {
    // Internal resources have no routes.
    if ($resource_type->isInternal()) {
      return new RouteCollection();
    }

    $routes = new RouteCollection();
    // Collection route like `/jsonapi/node/article`.
    if ($resource_type->isLocatable()) {
      foreach (static::getLayoutBuilderDisplays($resource_type) as $key => $display) {
        $collection_route = new Route("/{$resource_type->getPath()}/{$display->getMode()}");
        $collection_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getCollection']);
        $collection_route->addDefaults(['view_mode' => $display->getMode()]);
        $collection_route->setMethods(['GET']);
        // Allow anybody access because "view" and "view label" access are checked
        // in the controller.
        $collection_route->setRequirement('_access', 'TRUE');
        $routes->add(static::getRouteName($resource_type, 'collection', $display->getMode()), $collection_route);
      }
    }

    // Individual routes like `/jsonapi/node/article/{uuid}` or
    // `/jsonapi/node/article/{uuid}/relationships/uid`.
    $routes->addCollection(static::getIndividualRoutesForResourceType($resource_type));

    // Add the resource type as a parameter to every resource route.
    foreach ($routes as $route) {
      static::addRouteParameter($route, static::RESOURCE_TYPE_KEY, ['type' => ResourceTypeConverter::PARAM_TYPE_ID]);
      $route->addDefaults([static::RESOURCE_TYPE_KEY => $resource_type->getTypeName()]);
    }

    // Resource routes all have the same base path.
    $routes->addPrefix($path_prefix);

    return $routes;
  }

  protected static function getLayoutBuilderDisplays(ResourceType $resource_type) {
    $displays = [];
    $view_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display');
    foreach ($view_display->loadByProperties([
      'targetEntityType' => $resource_type->getEntityTypeId(),
      'bundle' => $resource_type->getBundle(),
    ]) as $key => $display) {
      if ($display->isLayoutBuilderEnabled()) {
        $displays[$key] = $display;
      }
    }
    return $displays;
  }

  protected static function getIndividualRoutesForResourceType(ResourceType $resource_type) {
    if (!$resource_type->isLocatable()) {
      return new RouteCollection();
    }

    $routes = new RouteCollection();

    $path = $resource_type->getPath();
    $entity_type_id = $resource_type->getEntityTypeId();
    foreach (static::getLayoutBuilderDisplays($resource_type) as $key => $display) {
      // Individual read, update and remove.
      $individual_route = new Route("/{$path}/{$display->getMode()}/{entity}");
      $individual_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getIndividual']);
      $individual_route->addDefaults(['view_mode' => $display->getMode()]);
      $individual_route->setMethods(['GET']);
      // No _entity_access requirement because "view" and "view label" access are
      // checked in the controller. So it's safe to allow anybody access.
      $individual_route->setRequirement('_access', 'TRUE');
      $routes->add(static::getRouteName($resource_type, 'individual', $display->getMode()), $individual_route);
    }

    foreach ($resource_type->getRelatableResourceTypes() as $relationship_field_name => $target_resource_types) {
      foreach (static::getLayoutBuilderDisplays($resource_type) as $key => $display) {
        foreach ($target_resource_types as $target_resource_type) {
          if (!$target_resource_type->isMutable()) {
            continue;
          }
          foreach (static::getLayoutBuilderDisplays($target_resource_type) as $target_display) {
            // Read, update, add, or remove an individual resources relationships to
            // other resources.
            $relationship_route = new Route("/{$path}/{$display->getMode()}/{entity}/relationships/{$relationship_field_name}/{$target_display->getMode()}");
            $relationship_route->addDefaults(['_on_relationship' => TRUE]);
            $relationship_route->addDefaults(['related' => $relationship_field_name]);
            $relationship_route->addDefaults(['view_mode' => $target_display->getMode()]);
            $relationship_route->setRequirement(RelationshipFieldAccess::ROUTE_REQUIREMENT_KEY, $relationship_field_name);
            $relationship_route->setRequirement('_csrf_request_header_token', 'TRUE');
            $relationship_route_methods = ['GET'];
            $relationship_controller_methods = [
              'GET' => 'getRelationship',
            ];
            foreach ($relationship_route_methods as $method) {
              $method_specific_relationship_route = clone $relationship_route;
              $method_specific_relationship_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ":{$relationship_controller_methods[$method]}"]);
              $method_specific_relationship_route->addDefaults(['view_mode' => $display->getMode()]);
              $method_specific_relationship_route->setMethods($method);
              $routes->add(static::getRouteName($resource_type, sprintf("%s.relationship.%s", $relationship_field_name, strtolower($method)), $display->getMode(), $target_display->getMode()), $method_specific_relationship_route);
            }

            // Only create routes for related routes that target at least one
            // non-internal resource type.
            if (static::hasNonInternalTargetResourceTypes($target_resource_types)) {
              // Get an individual resource's related resources.
              $related_route = new Route("/{$path}/{$display->getMode()}/{entity}/{$relationship_field_name}/{$target_display->getMode()}");
              $related_route->setMethods(['GET']);
              $related_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':getRelated']);
              $related_route->addDefaults(['related' => $relationship_field_name]);
              $related_route->addDefaults(['view_mode' => $target_display->getMode()]);
              $related_route->setRequirement(RelationshipFieldAccess::ROUTE_REQUIREMENT_KEY, $relationship_field_name);
              $routes->add(static::getRouteName($resource_type, "$relationship_field_name.related", $display->getMode(), $target_display->getMode()), $related_route);
            }
          }
        }
      }
    }

    // Add entity parameter conversion to every route.
    $routes->addOptions(['parameters' => ['entity' => ['type' => 'entity:' . $entity_type_id]]]);

    return $routes;
  }

  public static function getRouteName(ResourceType $resource_type, $route_type, $view_mode = 'default', $target_view_mode = NULL) {
    if ($target_view_mode === NULL) {
      return sprintf('jsonapi.layout_builder.%s.%s.%s', $resource_type->getTypeName(), $route_type, $view_mode);
    }
    return sprintf('jsonapi.layout_builder.%s.%s.%s.%s', $resource_type->getTypeName(), $route_type, $view_mode, $target_view_mode);
  }

}
