<?
require_once __DIR__ . '/system_settings.php';
function send_data_request($url, $data)
{
  $api = unserialize(API);
  $url = $url . '?secret_key=' . $api['secret_key'] . '&data=' . urlencode(serialize($data));
  file_get_contents($url);
  return true;
}

function send_data_post($url, $data)
{
  $api = unserialize(API);
  $url = $url . '?secret_key=' . $api['secret_key'];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_USERAGENT, 'api');
  curl_setopt($ch, CURLOPT_TIMEOUT, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
  curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

  $response = curl_exec($ch);

  return true;
}

//alias send_data_post;
function send_data($url, $data)
{
  $api = unserialize(API);
  $url = $url . '?secret_key=' . $api['secret_key'];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_USERAGENT, 'api');
  curl_setopt($ch, CURLOPT_TIMEOUT, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
  curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
  curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);


  $response = curl_exec($ch);

  return true;
}

function send_data_get($url, $data)
{
  $api = unserialize(API);
  $url = $url . '?secret_key=' . $api['secret_key'] . '&data=' . urlencode(serialize($data));

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, 0);
  // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_USERAGENT, 'api');
  curl_setopt($ch, CURLOPT_TIMEOUT, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
  curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

  $response = curl_exec($ch);

  return true;
}

function get_task_free_user_id()
{
  require_once __DIR__ . '/system_settings.php'; //TASK USERS
  $tasks_file  = __DIR__ . '/logs/system/users_tasks.json';
  $users_tasks = TASK_USERS;

  if (is_file($tasks_file)) {
    $loaded_tasks = json_decode(file_get_contents($tasks_file), true);

    if (!empty($loaded_tasks)) {
      foreach ($loaded_tasks as $key => $value) {
        $users_tasks[$key] = $value;
      }
    }
  } else {
    file_put_contents($tasks_file, json_encode($users_tasks));
  }

  $disabled_users_file = __DIR__ . '/logs/system/users_tasks_disabled.json';
  if (is_file($disabled_users_file)) {
    $disabled_users = json_decode(file_get_contents($disabled_users_file), true);

    foreach ($disabled_users as $user_id => $val) {
      if (isset($users_tasks[$user_id])) {
        unset($users_tasks[$user_id]);
      }
    }
  }

  $min_tasks_num = 9999;
  $free_user_id  = 0;
  foreach ($users_tasks as $user_id => $date_ar) {
    $cur_date = date('Y-m-d');
    if (isset($date_ar[$cur_date])) {
      $tasks_count = count($date_ar[$cur_date]);
      logw($user_id . ' cnt ' . $tasks_count);

      if ($tasks_count < $min_tasks_num) {
        $min_tasks_num = $tasks_count;
        $free_user_id  = $user_id;
      }
    } else {
      $min_tasks_num = 0;
      $free_user_id  = $user_id;
    }

  }

  if (isset($free_user_id) && $free_user_id > 0) {
    return $free_user_id;
  } else {
    return false;
  }

}

function mb_strcasecmp($str1, $str2, $encoding = null)
{
  if (null === $encoding) {$encoding = mb_internal_encoding();}
  return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
}

function mb_ucfirst($string, $enc = 'UTF-8')
{
  return mb_strtoupper(mb_substr($string, 0, 1, $enc), $enc) .
  mb_substr($string, 1, mb_strlen($string, $enc), $enc);
}

function array_search_full($array, $needle)
{
  $found = array_filter($array,
    function ($value) use ($needle) {
      if (is_array($value)) {
        $result = array_search_full($value, $needle);
        if ($result) {
          return true;
        }

      } elseif (mb_stristr($value, $needle)) {
        return true;
      }

      return false;
    }
  );

  if (!empty($found)) {
    return $found;
  } else {
    return false;
  }

}

function get_last_digits($str, $num)
{
  preg_replace('/[^0-9]+/', '', $str);
  return substr($str, -$num);
}

function format_phone7($phone, $for_search = false)
{

  $phone = preg_replace("/[^0-9+]/", "", $phone);

  if (!empty($phone) && strlen($phone) > 5) {
    $phone_start = substr($phone, 0, 1);

    switch ($phone_start) {
      case 9:
        if (strlen($phone) == 10) {
          $formatted_phone = '7' . $phone;
        }

        break;
      case 8:
        if (strlen($phone) == 11) {
          $formatted_phone = '7' . substr($phone, 1);
        }

        break;
      case 7:
        if (strlen($phone) == 11) {
          $formatted_phone = ($phone);
        }

        break;

        break;
      case '+':
        if (strlen($phone) == 12 && substr($phone, 1, 1) == 7) {
          $formatted_phone = '7' . substr($phone, 2);
        }
        break;

      default:
        if (strlen($phone) == 10) {
          $formatted_phone = '7' . $phone;
        }
    }

    if (!empty($formatted_phone)) {
      if ($for_search) {
        if (substr($formatted_phone, 0, 2) == '+7') {
          $trim_num = 2;
        } elseif (substr($formatted_phone, 0, 1) == 8) {
          $trim_num = 1;
        } elseif (substr($formatted_phone, 0, 1) == 7) {
          $trim_num = 1;
        } else {
          $trim_num = 0;
        }
        return substr($formatted_phone, $trim_num);
      } else {
        return $formatted_phone;
      }
    }
  }

  return $phone;
}


function format_phone8($phone, $for_search = false)
{

  $phone = preg_replace("/[^0-9+]/", "", $phone);

  if (!empty($phone) && strlen($phone) > 5) {
    $phone_start = substr($phone, 0, 1);

    switch ($phone_start) {
      case 9:
        if (strlen($phone) == 10) {
          $formatted_phone = '8' . $phone;
        }

        break;
      case 8:
        if (strlen($phone) == 11) {
          $formatted_phone = $phone;
        }

        break;
      case 7:
        if (strlen($phone) == 11) {
          $formatted_phone = '8' . substr($phone, 1);
        }

        break;

        break;
      case '+':
        if (strlen($phone) == 12 && substr($phone, 1, 1) == 7) {
          $formatted_phone = '8' . substr($phone, 2);
        }
        break;

      default:
        if (strlen($phone) == 10) {
          $formatted_phone = '8' . $phone;
        }
    }

    if (!empty($formatted_phone)) {
      if ($for_search) {
        if (substr($formatted_phone, 0, 2) == '+7') {
          $trim_num = 2;
        } else if (substr($formatted_phone, 0, 1) == 8) {
          $trim_num = 1;
        } else {
          $trim_num = 0;
        }
        return substr($formatted_phone, $trim_num);
      } else {
        return $formatted_phone;
      }
    }
  }

  return $phone;
}

function format_phone($phone, $for_search = false)
{

  $phone = preg_replace("/[^0-9+]/", "", $phone);

  if (!empty($phone) && strlen($phone) > 5) {
    $phone_start = substr($phone, 0, 1);

    switch ($phone_start) {
      case 9:
        if (strlen($phone) == 10) {
          $formatted_phone = '+7' . $phone;
        }

        break;
      case 8:
        if (strlen($phone) == 11) {
          $formatted_phone = '+7' . substr($phone, 1);
        }

        break;
      case 7:
        if (strlen($phone) == 11) {
          $formatted_phone = '+' . $phone;
        }

        break;
      case 3:
        if (strlen($phone) == 12) {
          $formatted_phone = '+' . $phone;
        }

        break;
      case '+':
        if ((strlen($phone) == 12 && substr($phone, 1, 1) == 7) ||
          (strlen($phone) == 13 && substr($phone, 1, 1) == 3)) {
          $formatted_phone = $phone;
        }
        break;

      default:
        if (strlen($phone) == 10) {
          $formatted_phone = '+7' . $phone;
        }
    }

    if (!empty($formatted_phone)) {
      if ($for_search) {
        if (substr($formatted_phone, 0, 2) == '+7') {
          $trim_num = 2;
        } else if (substr($formatted_phone, 0, 1) == 8) {
          $trim_num = 1;
        } else {
          $trim_num = 0;
        }
        return substr($formatted_phone, $trim_num);
      } else {
        return $formatted_phone;
      }
    }

  }

  return $phone;
}

function get_phone_from_name($name, $last_try = false)
{
  $phones = array();
  // debug('0. '.$name);

  $name = str_replace(array('-', '(', ')', '+'), ' ', $name);
  // debug('1. '.$name);

  $phone = preg_replace('/[^\d\s]+/u', ' ', $name);
  // debug('2. '.$phone);

//Ukr & Belarus number
  preg_match_all('/\d{12}/', $phone, $matches);
  foreach ($matches[0] as $key => $number) {
    // debug($number);
    $formatted_phone = format_phone($number);
    if ($formatted_phone) {
      $phones[] = $formatted_phone;
    }
  }

  preg_match_all('/\d{12}\b/', $phone, $matches);
  foreach ($matches[0] as $key => $number) {
    $formatted_phone = format_phone($number);
    if ($formatted_phone) {
      $phones[] = $formatted_phone;
    }
  }

  preg_match_all('/\d{11}/', $phone, $matches);
  foreach ($matches[0] as $key => $number) {
    $formatted_phone = format_phone($number);
    if ($formatted_phone) {
      $phones[] = $formatted_phone;
    }
  }

  preg_match_all('/\d{10}/', $phone, $matches);
  foreach ($matches[0] as $key => $number) {
    $formatted_phone = format_phone($number);
    if ($formatted_phone) {
      $phones[] = $formatted_phone;
    }
  }

  preg_match_all('/\d{10}\b/', $phone, $matches);
  foreach ($matches[0] as $key => $number) {
    $formatted_phone = format_phone($number);
    if ($formatted_phone) {
      $phones[] = $formatted_phone;
    }
  }

  if (empty($phones)) {
    $phone = str_replace(' ', '', $phone);

    if (!$last_try) {
      $phones = get_phone_from_name($phone, true);
    }
  }

  if (!empty($phones)) {
    $phones = array_unique($phones);
    return $phones;
  }

  return false;
}

function log_init()
{
  $dir = __DIR__ . '/logs/';
  if (!is_dir($dir)) {
    mkdir($dir);
  }

  foreach (['system', 'errors', 'sync', 'sync/web', 'sync/artmark', 'hooks', 'widgets', 'tasks', 'other'] as $log_dir) {
    if (!is_dir($dir . $log_dir)) {
      mkdir($dir . $log_dir);
    }
  }

  $file_name = date("d-m-y", time());
  if (is_file($dir . $file_name)) {
    $fd = fopen($dir . $file_name, "a");
    fwrite($fd, "\n");
    fclose($fd);
  }
}

function logw($input, $file_name = null, $settings = array())
{
  $logs_dir = __DIR__ . '/logs/';
  $settings = [
    'to_screen' => true,
    'save_logs' => true,
  ];

  if (is_file(__DIR__ . '/system_settings.php')) {
    include __DIR__ . '/system_settings.php';
    $settings = array_merge($settings, $log_settings);
  }

  if (is_array($input)) {
    $msg = print_r($input, true);
  } else {
    $msg = $input;
  }

  if (!isset($file_name)) {
    $file_name = date("m-y", time()) . '.log';
  }

  $msg_start = date("d.m H:i:s", time());
  $msg_start .= ' ';

  $backtrace = debug_backtrace();
  if (!empty($backtrace[1]['function'])) {
    $msg_end .= ' [' . $backtrace[1]['function'] . ']';
  }

  $msg = $msg_start . $msg . $msg_end . "\n";

/*  if (isset($file_name)) {
$fd = fopen($logs_dir . $file_name, "a");
}

if ($settings['save_logs']) {
fwrite($fd, $msg);
fclose($fd);
}*/

  // $fd  = fopen($logs_dir . date('m-y') . '.log', "a");

  if ($settings['save_logs']) {
    $fd = fopen($logs_dir . $file_name, "a");
    fwrite($fd, $msg);
    fclose($fd);
  }

  if ($settings['web']) {
    $msg = '<pre>' . $msg . '</pre>';
  }

  if ($settings['to_screen']) {
    echo $msg;
  }

  return true;
}

function debug($data = null)
{
  if (!empty($data)) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    echo PHP_EOL;
  }

  return true;
}

function format_url($url)
{
  $url = preg_replace('~[^\\pL0-9_]+~u', '-', $url);
  $url = trim($url, "-");
  $url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
  $url = strtolower($url);
  $url = preg_replace('~[^-a-z0-9_]+~', '', $url);
  return $url;
}
