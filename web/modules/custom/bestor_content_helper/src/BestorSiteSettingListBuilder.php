<?php

namespace Drupal\bestor_content_helper;

use Drupal\bestor_content_helper\Entity\BestorSiteSetting;
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
    $header['label'] = $this->t('Setting');
    $header['description'] = $this->t('Description');
    $header['value'] = $this->t('Current Value');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(BestorSiteSetting $entity) {
    $row['label'] = $entity->label();
    
    $description = $entity->get('description')->value;
    $row['description'] = $description ?: $this->t('No description');
    
    $value = (string) $entity->get('value')->value;
    $row['value'] = [
      'data' => [
        '#markup' => mb_strlen($value) > 300
          ? mb_substr($value, 0, 300) . '…'
          : $value,
      ],
    ];
    
    return $row + parent::buildRow($entity);
  }

 
}