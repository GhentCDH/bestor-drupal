<?php

namespace Drupal\bestor_content_helper;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines a class to build a listing of Bestor site setting entities.
 */
class BestorSiteSettingListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Setting');
    $header['description'] = $this->t('Description');
    $header['value'] = $this->t('Current Value');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\bestor_content_helper\Entity\BestorSiteSetting $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    
    // Description
    $description = $entity->get('description')->value;
    $row['description'] = $description ?: $this->t('No description');
    
    // Value (truncated)
    $value = $entity->get('value')->value;
    if (!empty($value) && strlen($value) > 100) {
      $value = substr($value, 0, 100) . '...';
    }
    $row['value'] = $value ?: $this->t('Not set');
    
    return $row + parent::buildRow($entity);
  }

 
}