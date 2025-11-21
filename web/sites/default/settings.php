<?php

$databases = [];

/**
 * Location of the site configuration files.
 */
$settings['config_sync_directory'] = '../config/sync';

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 */
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'b4cih_Br6hrYFWn73_P5cMVIs__UnTn4vf9WhtJxeLrfvnr9L-vf9OnPw1HcAkxnnrxCM7cajA';

/**
 * Access control for update.php script.
 */
$settings['update_free_access'] = FALSE;

/**
 * Default mode for directories and files written by Drupal.
 * Value should be in PHP Octal Notation, with leading zero.
 */
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

/**
 * The default list of directories that will be ignored by Drupal's file API.
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

/**
 * The default number of entities to update in a batch process.
 * Decrease for lower memory consumption, increase for faster batch processing.
 */
$settings['entity_update_batch_size'] = 50;

/**
 * Entity update backup.
 *
 * This is used to inform the entity storage handler that the backup tables as
 * well as the original entity type and field storage definitions should be
 * retained after a successful entity update process.
 */
$settings['entity_update_backup'] = TRUE;

/**
 * State caching.
 *
 * State caching uses the cache collector pattern to cache all requested keys
 * from the state API in a single cache entry, which can greatly reduce the
 * amount of database queries. However, some sites may use state with a
 * lot of dynamic keys which could result in a very large cache.
 */
$settings['state_cache'] = TRUE;

/**
 * Public file base URL
 */
$settings['file_public_path'] = 'sites/default/files';

/**
 * Optimized assets path:
 */
$settings['file_assets_path'] = 'sites/default/files';

/**
 * Node migration type.
 *
 * This is used to force the migration system to use the classic node migrations
 * instead of the default complete node migrations. The migration system will
 * use the classic node migration only if there are existing migrate_map tables
 * for the classic node migrations and they contain data. These tables may not
 * exist if you are developing custom migrations and do not want to use the
 * complete node migrations. Set this to TRUE to force the use of the classic
 * node migrations.
 */
$settings['migrate_node_migrate_type_classic'] = FALSE;

$settings['locale_translation_auto_update'] = FALSE;

$settings['config_exclude_modules'] = ['devel', 'devel_generate'];

$config['system.site']['name'] = getenv('DRUPAL_SITE_NAME');

$settings['trusted_host_patterns'] = [
  '^' . preg_quote(getenv('DOMAIN'), '/') . '$',
];


/**
 * Database connection settings.
 */
$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASSWORD'),
  'host' => getenv('DB_HOST'),
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];

/**
 * Cache configuration.
 *
 * If MEMCACHE_HOST environment variable is set, use Memcache as the default
 * cache backend. Otherwise, use the in-memory cache backend.
 */
if (getenv('MEMCACHE_HOST')) {
  $settings['cache']['default'] = 'cache.backend.memcache';
  $settings['memcache']['key_prefix'] = getenv('MEMCACHE_KEY_PREFIX') ?: '';
  // Specify the Memcache server(s) with port.
  $settings['memcache']['servers'] = [
    getenv('MEMCACHE_HOST') . ':' . (getenv('MEMCACHE_PORT') ?: '11211') => 'default',
  ];
} else {
  $settings['cache']['default'] = 'cache.backend.memory';
}

/**
 * Elasticsearch configuration.
 */
$elasticsearch_host = getenv('ELASTIC_HOST');
$elasticsearch_port = getenv('ELASTIC_PORT');
$elasticsearch_scheme = getenv('ELASTIC_SCHEME');
$elasticsearch_url = sprintf('%s://%s:%s', $elasticsearch_scheme, $elasticsearch_host, $elasticsearch_port);

$config['search_api.server.elasticsearchconnectordocker']['backend_config']['connector_config']['url'] = $elasticsearch_url;

if (getenv('ELASTIC_AUTH_USER') and getenv('ELASTIC_AUTH_PASSWORD')) {
  $config['search_api.server.elasticsearchconnectordocker']['backend_config']['connector_config']['authentication_type'] = 'Basic';
  $config['elasticsearch_connector.cluster.elasticsearchconnectordocker']['backend_config']['connector_config']['basic_auth_username'] = getenv('ELASTIC_AUTH_USER');
  $config['elasticsearch_connector.cluster.elasticsearchconnectordocker']['backend_config']['connector_config']['basic_auth_password'] = getenv('ELASTIC_AUTH_PASSWORD');
}

/**
 * Reverse proxy configuration
 *
 * Since the application is behind a reverse proxy
 */
// TODO: verify the proxy addresses and make sure that they are static
$settings['reverse_proxy'] = TRUE;

// If you are behind a reverse proxy, you can specify the addresses of the proxy servers.
// You can use CIDR notation to specify a range of addresses.
// You can also use the special value 'REMOTE_ADDR' to trust the address of the immediate
// client connected to your Drupal server (which may be another proxy).
$settings['reverse_proxy_addresses'] = [
  '172.18.56.0/24',
];

$settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO | \Symfony\Component\HttpFoundation\Request::HEADER_FORWARDED;

// Load local settings if available.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
