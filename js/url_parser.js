//UTM Parser for AmoCRM
var utm_forms = [];
var utm_list = ['utm_source', 'utm_keyword', 'utm_campaign', 'utm_medium', 'utm_term', 'utm_content'];
var inputs_data = {
  url: location.protocol + '//' + location.host + location.pathname
};

//Парсим ссылку на наличие UTM данных
function get_params_by_name(name) {
  name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
  var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
    results = regex.exec(location.search);
  return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

//Добавляем скрытые инпуты с UTM данными к формам
function add_hidden_inputs(form) {
  for (var key in inputs_data) {
    var value = inputs_data[key];
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    form.appendChild(input);
  }

  utm_forms.push(form);
}

//Получаем UTM
utm_list.forEach(
  function(value, key) {
    var utm = get_params_by_name(value);
    if (utm.length > 0) {
      inputs_data[value] = utm;
    }
  }
);

//Сравниваем новые UTM данные с сохранёнными, если есть новые то чистим Storage
for (var key in inputs_data) {
  if (utm_list.indexOf(key) >= 0) {
    var value = inputs_data[key];
    var saved_utm = sessionStorage.getItem(key);
    if (saved_utm != value) {
      // console.log('Новый запрос, чистим UTM');
      utm_list.forEach(
        function(value, key) {
          sessionStorage.removeItem(value);
        }
      );
      break;
    }
  }
}

//Сохраняем UTM данные в storage
for (var key in inputs_data) {
  sessionStorage.setItem(key, inputs_data[key]);
  // console.log('Сохраняем UTM ' + key, inputs_data[key]);
}

//Загружаем UTM данные из storage
utm_list.forEach(
  function(value, key) {
    saved_utm = sessionStorage.getItem(value);
    if (saved_utm && saved_utm.length > 0) {
      inputs_data[value] = saved_utm;
      // console.log('Загрузили UTM ' + value, inputs_data[value]);
    }
  }
);

//Добавляем при клике на submit
/*document.addEventListener('submit', function(e) {
  add_hidden_inputs(e.target);
});*/

//Добавляем ко всем формам
var list = document.forms;
for (let item of list) {
  add_hidden_inputs(item);
}

console.log('UTM added to forms');
console.log(utm_forms);

//Добавляем скрытые инпуты с UTM данными к формам AmoCRM
// add_hidden_inputs(document.getElementById('form_callback'));


/*for (var key in inputs_data) {
  form.append('<input type="hidden" name="' + key + '"value="' + inputs_data[key] + '">');
}*/