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
 *   translatable = FALSE,
 *   admin_permission = "administer bestor site settings",
 *   handlers = {
 *     "access" = "Drupal\bestor_content_helper\Access\BestorSiteSettingAccessControlHandler",
 *     "list_builder" = "Drupal\bestor_content_helper\BestorSiteSettingListBuilder",
 *     "form" = {
 *       "default" = "Drupal\bestor_content_helper\Form\BestorSiteSettingForm",
 *       "edit" = "Drupal\bestor_content_helper\Form\BestorSiteSettingForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/site/bestor-settings",
 *     "edit-form" = "/admin/config/site/bestor-settings/{bestor_site_setting}/edit"
 *   }
 * )
 */
class BestorSiteSetting extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('max_length', 255);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('max_length', 255);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setTranslatable(FALSE)
      ->setLabel(t('Description'));

    $fields['value'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Default value (English if translatable)'))
      ->setTranslatable(FALSE)
      ->setDescription(t('The default setting, if translatable provide the English value'));

    $fields['value_nl'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Dutch site variant'))
      ->setTranslatable(FALSE)
      ->setDescription(t('Value shown when the website is displayed in Dutch. Leave open if the default value should be shown'));

    $fields['value_fr'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('French site variant'))
      ->setTranslatable(FALSE)
      ->setDescription(t('Value shown when the website is displayed in French. Leave open if the default value should be shown'));

    $fields['setting_group'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Setting category'))
      ->setTranslatable(FALSE)
      ->setDescription(t('The group this setting belongs to'));

    return $fields;
  }

  /**
   * Get value for current or specific language.
   *
   * @param string|null $langcode
   *   The language code. If NULL, uses current language.
   *
   * @return string|null
   *   The value for the language, or default value if not set.
   */
  public function getValue($langcode = NULL) {
    if ($langcode === NULL) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    // Try language-specific field first
    $field_name = 'value_' . $langcode;
    if ($this->hasField($field_name)) {
      $translated_value = $this->get($field_name)->value;
      if (!empty($translated_value)) {
        return $translated_value;
      }
    }
    
    // Otherwise the default value
    return $this->get('value')->value;
  }

}