<?
require_once $_SERVER['DOCUMENT_ROOT'] . '/amocrm/requires.php';

$api = unserialize(API);
if ($_GET['secret_key'] != $api['secret_key']) {
  die();
}
file_put_contents(__DIR__ . '/input', print_r($_REQUEST, true));

if (!empty($_POST)) {
  // $data = unserialize($_GET['data']);
  $data = $_POST;
}

//AmoCRM
error_reporting(E_ERROR);
ini_set('log_errors', true);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/amocrm/php_errors.log');

$lead = [
  'name' => 'Заявка с сайта',
  'tags' => [TAG],
  // 'pipeline_id' => 0,
  // 'responsible_user_id' => get_next_user_id(0),
];

$contact['name'] = NAME ? NAME : 'Посетитель сайта';
$phone           = PHONE ? PHONE : '';
$email           = EMAIL ? EMAIL : '';

if (!empty($phone)) {
  $contact['phone'] = $phone;
  $f_phone = format_phone($phone);

  if ($f_phone) {
    $contact['phone']    = $f_phone;
    $contact['search'][] = format_phone($f_phone, true);
  }
}

if (!empty($email)) {
  $contact['email']    = $email;
  $contact['search'][] = $contact['email'];
}

switch ($data['formid']) {
  case 'callback':
    $lead['tags'][] = 'заказ звонка';
    break;
  case 'order':
    $lead['tags'][] = 'заказ курса';
    break;
  case 'buy':
    $lead['tags'][] = 'купить курс';
    break;
  case 'review':
    $lead['tags'][] = 'оставить отзыв';
    break;
  case 'question':
    $lead['tags'][] = 'вопрос';
    break;

  default:
    break;
}

$note_text .= 'Форма: ' . implode(' ', $lead['tags']) . "\n";

if (!empty($data['product'])) {
  $note_text .= 'Продукт: ' . $data['product'] . "\n";
}

if (!empty($data['msg'])) {
  $note_text .= 'Сообщение: ' . $data['msg'] . "\n";
}

$note_text .= 'Контакт: ' . $contact['name'] . "\n";
$note_text .= !empty($contact['phone']) ? 'Телефон: ' . $contact['phone'] . "\n" : '';
$note_text .= !empty($contact['email']) ? 'Email: ' . $contact['email'] . "\n" : '';
$note_text .= !empty($data['url']) ? 'Ссылка: ' . $data['url'] . "\n" : '';

//$data utm to $lead_data
foreach (['utm_source', 'utm_keyword', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content'] as $key => $value) {
  if (isset($data[$value]) && !empty($data[$value])) {
    $lead[$value] = $data[$value];
    $note_text .= $value . ': ' . $lead[$value] . "\n";
  }
}

if (!empty($contact['phone']) || !empty($contact['email'])) {
  send_to_amo($contact, $lead, $note_text);
}
