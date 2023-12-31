<?php

namespace Drupal\jsonapi_layout_builder\Normalizer;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Base class for decorated normalizers.
 */
class JsonApiNormalizerDecoratorBase implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface {

  /**
   * The decorated (de)normalizer.
   *
   * @var \Symfony\Component\Serializer\SerializerAwareInterface|\Symfony\Component\Serializer\Normalizer\NormalizerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $inner;

  /**
   * @var \Symfony\Component\Routing\Route
   */
  protected $route;

  /**
   * JsonApiNormalizerDecoratorBase constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerAwareInterface|\Symfony\Component\Serializer\Normalizer\NormalizerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface $inner
   *   The decorated normalizer or denormalizer.
   */
  public function __construct($inner, RouteMatchInterface $route_match) {
    assert($inner instanceof NormalizerInterface || $inner instanceof DenormalizerInterface);
    assert($inner instanceof SerializerAwareInterface);
    $this->inner = $inner;
    $this->route = $route_match->getRouteObject();
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    return $this->inner->normalize($object, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    return $this->inner->denormalize($data, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function setSerializer(SerializerInterface $serializer) {
    $this->inner->setSerializer($serializer);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $this->inner instanceof NormalizerInterface && $this->inner->supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $this->inner instanceof DenormalizerInterface && $this->inner->supportsDenormalization($data, $type, $format);
  }

}
