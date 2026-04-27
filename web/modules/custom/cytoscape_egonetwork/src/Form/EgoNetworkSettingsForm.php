<?php

namespace Drupal\cytoscape_egonetwork\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Cytoscape Ego Network.
 *
 * Allows admins to select which node bundles get the "Show ego network"
 * checkbox on their node edit form.
 */
class EgoNetworkSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'cytoscape_egonetwork_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['cytoscape_egonetwork.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config         = $this->config('cytoscape_egonetwork.settings');
    $enabledBundles = $config->get('enabled_bundles') ?? [];

    $bundleOptions = [];
    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $bundle) {
      $bundleOptions[$bundle->id()] = $bundle->label();
    }

    $form['enabled_bundles'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Enabled node bundles'),
      '#description'   => $this->t(
        'Select the node bundles that get a "Show ego network" checkbox on their edit form. '
        . 'The graph is only built for nodes where that checkbox is ticked.'
      ),
      '#options'       => $bundleOptions,
      '#default_value' => $enabledBundles,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Filter out unchecked values (checkboxes returns 0 for unchecked).
    $enabled = array_values(array_filter($form_state->getValue('enabled_bundles')));

    $this->config('cytoscape_egonetwork.settings')
      ->set('enabled_bundles', $enabled)
      ->save();

    // Rebuild field definitions so hook_entity_bundle_field_info changes
    // take effect immediately without a drush cr.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    parent::submitForm($form, $form_state);
  }

}