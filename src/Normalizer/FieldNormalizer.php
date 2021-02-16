<?php

namespace Drupal\jsonapi_layout_builder\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jsonapi\JsonApiResource\Relationship;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi\Normalizer\FieldNormalizer as JsonapiFieldNormalizer;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * {@inheritdoc}
 */
class FieldNormalizer extends JsonApiNormalizerDecoratorBase {

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $normalization_field = parent::normalize($field, $format, $context);
    if ($this->route->getDefault('view_mode') === NULL) {
      return $normalization_field;
    }
    $settings = $field->getSettings();
    $settings['cardinality'] = $field->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();
    $normalization_settings = new CacheableNormalization(
      new CacheableMetadata(),
      $settings
    );
    $values = [
      'values' => ($settings['cardinality'] === 1) ? CacheableNormalization::aggregate([$normalization_field]) : $normalization_field,
      'settings' => $normalization_settings,
    ];
    return CacheableNormalization::aggregate($values);
  }

}
