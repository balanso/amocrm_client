<?
require_once __DIR__ . '/system_settings.php';

function auth()
{
  $cookie_file = __DIR__ . '/logs/system/cookie.log';
  if (is_file($cookie_file) && filemtime($cookie_file) + 60 * 5 > time()) {
    return true;
  }

  $api  = unserialize(API);
  $user = array(
    'USER_LOGIN' => $api['login'],
    'USER_HASH'  => $api['api_key'],
  );

  $link = 'https://' . $api['domain'] . '.amocrm.ru/private/api/auth.php?type=json';
  $curl = curl_init();

  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
  curl_setopt($curl, CURLOPT_URL, $link);
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($user));
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
  curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
  $out  = curl_exec($curl);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  $code   = (int) $code;
  $errors = array(
    301 => 'Moved permanently',
    400 => 'Bad request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not found',
    500 => 'Internal server error',
    502 => 'Bad gateway',
    503 => 'Service unavailable',
  );
  try
  {
    if ($code != 200 && $code != 204) {
      throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
    }

  } catch (Exception $E) {
    die('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode());
  }

  $Response = json_decode($out, true);
  $Response = $Response['response'];

  if (isset($Response['auth'])) {
    return true;

  } else {
    logw('Ошибка: авторизация не удалась');
    return false;
  }
}

/**
 * @param $link
 * @param array $data
 * @param $sleep_time
 */
function run_curl($link, $data = array(), $sleep_time = 1)
{
  auth();

  $lock_file_path = __DIR__ . '/logs/system/curl.lock';
  $lock_file      = fopen($lock_file_path, 'a+');
  flock($lock_file, LOCK_EX, $wouldblock);
  $last_curl_time = fread($lock_file, filesize($lock_file_path) + 1);

  if (!empty($last_curl_time) && $last_curl_time >= time()) {
    sleep($sleep_time);
  }

  $curl = curl_init();

  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_USERAGENT, "amoCRM-API-client-undefined/2.0");
  curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: application/json"));

  if (!empty($data)) {
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
  }

  $api = unserialize(API);
  if (stristr($link, 'http')) {
    curl_setopt($curl, CURLOPT_URL, $link);
  } else {
    curl_setopt($curl, CURLOPT_URL, 'https://' . $api['domain'] . '.amocrm.ru/api/v2/' . $link);
  }

  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_COOKIEFILE, __DIR__ . '/logs/system/cookie.log');
  curl_setopt($curl, CURLOPT_COOKIEJAR, __DIR__ . '/logs/system/cookie.log');

  logw('Запуск cURL ' . $link);
  ftruncate($lock_file, 0);
  fwrite($lock_file, time() + 1);
  flock($lock_file, LOCK_UN, $wouldblock);
  fclose($lock_file);

  $out = curl_exec($curl);
  curl_close($curl);

  return json_decode($out, true);
}

//[fields & custom_fields]
/**
 * @param $entity
 * @param $items
 * @param $search_similiar
 * @return mixed
 */
function prepare_data($entity, $items, $search_similiar = false)
{

  if (substr($entity, -1) == 's') {
    $return_all = true;
  } else {
    $return_all = false;
    if ($entity == 'company') {
      $entity = 'companies';
    } else {
      $entity = $entity . 's';
    }
    $items = [$items];
  }

  $prepared_items = array();
  foreach ($items as $key => $data) {

    $item        = array();
    $found_items = array();

    if (isset($data['name'])) {
      $item['name'] = $data['name'];
    }

    //Делаем массивами данные, если пришли не как массив
    $array_field_names = ['phone', 'email', 'tags'];
    foreach ($array_field_names as $key => $field_name) {
      if (isset($data[$field_name]) && !is_array($data[$field_name])) {
        $data[$field_name] = array($data[$field_name]);
      }
    }

    // Ищем по ID
    if (isset($data['id'])) {
      $found_items = amo_get($entity, array('id' => $data['id']))[0];

      if (empty($found_items)) {
        logw($entity . ' id ' . $data['id'] . ' не найден');
        return false;
      }
    } elseif ($search_similiar) {
      if (!empty($data['search'])) {
        if (is_array($data['search'])) {
          $search_query = implode(' ', $data['search']);
        } else {
          $search_query = $data['search'];
        }

        if (!empty($search_query)) {
          // logw('Ищем похожие ' . $entity . ' по запросу ' . $search_query);
          $found_items = amo_get($entity, $search_query)[0];
        }
      }
    }

    //Есть идентичную сущность
    if ($found_items) {
      $item['id'] = $found_items['id'];
      logw('Найден существующий ' . $entity . ' id ' . $found_items['id']);

      //Сравним есть ли отличающиеся данные, если нет то обновлять не нужно.
      $data_amo  = get_compare_fields_data($found_items);
      $data_this = get_compare_fields_data($data);

      if ($data_this['phone'] && $data_amo['phone'] && !is_array($data_amo['phone'])) {
        $data_amo['phone'] = array($data_amo['phone']);
      }

      $has_new_data = false;
      foreach ($data_this as $key => $value) {
        if ($value != $data_amo[$key]) {

          if (is_array($value) && is_array($data_amo[$key])) {
            $new_values = array_diff($value, $data_amo[$key]);

            if (empty($new_values)) {
              continue;
            }
          }

          $has_new_data = true;
          break;
        }
      }

      if (!$has_new_data) {
        $item['no_update'] = true;
        logw('Не обновляем, данные идентичны, id ' . $item['id']);

        if ($return_all) {
          return [$item];
        } else {
          return $item;
        }
      }

      //Добавляем теги из амо
      if (!empty($found_items['tags'])) {
        foreach ($found_items['tags'] as $tag) {
          $data['tags'][] = $tag['name'];
        }
      }

      //Добавляем значения полей из амо в наши
      foreach (['phone', 'email'] as $field_name) {
        if (!empty($data[$field_name]) && !empty($found_items)) {
          $amo_data = get_field_data($field_name, $found_items);
          if ($amo_data) {
            foreach ($amo_data as $value) {
              $data[$field_name][] = $value;
            }
          }

          $data[$field_name] = array_unique($data[$field_name]);
        }
      }
    }

    //Строим массив custom_fields
    $field_id = unserialize(FIELD_ID);
    foreach (['phone', 'email'] as $field_name) {
      if (!empty($data[$field_name])) {
        foreach ($data[$field_name] as $value) {
          if (!empty($value)) {
            $item['custom_fields'][] = build_custom_field_data($field_id[$field_name], $value, 'WORK');
          }
          unset($data[$field_name]);
        }
      }
    }

    foreach ($data as $field_name => $field_val) {
      $field_name = mb_strtolower($field_name);
      if (isset($field_id[$field_name])) {
        $item['custom_fields'][] = build_custom_field_data($field_id[$field_name], $data[$field_name]);
      } elseif (is_numeric($field_name)) {
        $item['custom_fields'][] = build_custom_field_data($field_name, $data[$field_name]);
      }
    }

    if (is_array($data['tags']) && isset($data['tags'][0]['id'])) {
      foreach ($data['tags'] as $key => $tag) {
        $data['tags'][$key] = $tag['name'];
      }
    }

    if (!empty($item['add_tag'])) {
      foreach ($item['add_tag'] as $tag) {
        $data['tags'][] = $tag;
      }
    }

    if (!empty($data['tags'])) {
      $data['tags'] = array_unique($data['tags']);
      $item['tags'] = implode(',', $data['tags']);
    }

    switch ($entity) {
      case 'contacts':
        $fields = ['name', 'created_at', 'updated_at', 'responsible_user_id', 'created_by', 'company_name', 'tags', 'leads_id', 'customers_id', 'company_id'];
        break;

      case 'companies':
        $fields = ['name', 'created_at', 'updated_at', 'responsible_user_id', 'created_by', 'tags', 'leads_id', 'customers_id', 'contacts_id'];
        break;

      case 'leads':
        $fields = ['created_at', 'updated_at', 'status_id', 'pipeline_id', 'responsible_user_id', 'sale', 'tags', 'contacts_id', 'company_id'];
        break;

      case 'tasks':
        $fields = ['element_id', 'element_type', 'complete_till_at', 'task_type', 'text', 'created_at', 'updated_at', 'is_completed', 'created_by', 'id', 'updated_at', 'responsible_user_id'];
        break;

      case 'notes':
        $fields = ['element_id', 'element_type', 'text', 'note_type', 'created_at', 'updated_at', 'responsible_user_id', 'params', 'id', 'updated_at'];
        break;

      default:
        break;
    }

    foreach ($fields as $field_name) {
      if (isset($data[$field_name])) {
        $item[$field_name] = $data[$field_name];
      }
    }

    $msg = $entity . ' ';

    if (!empty($item['id'])) {
      $msg .= 'id ' . $item['id'] . ' ';
    }

    $msg .= 'подготовлен: ';
    $item_fields = get_all_fields_data($item);

    foreach ($item_fields as $key => $value) {
      $fields_text[] = $key . '=' . $value;
    }

    $msg .= implode(';', $fields_text);
    logw($msg);
    $prepared_items[] = $item;
  }

  if ($return_all) {
    return $prepared_items;
  } else {
    return $prepared_items[0];
  }
}

// Ищем похожие контакты
/**
 * @param $entity
 * @param $search_values
 * @return mixed
 */
function amo_get_similiar($entity, $search_values)
{
  $similiar_contacts = array();

  if (!is_array($search_values)) {
    $search_values = [$search_values];
  }

  foreach ($search_values as $value) {
    $found_contacts = amo_get($entity, array('query' => $value));
    if ($found_contacts) {
      foreach ($found_contacts as $k => $v) {
        $similiar_contacts[] = $v;
      }
    }
  }

  return $similiar_contacts;
}

//$search_similiar = false, $need_prepare = true;
/**
 * @param $entity
 * @param $data
 * @param $search_similiar
 * @param false $need_prepare
 * @return mixed
 */
function amo_update($entity, $data, $search_similiar = false, $need_prepare = true)
{
  if (substr($entity, -1) == 's') {
    $link       = $entity;
    $many_items = true;
  } else {
    if ($entity == 'company') {
      $link = 'companies';
    } else {
      $link = $entity . 's';
    }
  }

  if (isset($data['search']) && !empty($data['search'])) {
    $search_similiar = true;
  }

  if ($need_prepare) {
    $item = prepare_data($entity, $data, $search_similiar);
  } else {
    $item = $data;
  }

  if ($item) {
    if (!$many_items) {
      $items = [$item];
    } else {
      $items = $item;
    }

    foreach ($items as $key => $item) {
      if (isset($item['id'])) {

        // no_update ставится в случае когда данные контакта совпадают с данными в амо.
        if ($item['no_update']) {
          return $item['id'];
        }

        $action_msg              = 'Обновлён ';
        $ids_to_update[]         = $item['id'];
        $item['updated_at']      = time(); // + 1000
        $sorted_data['update'][] = $item;

      } else {
        $ids_to_add[]         = $item['id'];
        $action_msg           = 'Добавлен ';
        $sorted_data['add'][] = $item;
      }
    }

/*    if (!empty($ids_to_update)) {
logw('Обновляем ' . $entity . ' id ' . implode(', ', $ids_to_update));
}

if (!empty($ids_to_add)) {
logw('Добавляем ' . $entity);
}*/

    $output = run_curl($link, $sorted_data);

    if (!empty($output['_embedded']['items'])) {
      foreach ($output['_embedded']['items'] as $key => $value) {
        $updated_id[] = $value['id'];
      }
    } else {
      logw('Ошибка обновления');
      logw($output);
      return false;
    }

    if (!empty($updated_id)) {
      $updated_id_msg = implode(', ', $updated_id);
    } else {
      logw('Ошибка обновления ' . $entity);
      logw($output);
      return false;
    }

    if ($many_items) {
      logw('Обновлены ' . $entity . ' id ' . $updated_id_msg);
      return $updated_id;
    } else {
      logw($action_msg . $entity . ' id ' . $updated_id_msg);
      return $updated_id[0];
    }

  } else {
    logw('Ошибка: не получены данные от prepare_data');
    return false;
  }
}

//contacts | leads | companies | task, [id | query, limit_rows, limit_offset]
/**
 * @param $entity
 * @param array $data
 * @return mixed
 */
function amo_get($entity, $data = [])
{
  if (substr($entity, -1) == 's') {
    $return_all = true;
  } else {
    $return_all = false;
    if ($entity == 'company') {
      $entity = 'companies';
    } elseif ($entity != 'account') {
      $entity .= 's';
    }
  }

  $link = $entity . '?';

  if (is_array($data) && !empty($data)) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $v) {
          $link .= '&' . $key . '[]=' . $v;
        }
      } else {
        $link .= '&' . $key . '=' . $value;
      }

    }
  } elseif (empty($data)) {
    $link .= '';
  } else {
    $link .= 'query=' . $data;
  }

  $result = run_curl($link);

  if (in_array($entity, ['contacts', 'leads', 'companies', 'tasks', 'catalog_elements'])) {
    $items = $result['_embedded']['items'];
  } else {
    return $result;
  }

  if (!empty($items)) {
    $count = count($items);

    if ($count == 500) {
      if (!is_array($data)) {
        $data = ['query' => $data];
      }

      $data['limit_rows'] = 500;
      $data['limit_offset'] += 500;
      $recieved_items = amo_get($entity, $data);

      if (!empty($recieved_items)) {
        foreach ($recieved_items as $item) {
          $items[] = $item;
        }
      }
    }

    logw('Найдено ' . $entity . ': ' . count($items));
    if ($return_all) {
      return $items;
    } else {
      return $items[0];
    }

  } else {
    logw($entity . ' не найдено');
    return array();
  }
}

//Получаем данные поля $field_name = name or id
/**
 * @param $field_name
 * @param array $contact
 * @return mixed
 */
function get_field_data($field_name, array $contact)
{
  if (is_numeric($field_name)) {
    $field_id = $field_name;
  } else {
    $field_id_arr = unserialize(FIELD_ID);
    if (isset($field_id_arr[$field_name])) {
      $field_id = $field_id_arr[$field_name];
    } else {
      return false;
    }
  }

  if (isset($contact['custom_fields']) && !empty($field_id)) {
    $out = array();

    foreach ($contact['custom_fields'] as $key => $value) {
      if ($value['id'] == $field_id) {
        foreach ($value['values'] as $key => $data) {
          $out[] = $data['value'];
        }
      }
    }
  }

  if (!empty($out)) {
    return $out;
  } else {
    return false;
  }
}

//get data from contact array custom fields
/**
 * @param $field_id
 * @param $contact
 * @return mixed
 */
function get_field_data_by_id($field_id, $contact)
{
  if (isset($contact['custom_fields'])) {
    $out = array();

    foreach ($contact['custom_fields'] as $key => $value) {
      if ($value['id'] == $field_id) {
        foreach ($value['values'] as $key => $data) {
          $out[] = $data['value'];
        }
      }
    }
  }

  if (!empty($out)) {
    return $out;
  } else {
    return false;
  }
}

// Распарсиваем массив контакта или сделки, вытаскиваем все поля в корневой массив.
/**
 * @param $contact
 * @return mixed
 */
function get_all_fields_data($contact)
{
  $field_id = unserialize(FIELD_ID);
  if ($contact['custom_fields']) {
    $field_names = array_flip($field_id);

    foreach ($contact['custom_fields'] as $key => $value) {
      if (isset($field_names[$value['id']])) {
        $field_name = $field_names[$value['id']];

        foreach ($value['values'] as $data) {
          $contact[$field_name]   = [];
          $contact[$field_name][] = $data['value'];
        }

        $val_count = count($contact[$field_name]);

        if ($val_count == 1) {
          if (isset($contact[$field_name][0])) {
            $contact[$field_name] = $contact[$field_name][0];
          }
        }

      }
    }
    unset($contact['custom_fields']);
  }

  foreach ($contact as $key => $value) {
    if (is_array($value)) {
      $contact[$key] = implode(', ', $value);
    }
  }

  return $contact;
}

// Распарсиваем массив контакта или сделки, вытаскиваем все поля в корневой массив.
/**
 * @param $contact
 * @return mixed
 */
function get_compare_fields_data($contact)
{
  $field_id = unserialize(FIELD_ID);

  if (!empty($contact['name'])) {
    $out['name'] = $contact['name'];
  }

  if ($contact['custom_fields']) {
    foreach ($contact['custom_fields'] as $key => $value) {
      $field_names = array_flip($field_id);
      if (!empty($field_names[$value['id']])) {
        $out[$field_names[$value['id']]] = $value['values'][0]['value'];
      }
    }

    if (!empty($contact['tags'])) {
      foreach ($contact['tags'] as $key => $value) {
        $out['tags'][] = $value['name'];
      }
    }
  } else {
    foreach ($contact as $key => $value) {
      if (!empty($field_id[$key])) {
        $out[$key] = $value;
      }
    }

    if (!empty($contact['tags'])) {
      foreach ($contact['tags'] as $key => $value) {
        $out['tags'][] = $value;
      }
    }

    if (!empty($contact['status_id'])) {
      $out['status_id'] = $contact['status_id'];
    }
  }

  foreach (['phone', 'email'] as $key => $value) {
    if (!empty($out[$value]) && !is_array($out[$value])) {
      $out[$value] = [$out[$value]];
    }
  }

  return $out;
}

//get data from contact array custom fields
/**
 * @param $field_id
 * @param array $contact
 * @return mixed
 */
function remove_custom_field_data($field_id, array $contact)
{
  if (isset($contact['custom_fields'])) {
    foreach ($contact['custom_fields'] as $key => $value) {
      if ($value['id'] == $field_id) {
        unset($contact['custom_fields'][$key]);
      }
    }
  }

  return $contact;
}

//get data from contact array custom fields
/**
 * @param $field_id
 * @param $value
 * @param $enum
 * @return mixed
 */
function build_custom_field_data($field_id, $value, $enum = null)
{

  $data = array(
    'id' => $field_id,
  );

  if (is_array($value)) {
    if (isset($value[0]['subtype']) && isset($value[0]['value'])) {
      foreach ($value as $key => $value) {
        $data['values'][$key]['value']   = $value['value'];
        $data['values'][$key]['subtype'] = $value['subtype'];
      }
    } else {
      $data['values'] = $value;
    }
  } else {
    $data['values'] = array(array('value' => $value));
  }

  if ($enum) {
    $data['values'][0]['enum'] = $enum;
  }

  return $data;
}

//$lead = array(id, etc..)
/**
 * @param $id
 * @param array $fields
 * @param array $custom_fields
 * @return mixed
 */
function copy_lead_by_id($id, $fields = array(), $custom_fields = array())
{
  $lead = amo_get('lead', ['id' => $id]);
  // logw($lead);

  if ($lead) {
    unset($lead['id']);

    if (isset($lead['loss_reason_id'])) {
      unset($lead['loss_reason_id']);
    }

    $field_id = unserialize(FIELD_ID);
    if (!empty($custom_fields)) {
      foreach ($custom_fields as $key => $value) {
        if (isset($field_id[$key])) {
          $lead                    = remove_custom_field_data($field_id[$key], $lead);
          $lead['custom_fields'][] = build_custom_field_data($field_id[$key], $value);
        }
      }
    }

    foreach ($lead['custom_fields'] as $k => $data) {
      if (isset($data['values'])) {
        foreach ($data['values'] as $k1 => $values) {
          if (isset($values['value']) && is_string($values['value'])) {
            //Amo отдаёт данные кодированные в htmlspecialchars
            $lead['custom_fields'][$k]['values'][$k1]['value'] = htmlspecialchars_decode($values['value']);
          }

        }
      }
    }

    if (isset($lead['tags'])) {
      foreach ($lead['tags'] as $key => $value) {
        $tags[] = $value['name'];
      }
      $lead['tags'] = implode(',', $tags);
    }

    if (!empty($fields)) {
      foreach ($fields as $key => $value) {
        $lead[$key] = $value;
      }
    }

    $updated_id = amo_update('lead', $lead, false, false);

    if ($updated_id) {
      logw('Сделка скопирована, новый id ' . $updated_id);
      return $updated_id;
    } else {
      logw('Ошибка: сделка не скопирована');
      logw($updated_id);
      return false;
    }
  } else {
    logw('Сделка с id ' . $id . ' не найдена');
    return false;
  }
}

//$data = [lead, contact, lead_info]
/**
 * @param $data
 * @return mixed
 */
function add_unsorted_form($data)
{
  $prepared_data = [
    'source_uid'         => time() . rand(1, 100),
    'created_at'         => time(),
    'source_name'        => 'form',
    'incoming_entities'  => array(),
    'incoming_lead_info' => array(
      'form_id'      => 'template form_id',
      'form_page'    => !empty($data['lead']['name']) ? $data['lead']['name'] : 'Без названия',
      'ip'           => 'template ip',
      'service_code' => 'template code',
    ),
  ];

  if (isset($data['lead'])) {
    $lead = prepare_data('lead', $data['lead'], false);
    if ($lead) {
      if (!empty($data['lead']['notes'])) {
        $lead['notes'] = $data['lead']['notes'];
      }

      $prepared_data['incoming_entities']['leads'][] = $lead;
    }
  }

  if (isset($data['contact'])) {
    $contact = prepare_data('contact', $data['contact'], false);

    if ($contact) {
      if (!empty($data['contact']['notes'])) {
        $contact['notes'] = $data['contact']['notes'];
      }

      $prepared_data['incoming_entities']['contacts'][] = $contact;
    }
  }

  if (isset($data['lead_info'])) {
    foreach ($data['lead_info'] as $key => $value) {
      $prepared_data['incoming_lead_info'][$key] = $value;
    }
  }

  if (isset($data['pipeline_id']) || $data['lead']['pipeline_id']) {
    $prepared_data['pipeline_id'] = isset($data['pipeline_id']) ? $data['pipeline_id'] : $data['lead']['pipeline_id'];
  }

  $data = array(
    'add' => [$prepared_data],
  );

  $api  = unserialize(API);
  $link = 'incoming_leads/form?login=' . $api['login'] . '&api_key=' . $api['api_key'];
  // debug($data);
  $output = run_curl($link, $data);

  return $output;
}

/**
 * @param $group_id
 * @return mixed
 */
function get_next_user_id($group_id)
{
  $account = amo_get('account', ['with' => 'users']);

  if (is_file(__DIR__ . '/logs/system/last_group' . $group_id . '_user_id')) {
    $last_user = file_get_contents(__DIR__ . '/logs/system/last_group' . $group_id . '_user_id');
  }
  $use_next_user = false;
  $next_user_id  = 0;
  $i             = 0;

  if (!empty($account)) {

    while ($next_user_id == 0) {

      foreach ($account['_embedded']['users'] as $key => $user) {
        if ($user['group_id'] == $group_id) {
          if ($use_next_user) {
            $next_user_id = $user['id'];
            break;
          }

          if (!empty($last_user) && $last_user == $user['id'] && $use_next_user == false) {
            $use_next_user = true;
          } elseif (empty($last_user)) {
            $next_user_id = $user['id'];
          }
        }
      }

      $use_next_user = true;

      $i++;
      if ($i > 2) {
        break;
      }
    }

    if ($next_user_id != 0) {
      file_put_contents(__DIR__ . '/logs/system/last_group' . $group_id . '_user_id', $next_user_id);
      return $next_user_id;
    }
  }

  return false;

}

function generate_account_files()
{
  $data = amo_get('account', ['with' => 'custom_fields,pipelines,users,groups']);
  if (!empty($data)) {
    $contact_fields = $data['_embedded']['custom_fields']['contacts'];
    $lead_fields    = $data['_embedded']['custom_fields']['leads'];
    $fields_id      = [];

    foreach (array_merge($contact_fields, $lead_fields) as $key => $field) {
      $field_name = mb_strtolower($field['name']);
      if (!isset($field_id[$field_name])) {
        $fields_id[$field_name] = $field['id'];
      } else {
        $fields_id[$field_name . $field['id']] = $field['id'];
      }
    }

    if (!isset($fields_id['phone']) && isset($fields_id['телефон'])) {
      $fields_id['phone'] = $fields_id['телефон'];
      unset($fields_id['телефон']); //Совпадения ищутся по phone, "телефон" не подходит
    }

    $fields_name = array_flip($fields_id);

    foreach (['source', 'keyword', 'campaign', 'medium', 'term', 'content'] as $value) {
      if (!array_key_exists('utm_' . $value, $fields_id) && $key = array_search($value, $fields_name)) {
        $fields_id['utm_' . $value] = $key;
      } elseif (!array_key_exists('utm_' . $value, $fields_id)) {
        $fields_id['utm_' . $value] = 999;
      }
    }

    file_put_contents(AMO_FIELDS_ID_FILE, json_encode(array_reverse($fields_id)));
    file_put_contents(AMO_ACCOUNT_DATA_LOG, print_r($data, true));

    return true;
  }

  return false;
}

/**
 * @param array $contact
 * @param array $lead
 * @param string $note_text
 * @param bool $search_doubles
 */
function send_to_amo($contact, $lead, $note_text, $search_doubles = true)
{
  $json_input      = json_encode([$contact, $lead, $note]);
  $last_input_file = __DIR__ . '/logs/system/last_input.json';
  if (is_file($last_input_file) && file_get_contents($last_input_file) == $json_input) {
    die();
  } else {
    file_put_contents($last_input_file, $json_input, LOCK_EX);
  }

  if (!empty($contact) && !empty($lead)) {
    $formatted_phone  = format_phone($contact['phone']);
    $contact['phone'] = !empty($formatted_phone) ? $formatted_phone : $contact['phone'];
    $contact_id       = amo_update('contact', $contact);

    if ($contact_id) {
      $lead['contacts_id'] = [$contact_id];
      if ($search_doubles) {
        // Форматируем телефон под поиск без +7 и 8
        $formatted_phone = format_phone($contact['phone'], true);
        $search_query[]  = !empty($formatted_phone) ? $formatted_phone : $contact['phone'];
        $search_query[]  = !empty($contact['email']) ? $contact['email'] : null;
        $search_query    = implode(' ', $search_query);

        $exist_leads = amo_get('leads', ['query' => $search_query]);

        foreach ($exist_leads as $found_lead) {
          if ($found_lead['status_id'] != 143 && $found_lead['status_id'] != 142) {
            $lead_id = $found_lead['id'];

            $note_text = 'Повторное обращение' . "\nТеги: " . implode(', ', $lead['tags']) . "\n$note_text";

            break;
          }
        }
      }

      //Если не найдена сделка то добавляем новую
      if (!$lead_id) {
        $lead_id = amo_update('lead', $lead);
      }

      //Добавляем примечания
      if ($lead_id) {
        $add_task   = true;
        $lead_tasks = amo_get('tasks', ['element_id' => $lead_id]);
        if (!empty($lead_tasks)) {
          foreach ($lead_tasks as $key => $task) {
            if ($task['text'] == 'Новая заявка' && empty($task['is_completed'])) {
              $add_task = false;
              break;
            }
          }
        }

        if ($add_task) {
          amo_update('task', [
            'element_id'          => $lead_id,
            'element_type'        => 2,
            'task_type'           => 1,
            'responsible_user_id' => $lead_data['responsible_user_id'],
            'text'                => 'Новая заявка',
            'complete_till_at'    => '23:59',
          ]);
        }

        if (!empty($note_text)) {
          $note['text']         = $note_text;
          $note['element_id']   = $lead_id;
          $note['element_type'] = isset($note['element_type']) ? $note['element_type'] : 2;
          $note['note_type']    = isset($note['note_type']) ? $note['note_type'] : 4;
          $note_id              = amo_update('note', $note);
        }
      }

      return true;
    } else {
      logw('Не удалось получить id контакта');
    }
  } else {
    logw('Не получены данные сделки или контакта');
  }

  return false;
}
