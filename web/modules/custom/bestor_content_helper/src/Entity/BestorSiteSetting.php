<?php

namespace Drupal\bestor_content_helper\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Bestor site setting entity.
 *
 * @ContentEntityType(
 *   id = "bestor_site_setting",
 *   label = @Translation("Bestor site setting"),
 *   base_table = "bestor_site_setting",
 *   data_table = "bestor_site_setting_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer bestor site settings",
 *   handlers = {
 *     "list_builder" = "Drupal\bestor_content_helper\BestorSiteSettingListBuilder",
 *     "form" = {
 *       "default" = "Drupal\bestor_content_helper\Form\BestorSiteSettingForm",
 *       "edit" = "Drupal\bestor_content_helper\Form\BestorSiteSettingForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "langcode" = "langcode"
 *   },
 *   links = {
 *     "collection" = "/admin/config/site/bestor-settings",
 *     "edit-form" = "/admin/config/site/bestor-settings/{bestor_site_setting}/edit"
 *   }
 * )
 */
class BestorSiteSetting extends ContentEntityBase {

  public function label() {
    return $this->get('label')->value;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255);

    $fields['value'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Value'))
      ->setTranslatable(TRUE)
      ->setDefaultValue('');

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setTranslatable(TRUE);

    return $fields;
  }
}