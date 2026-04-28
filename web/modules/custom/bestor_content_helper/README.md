# bestor_content_helper

Content utilities, site settings, language fallback, and Twig integration for the Bestor site.

## Purpose

Acts as a utility layer between Drupal's content model and the Twig templates. Provides a custom site-settings entity, language fallback handling, media processing, facet state access, and a Twig extension that exposes all of this to templates.

## Architecture overview

```
src/
├── Entity/BestorSiteSetting.php               — custom content entity for site-wide key/value settings
├── Access/BestorSiteSettingAccessControlHandler.php
├── BestorSiteSettingListBuilder.php
├── Form/BestorSiteSettingForm.php
├── EventSubscriber/
│   └── NodeLanguageFallbackSubscriber.php     — redirects to "translation unavailable" page
├── Controller/
│   └── TranslationUnavailableController.php   — renders the translation-unavailable page
├── Service/
│   ├── NodeContentAnalyzer.php               — determines node content type (isLemma, etc.)
│   ├── CurrentPageAnalyzer.php               — determines current page type from route
│   ├── NodeLanguageAnalyzer.php              — checks available translations for a node
│   ├── StandardNodeFieldProcessor.php        — aggregates and renders node fields for templates
│   ├── FacetResultsProvider.php              — reads active facet state from the URL
│   ├── SiteSettingManager.php                — CRUD for BestorSiteSetting entities
│   ├── MediaProcessor.php                    — resolves media entity file URLs
│   └── UrlProvider.php                       — generates language-aware URLs
└── TwigExtension/CustomTranslationExtension.php  — Twig façade delegating to all services above
templates/
└── bestor-translation-unavailable.html.twig
```

## The `BestorSiteSetting` entity

A simple key/value content entity (not a config entity) stored in the `bestor_site_setting` database table. Fields: `id`, `label`, `description`, `value` (English), `value_nl`, `value_fr`, `setting_group`.

It is a content entity — not config — so site settings survive config imports and can be managed via the admin UI at `/admin/config/site/bestor-settings` without being overwritten during deployments. Language variants (`value_nl`, `value_fr`) are stored as separate fields rather than entity translations, keeping the schema simple and avoiding translation workflows for operational settings.

## Language fallback subscriber (`NodeLanguageFallbackSubscriber`)

Subscribes to `KernelEvents::EXCEPTION` to intercept access-denied exceptions and redirect to the translation-unavailable page when a node exists but is not published in the current interface language.

It uses `KernelEvents::EXCEPTION` rather than `KernelEvents::REQUEST` because Drupal's router performs access checking at priority 33 inside its own `KernelEvents::REQUEST` subscriber. By the time a lower-priority request subscriber would fire, the access denial has already been processed into an exception. Hooking on `KernelEvents::EXCEPTION` is the correct interception point for post-router access checks.

The subscriber only redirects when the node genuinely lacks a published translation in the current language — not for permission-based denials on published content.

## Twig extension (`CustomTranslationExtension`)

Acts as a façade: it injects all eight services and exposes their functionality as Twig functions/filters. The extension itself contains no business logic — all computation happens in the injected services. This keeps the extension testable and the Twig API clean.

## Extension points

- `bestor_content_helper.twig_extension` — Twig extension tag; provides all custom Twig functions used in templates
- `bestor_content_helper.node_language_fallback_subscriber` — event subscriber; intercepts access exceptions for unpublished translations

## Configuration

Admin UI: `/admin/config/site/bestor-settings` — list and edit `BestorSiteSetting` entities.

## Dependencies

- `drupal:node`
- `drupal:content_translation`
- `facets:facets`
- `linkit:linkit`
