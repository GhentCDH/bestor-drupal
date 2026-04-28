# Custom themes

## Theme inventory

| Theme | Base | Status | Purpose |
|-------|------|--------|---------|
| `bestor` | `jakarta` | **Active (frontend)** | Visitor-facing theme: templates, CSS/JS overrides, page layout |
| `jakarta` | *(none)* | **Active (base)** | Design system: SDC component library, compiled CSS/JS, webpack build |
| `bestor_admin` | `claro` | **Active (admin)** | Back-end admin interface styling and block placements |
| `old_bestor` | `ruhi` | **Deprecated** | Previous frontend theme; do not extend |

## Theme hierarchy

```
jakarta  (design system, no parent)
  └── bestor  (frontend, active)

claro  (Drupal core admin theme)
  └── bestor_admin  (admin, active)

ruhi
  └── old_bestor  (deprecated, do not use)
```

## When to use each theme

| Situation | Where to work |
|-----------|---------------|
| Adding or changing a Twig template | `bestor/templates/` |
| Changing frontend CSS or JS | `bestor/css/` or `bestor/js/` |
| Fixing or updating a shared UI component (button, card, menu, …) | `jakarta/components/` |
| Changing compiled design tokens or global SCSS | `jakarta/src/` — then run `npm run build` |
| Adding or changing the admin UI appearance | `bestor_admin/css/` or `bestor_admin/js/` |
| Anything related to `old_bestor` | Do not — this theme is deprecated |

**Rule**: frontend template and style work goes in `bestor`. Only edit `jakarta` when the change must apply to the design system itself and would be appropriate for any site using `jakarta`.

## Build system

The build pipeline lives entirely in `jakarta/`:

```bash
cd web/themes/custom/jakarta
nvm use            # use the Node version in .nvmrc
npm install
npm run watch      # development
npm run build      # production
```

Child themes (`bestor`) consume compiled output from `jakarta`; they do not have their own build step.

## SDC (Single Directory Components)

`jakarta` contains 150+ SDC components organized by atomic design tier (atoms / molecules / organisms). `bestor` adds one project-specific SDC component: `components/molecules/c-media-gallery/` — this lives in `bestor` because it is specific to the Bestor media field and not reusable across other sites.

## Further reading

- [bestor/README.md](bestor/README.md)
- [jakarta/README.md](jakarta/README.md)
- [bestor_admin/README.md](bestor_admin/README.md)
- [old_bestor/README.md](old_bestor/README.md)
