# customize_admin_menu_per_role

Role-based admin dashboards and redirect logic for the Bestor back-end.

## Purpose

Provides custom admin landing pages for two non-superadmin roles (Redactor and Web Manager), and redirects those users away from the default `/admin` route to their role-specific dashboard. Superadmins (`administer site configuration` permission) are unaffected.

## Architecture overview

```
src/
├── Controller/
│   ├── RedactorDashboardController.php       — dashboard page for the Redactor role
│   └── WebmanagerDashboardController.php     — dashboard page for the Web Manager role
└── EventSubscriber/
    └── AdminConfigRedirectSubscriber.php     — redirects /admin to the correct dashboard
css/loggedin_admin_menu.css                   — styling for the logged-in admin menu
config/install/
├── system.menu.user-menu.yml                — user menu customisation
└── system.menu.web-manager-admin.yml        — separate admin menu for web managers
```

## Redirect logic (`AdminConfigRedirectSubscriber`)

Subscribes to `KernelEvents::REQUEST` at priority 31. When the requested path is `/admin`:
1. If the user has `administer site configuration` → no redirect (Drupal default admin)
2. If the user has `access webmanager dashboard` → redirect to web manager dashboard
3. If the user has `access redactor dashboard` → redirect to redactor dashboard

## Dashboards

Each dashboard controller renders a role-appropriate overview page. The dashboard routes are defined in `customize_admin_menu_per_role.routing.yml` and protected by the corresponding permissions defined in `customize_admin_menu_per_role.permissions.yml`.

## Configuration

The module installs a `web-manager-admin` menu (`system.menu.web-manager-admin.yml`) on enable. This is a separate menu from the default admin toolbar menu, allowing web manager-specific links without cluttering the main admin menu.

## Dependencies

- No custom module dependencies
