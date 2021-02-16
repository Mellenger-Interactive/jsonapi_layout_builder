<?php

namespace Drupal\jsonapi_layout_builder;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replace the resource type repository for our own configurable version.
 */
class JsonapiLayoutBuilderServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Enable normalizers in the "src-impostor-normalizers" directory to be
    // within the \Drupal\jsonapi\Normalizer namespace in order to circumvent
    // the encapsulation enforced by
    // \Drupal\jsonapi\Serializer\Serializer::__construct().
    $container_namespaces = $container->getParameter('container.namespaces');
    $container_modules = $container->getParameter('container.modules');
    $jsonapi_impostor_path = dirname($container_modules['jsonapi_layout_builder']['pathname']) . '/src-impostor-normalizers';
    $container_namespaces['Drupal\jsonapi\Normalizer\ImpostorFrom\jsonapi_layout_builder'][] = $jsonapi_impostor_path;
    // Manually include the impostor definitions to avoid class not found error
    // during compilation, which gets triggered though cache-clear.
    $container->getDefinition('serializer.normalizer.field.jsonapi_layout_builder')
      ->setFile($jsonapi_impostor_path . '/FieldNormalizerImpostor.php');
    $container->setParameter('container.namespaces', $container_namespaces);
  }

}
