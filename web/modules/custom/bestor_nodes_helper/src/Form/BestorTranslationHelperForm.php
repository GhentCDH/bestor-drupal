<?php

namespace Drupal\bestor_nodes_helper\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bestor_nodes_helper\Service\CustomTranslations;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure custom translations.
 */
class BestorTranslationHelperForm extends ConfigFormBase {

  protected CustomTranslations $customTranslations;

  /**
   * Constructs a TranslationHelperSettingsForm object.
   *
   * @param CustomTranslations $custom_translations
   *   The custom translations service.
   */
  public function __construct(CustomTranslations $custom_translations) {
    $this->customTranslations = $custom_translations;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bestor_nodes_helper.custom_translations')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bestor_nodes_helper.translations'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bestor_nodes_helper_translations';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bestor_nodes_helper.translations');
    $custom_translations = $config->get('custom_translations') ?? [];
    $definitions = $this->customTranslations->getDefinitions();
    $languages = $this->customTranslations->getSupportedLanguages();

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Manage custom translations for interface labels.') . '</p>',
      '#prefix' => '<div class="messages messages--info">',
      '#suffix' => '<p><strong>' . $this->t('To add new translation keys, edit: <code>src/Service/CustomTranslations.php</code> → getDefinitions()') . '</strong></p></div>',
    ];

    // Build header dynamically
    $header = [$this->t('Key')];
    foreach ($languages as $langcode) {
      $header[] = $this->t(ucfirst($langcode));
    }
    $header[] = $this->t('Description');

    $form['translations'] = [
      '#type' => 'table',
      '#header' => $header,
    ];

    foreach ($definitions as $key => $definition) {
      $current = $custom_translations[$key] ?? [];
      
      $form['translations'][$key]['key'] = [
        '#markup' => '<code>' . $key . '</code>',
      ];

      foreach ($languages as $langcode) {
        $form['translations'][$key][$langcode] = [
          '#type' => 'textfield',
          '#default_value' => $current[$langcode] ?? $definition[$langcode],
          '#size' => 30,
          '#required' => TRUE,
        ];
      }

      $form['translations'][$key]['description'] = [
        '#markup' => '<small>' . $definition['description'] . '</small>',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    
    $translations = [];
    $languages = $this->customTranslations->getSupportedLanguages();
    
    foreach ($form_state->getValue('translations') as $key => $values) {
      $translations[$key] = [];
      foreach ($languages as $langcode) {
        $translations[$key][$langcode] = $values[$langcode];
      }
    }

    $this->config('bestor_nodes_helper.translations')
      ->set('custom_translations', $translations)
      ->save();

    $this->customTranslations->clearCache();
  }

}