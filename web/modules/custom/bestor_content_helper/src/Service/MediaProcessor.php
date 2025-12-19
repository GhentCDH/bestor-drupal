<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\media\MediaInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Service for handling media images.
 */
class MediaProcessor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a MediaProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Gets image URL from a node's media field.
   *
   * @param NodeInterface|ParagraphInterface $entity
   *   The node entity.
   * @param string $field_name
   *   The field name.
   * @param array|null $options
   *   Mediatype specific options.
   *
   * @return string|null
   *   The image URL, or NULL if not available.
   */
  public function getEntityMediaInfo(NodeInterface|ParagraphInterface $entity, string $field_name, array $options = NULL): ?array {
    dpm($field_name);
    dpm($entity->get($field_name));
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return NULL;
    }

    $media = $entity->get($field_name)->entity;
    dpm($field_name);
    dpm($entity->get($field_name)->getFieldDefinition()->getFieldStorageDefinition()->getCardinality());
    dpm($media);
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    
    $type = $media->bundle();

    $result = [
      'type' => $type,
      'display' => 'default'
    ];

    switch ($type) {
      case 'image':
        $img_style = $options['image_style'] ?? NULL;
        return array_merge($result, [
          'url' => $this->getStyledImageUrl($media, $img_style),
          'alt' => $this->getImageAlt($media) ?? '',
          'display' => 'custom'
        ]);
      default:
        return $result;
    }
  }

  /**
   * Gets the styled image URL from a media field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   * @param string|null $style_name
   *   The image style name, or NULL for original.
   * @param bool $fallback_to_original
   *   Whether to fallback to original if style doesn't exist.
   *
   * @return string|null
   *   The styled image URL, or NULL if not available.
   */
  public function getStyledImageUrl(MediaInterface $media, string $style_name = NULL, bool $fallback_to_original = TRUE): ?string {
    // Get the file from the media entity.
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }

    $file = $media->get('field_media_image')->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $uri = $file->getFileUri();

    // If no style requested, return original.
    if ($style_name === NULL) {
      return $this->fileUrlGenerator->generateString($uri);
    }

    // Try to load the image style.
    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load($style_name);

    // If style exists, return styled URL.
    if ($image_style instanceof ImageStyle) {
      return $this->fileUrlGenerator->transformRelative(
        $image_style->buildUrl($uri)
      );
    }

    // Fallback to original if style doesn't exist and fallback is enabled.
    if ($fallback_to_original) {
      return $this->fileUrlGenerator->generateString($uri);
    }

    return NULL;
  }


  /**
   * Gets the alt text from a media image field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string|null
   *   The alt text, or NULL if not available.
   */
  public function getImageAlt(MediaInterface $media): ?string {
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }

    return $media->get('field_media_image')->alt;
  }
}