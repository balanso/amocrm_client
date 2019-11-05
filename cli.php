<?

if (!php_sapi_name() === 'cli') {
  die();
}

$access = true;
require_once __DIR__ . '/requires.php';

if (isset($argv[1])) {
  $function      = $argv[1];
  $args          = array_slice($argv, 2);
  $args_parsed   = array();
  $args_filtered = array();

  if (function_exists($function)) {
    foreach ($args as $key => $value) {
      parse_str($value, $args_parsed[$key]);
    }

    // logw($args_parsed);

    foreach ($args_parsed as $key => $param) {
      foreach ($param as $k => $val) {
        if ($val == '') {
          $args_filtered[] = $k;
        } else {
          $args_filtered[] = $param;
        }
      }
    }
    // logw($args_filtered);

    $result = call_user_func_array($function, $args_filtered);

    if (!empty($result)) {
      logw($result);
    }

  } else {
    echo ('Функция "' . $function . '" не существует');
  }
} else {
  echo ('Формат: Имя_функции Аргументы (строка или массив вида a=1&b[]=1&b[]=2)');
}

function test_cli()
{
  print_r(func_get_args());
}

function run($path = '')
{
  if (is_file(__DIR__ . '/' . $path)) {
    include __DIR__ . '/' . $path;
  } else {
    echo 'Файл ' . $path . ' не найден!';
  }
}
