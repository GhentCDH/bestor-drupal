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
   * Gets media info from entity field.
   *
   * @param NodeInterface|ParagraphInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param array|null $options
   *   Options: 'image_style', 'cardinality' (single/multiple).
   *
   * @return array
   *   Single item array or array of arrays depending on cardinality.
   */
  public function getEntityMediaInfo(NodeInterface|ParagraphInterface $entity, string $field_name, array $options = NULL): array {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return [];
    }

    $media_items = $entity->get($field_name)->referencedEntities();
    $results = [];

    foreach ($media_items as $delta => $media_item) {
      if (!$media_item instanceof MediaInterface) {
        continue;
      }

      $info = $this->processMediaItem($media_item, $options);
      if ($info) {
        $info['delta'] = $delta;
        $results[] = $info;
      }
    }
    return $results;
  }



  /**
   * Process a single media item.
   *
   * @param \Drupal\media\MediaInterface $media_item
   *   The media entity.
   * @param array|null $options
   *   Options including 'image_style'.
   *
   * @return array|null
   *   Processed media info.
   */
  protected function processMediaItem(MediaInterface $media_item, array $options = NULL): ?array {
    $type = $media_item->bundle();
    $img_style = $options['image_style'] ?? NULL;

    $result = [
      'type' => $type,
      'display' => 'default',
      'entity' => $media_item,
    ];

    switch ($type) {
      case 'image':
        return array_merge($result, [
          'url' => $this->getStyledImageUrl($media_item, $img_style),
          'original_url' => $this->getStyledImageUrl($media_item, NULL),
          'alt' => $this->getImageAlt($media_item) ?? '',
          'display' => 'custom',
        ]);
        
      default:
        return $result;
    }
  }

  public function getMediaItemCount(NodeInterface|ParagraphInterface $entity, string $field_name): int {
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return 0;
    }
    return count($entity->get($field_name)->referencedEntities());
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
      return $this->fileUrlGenerator->generateAbsoluteString($uri);
    }

    // Try to load the image style.
    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load($style_name);

    // If style exists, return styled URL.
    if ($image_style instanceof ImageStyle) {
      return $image_style->buildUrl($uri);
    }

    // Fallback to original if style doesn't exist and fallback is enabled.
    if ($fallback_to_original) {
      return $this->fileUrlGenerator->generateAbsoluteString($uri); 
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