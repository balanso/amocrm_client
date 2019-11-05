<?
define('AMO_FIELDS_ID_FILE', __DIR__ . '/logs/system/fields_id.json');
define('AMO_ACCOUNT_DATA_LOG', __DIR__ . '/logs/system/account_data.log');

require_once __DIR__ . '/common_functions.php';
require_once __DIR__ . '/api_functions.php';

log_init();

if (!is_file(__DIR__ . '/logs/system/fields_id.json')) {
  generate_account_files();
}
