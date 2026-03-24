<?php

namespace Drupal\sapi_language_fallback\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views filter plugin: language filtering with optional user-facing fallback.
 *
 * Add this filter instead of (not alongside) the built-in "Search: Language"
 * filter. When the user enables the fallback toggle, all language versions are
 * fetched and hook_views_post_execute reduces them to one per node.
 *
 * @ViewsFilter("sapi_language_fallback")
 */
class LanguageFallbackFilter extends FilterPluginBase {

  protected LanguageManagerInterface $languageManager;

  protected bool $fallbackEnabled = FALSE;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
    );
  }

  public function isFallbackEnabled(): bool {
    return $this->fallbackEnabled;
  }

  // ---------------------------------------------------------------------------
  // Plugin options.
  // ---------------------------------------------------------------------------

  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value'] = ['default' => FALSE];
    $options['fallback_enabled_default'] = ['default' => 'no_fallback'];
    $options['language_priority'] = ['default' => []];
    $options['link_mode'] = ['default' => 'actual_language'];

    return $options;
  }

  // ---------------------------------------------------------------------------
  // Admin options form.
  // ---------------------------------------------------------------------------

  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['fallback_enabled_default'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default value'),
      '#options' => [
        'no_fallback' => $this->t('Current language only'),
        'fallback' => $this->t('Include other languages as fallback'),
      ],
      '#default_value' => $this->options['fallback_enabled_default'],
    ];

    $form['language_priority'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Fallback language priority'),
      '#description' => $this->t(
        'Language codes in priority order, one per line (highest priority first). '
        . 'Leave empty to use the first language found in the result set.<br>'
        . 'Example: <code>en</code> / <code>fr</code> / <code>nl</code>'
      ),
      '#default_value' => implode("\n", $this->options['language_priority']),
      '#rows' => 4,
    ];
  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::validateOptionsForm($form, $form_state);
    $raw = (string) $form_state->getValue(['options', 'language_priority']);
    foreach (array_filter(array_map('trim', explode("\n", $raw))) as $code) {
      if (!preg_match('/^[a-z]{2,8}(-[A-Za-z0-9]{2,8})*$/', $code)) {
        $form_state->setError(
          $form['language_priority'],
          $this->t('"%code" is not a valid language code.', ['%code' => $code])
        );
      }
    }
  }

  public function submitOptionsForm(&$form, FormStateInterface $form_state): void {
    $raw = (string) $form_state->getValue(['options', 'language_priority']);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $raw))));
    $form_state->setValue(['options', 'language_priority'], $lines);
    parent::submitOptionsForm($form, $form_state);
  }

  // ---------------------------------------------------------------------------
  // Expose form.
  // ---------------------------------------------------------------------------

  public function buildExposeForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposeForm($form, $form_state);
    unset($form['expose']['multiple'], $form['expose']['remember'], $form['expose']['remember_roles']);

    $form['expose']['link_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Link mode for fallback results'),
      '#description' => $this->t('How URLs are generated when a node is shown in a fallback language.'),
      '#options' => [
        'actual_language' => $this->t('<strong>Link to the available language version</strong> (default) — the node renders and links in the fallback language.'),
        'current_language' => $this->t('<strong>Link to the current-language URL</strong> — useful with a custom "translation unavailable" controller.'),
      ],
      '#default_value' => $this->options['link_mode'] ?? 'actual_language',
    ];
  }

  public function submitExposeForm($form, FormStateInterface $form_state): void {
    parent::submitExposeForm($form, $form_state);
    $value = $form_state->getValue(['options', 'expose', 'link_mode']);
    if ($value !== NULL) {
      $form_state->setValue(['options', 'link_mode'], $value);
      $this->options['link_mode'] = $value;
    }
  }

  // ---------------------------------------------------------------------------
  // Exposed value form (frontend only).
  // ---------------------------------------------------------------------------

  protected function valueForm(&$form, FormStateInterface $form_state): void {
    if (!$form_state->get('exposed')) {
      return;
    }
    $form['value'] = [
      '#type' => 'radios',
      '#title' => $this->t('Language fallback'),
      '#options' => [
        'no_fallback' => $this->t('Current language only'),
        'fallback' => $this->t('Include other languages as fallback'),
      ],
      '#default_value' => $this->options['fallback_enabled_default'],
    ];
  }

  public function acceptExposedInput($input): bool {
    if ($this->isExposed()) {
      $identifier = $this->options['expose']['identifier'] ?? 'value';
      $value = $input[$identifier] ?? $this->options['fallback_enabled_default'];
      $this->fallbackEnabled = ($value === 'fallback');
    }
    else {
      $this->fallbackEnabled = ($this->options['fallback_enabled_default'] === 'fallback');
    }
    return TRUE;
  }

  // ---------------------------------------------------------------------------
  // Query.
  // ---------------------------------------------------------------------------

  public function query(): void {
    if (!$this->isExposed()) {
      $this->fallbackEnabled = ($this->options['fallback_enabled_default'] === 'fallback');
    }

    /** @var \Drupal\search_api\Plugin\views\query\SearchApiQuery $query */
    $query = $this->query;
    if (!$query instanceof SearchApiQuery) {
      \Drupal::logger('sapi_language_fallback')->warning(
        'LanguageFallbackFilter requires a Search API view. No language filter applied.'
      );
      return;
    }

    $current_lang = $this->languageManager->getCurrentLanguage()->getId();

    if (!$this->fallbackEnabled) {
      $query->addCondition('search_api_language', $current_lang);
      return;
    }

    // Fallback mode: no language condition; post_execute + pre_render handle
    // deduplication and entity swapping.
    $this->view->sapi_language_fallback = [
      'enabled' => TRUE,
      'current_langcode' => $current_lang,
      'language_priority' => array_values($this->options['language_priority'] ?? []),
      'link_mode' => $this->options['link_mode'] ?? 'actual_language',
    ];
  }

  // ---------------------------------------------------------------------------
  // Misc.
  // ---------------------------------------------------------------------------

  public function adminSummary(): string {
    return $this->t('Language filter with optional fallback');
  }

  public function canExpose(): bool {
    return TRUE;
  }

}