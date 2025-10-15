<?php

/**
 * @file
 * Local development override configuration.
 *
 * This file reads database and other configuration from environment variables.
 * It will override any settings in settings.php.
 */

// Database configuration from environment variables
$databases['default']['default'] = [
  'database' => getenv('DRUPAL_DATABASE_NAME') ?: 'drupal',
  'username' => getenv('DRUPAL_DATABASE_USERNAME') ?: 'drupal',
  'password' => getenv('DRUPAL_DATABASE_PASSWORD') ?: 'drupal',
  'host' => getenv('DRUPAL_DATABASE_HOST') ?: 'db',
  'port' => getenv('DRUPAL_DATABASE_PORT') ?: '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
];

// Trusted host patterns
$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^.+\.localhost$',
  '^bestor.*$',
  '^' . (getenv('PROJECT_NAME') ?: 'drupal') . '.*$',
];

// File paths
// $settings['file_public_path'] = 'sites/default/files';
// $settings['file_private_path'] = '../private';
// $settings['config_sync_directory'] = '../config/sync';

// Development settings
// $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
// $config['system.logging']['error_level'] = 'verbose';
// $config['system.performance']['css']['preprocess'] = FALSE;
// $config['system.performance']['js']['preprocess'] = FALSE;

// Disable caching for development
// $settings['cache']['bins']['render'] = 'cache.backend.null';
// $settings['cache']['bins']['page'] = 'cache.backend.null';
// $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Allow test modules and themes
// $settings['extension_discovery_scan_tests'] = TRUE;

// Skip file system permissions hardening
// $settings['skip_permissions_hardening'] = TRUE;

// Show all error messages
// $config['system.logging']['error_level'] = 'verbose';

// Disable CSS and JS aggregation
// $config['system.performance']['css']['preprocess'] = FALSE;
// $config['system.performance']['js']['preprocess'] = FALSE;

// Memcache configuration from environment variables
$memcache_host = getenv('MEMCACHE_HOST') ?: 'memcache';
$memcache_port = getenv('MEMCACHE_PORT') ?: '11211';

// Memcache settings 
$settings['cache']['default'] = 'cache.backend.memcache'; 
// Database cache 
// $settings['cache']['default'] = 'cache.backend.database';
// No cache
// $settings['cache']['default'] = 'cache.backend.null';

if (extension_loaded('memcache')) {
  $settings['memcache']['servers'] = [$memcache_host . ':' . $memcache_port => 'default'];
  $settings['memcache']['bins'] = ['default' => 'default'];
  $settings['memcache']['key_prefix'] = '';
  $settings['cache']['default'] = 'cache.backend.memcache';
}


// Elasticsearch configuration from environment variables
// Prefer explicit SEARCH_API_SERVER_HOST/PORT/SCHEME from env, otherwise derive a sensible default from PROJECT_NAME and ENVIRONMENT.
$elasticsearch_host = getenv('SEARCH_API_SERVER_HOST');
$elasticsearch_port = getenv('SEARCH_API_SERVER_PORT') ?: '9200';
$elasticsearch_scheme = getenv('SEARCH_API_SCHEME') ?: 'http';

if (empty($elasticsearch_host)) {
  $project_name = getenv('PROJECT_NAME') ?: 'drupal-project';
  $env_type = getenv('ENVIRONMENT') ?: 'development';
  // If production, use '-elasticsearch-prd', otherwise '-elasticsearch-dev'.
  $suffix = ($env_type === 'production') ? 'elasticsearch-prd' : 'elasticsearch-dev';
  $elasticsearch_host = $project_name . '-' . $suffix;
}

$elasticsearch_url = sprintf('%s://%s:%s', $elasticsearch_scheme, $elasticsearch_host, $elasticsearch_port);

$config['search_api.server.elasticsearchconnectordocker']['backend_config']['connector_config']['url'] = $elasticsearch_url;
