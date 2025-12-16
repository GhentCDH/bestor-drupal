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
   * Mapping for custom group labels.
   */
  protected function getGroupLabels() {
    return [
      'search_banner' => 'Frontpage search banner',
      'footer_disclaimer' => 'Footer disclaimer',
      'footer_newsletter' => 'Newsletter url',
      'footer_socials' => 'Socials url', 
      'general_site_defaults' => 'Site defaults',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['setting_group'] = $this->t('Setting group');
    $header['id'] = $this->t('Setting id');
    $header['label'] = $this->t('Setting');
    $header['description'] = $this->t('Description');
    $header['value'] = $this->t('Default value');
    $header['value_nl'] = $this->t('Override value for Dutch site');
    $header['value_fr'] = $this->t('Override value for French site');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['setting_group'] = $entity->get('setting_group')->value ?? 'Uncategorized';

    /** @var \Drupal\bestor_content_helper\Entity\BestorSiteSetting $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    
    // Description
    $description = $entity->get('description')->value;
    $row['description'] = $description ?: $this->t('No description');
    
    // Value (truncated)
    foreach(['value', 'value_nl', 'value_fr'] as $val_translation) {
      $value = $entity->get($val_translation)->value;
      if (!empty($value) && strlen($value) > 300) {
        $value = substr($value, 0, 300) . '...';
      }
      $row[$val_translation] = $value ?: $this->t('Not set');
      
    }

    return $row + parent::buildRow($entity);
  }


  public function render(){
    $build = parent::render();
    $group_labels = $this->getGroupLabels();
    $rows = $build['table']['#rows'];
   
    if (empty($rows)) {
      return $build;
    }

    $grouped = [];
    foreach($rows as $row){
      $gr_id = $row['setting_group'] ?? 'Uncategorized';
      if(!empty($gr_id) && isset($group_labels[$gr_id])) {
        $row['setting_group'] = $group_labels[$gr_id];
        $grouped[$gr_id][] = $row;
      } else {
        $row['setting_group'] = 'Uncategorized';
        $grouped['Uncategorized'][] = $row;  
      }
    }

    // sort in the order of the group label definitions
    $build_rows = [];
    foreach($group_labels as $gr_id => $gr_label) {
      if (isset($grouped[$gr_id])){
         $build_rows = array_merge($build_rows, $grouped[$gr_id]);
      }  
    }
    if (isset($grouped['Uncategorized'])){
      $build_rows = array_merge($build_rows, $grouped['Uncategorized']);
    }
    $build['table']['#rows'] = $build_rows;
    return $build;
  }
}