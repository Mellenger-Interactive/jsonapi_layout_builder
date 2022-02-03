<?php

namespace Drupal\jsonapi_layout_builder;

use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\jsonapi\IncludeResolver as CoreIncludeResolver;

/**
 * {@inheritdoc}
 */
class IncludeResolver extends CoreIncludeResolver {

  /**
   * The field name used by this storage.
   *
   * @var string
   */
  const FIELD_NAME = 'layout_builder__components';

  /**
   * {@inheritdoc}
   */
  public function resolve($data, $include_parameter) {
    if (strpos($include_parameter, static::FIELD_NAME) === FALSE) {
      return parent::resolve($data, $include_parameter);
    }

    $bundles = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('block_content');
    $includes = [];
    foreach ($bundles as $bundle => $b) {
      $this->getReferencesNested('block_content', $bundle, 3, '', $includes);
    }
    $ipe = 'layout_builder__components' . implode(',' . static::FIELD_NAME, array_values($includes[0]));
    $include_parameter_expanded = str_replace(static::FIELD_NAME, $ipe, $include_parameter);
    return parent::resolve($data, $include_parameter_expanded);
  }

  protected function getReferencesNested($entity_type, $bundle, $deep, $trail = '', &$result = []) {
    $d = $deep - 1;
    /** @var \Drupal\Core\Entity\EntityFieldManager $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = $entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $type_key = \Drupal::entityTypeManager()
      ->getDefinition($entity_type)
      ->getKey('bundle');
    $entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->create([$type_key => $bundle]);
    /**
     * @var string $field_name
     * @var \Drupal\field\Entity\FieldConfig $field
     */
    foreach ($fields as $field_name => $field) {
      $f = $entity->get($field_name)->applyDefaultValue();
      if (!$f instanceof EntityReferenceFieldItemList) {
        continue;
      }
      $target_type = $field->getItemDefinition()->getSetting('target_type');
      $entity_type = \Drupal::entityTypeManager()
        ->getStorage($target_type)
        ->getEntityType();
      if ($entity_type instanceof ConfigEntityType) {
        continue;
      }
      $t = $trail . '.' . $field_name;
      $result[$d][$t] = $t;
      if ($d < 1) {
        continue;
      }
      $handler = $field->getItemDefinition()->getSetting('handler');
      $handler_settings = $field->getItemDefinition()
        ->getSetting('handler_settings');
      $bundles = array_keys(\Drupal::service('entity_type.bundle.info')
        ->getBundleInfo($target_type));
      if (!empty($handler_settings['target_bundles'])) {
        $bundles = $handler_settings['target_bundles'];
      }
      foreach ($bundles as $b) {
        $this->getReferencesNested($target_type, $b, $d, $t, $result);
      }
    }
  }

}
