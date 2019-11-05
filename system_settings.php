<?
$fields_id = array();
if (is_file(__DIR__ . '/logs/system/fields_id.json')) {
  $fields_id = json_decode(file_get_contents(__DIR__ . '/logs/system/fields_id.json'), true);
}

//utm_content, utm_term, utm_medium, utm_campaign, utm_keyword, utm_source

if (!defined('FIELD_ID')) {
  define('FIELD_ID', serialize($fields_id));
}

if (!defined('API')) {
  define('API', serialize(array(
    'login'      => 'LOGIN',
    'api_key'       => 'API_KEY',
    'domain'     => 'DOMAIN',

    'secret_key' => 'a4f8205462f10363216233a311efd39d',
  )));
}

$log_settings = [
  'to_screen' => false,
  'save_logs' => true,
];

if (php_sapi_name() === 'cli') {
  $log_settings['web']       = false;
  $log_settings['to_screen'] = true;
} else {
  $log_settings['web'] = true;
}
