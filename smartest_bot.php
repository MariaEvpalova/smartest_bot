<?php
/**
 * Полезный чат-бот c отчетами для bitrix24
 */
$appsConfig     = array();
$configFileName = '/config_' . trim(str_replace('.', '_', $_REQUEST['auth']['domain'])) . '.php';
if (file_exists(__DIR__ . $configFileName)) {
   include_once __DIR__ . $configFileName;
}

// receive event "new message for bot"
if ($_REQUEST['event'] == 'ONIMBOTMESSAGEADD') {
   // check the event - register this application or not
   if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
      return false;
   }
   // response time
   $arReport = getAnswer($_REQUEST['data']['PARAMS']['MESSAGE']);
   
   // send answer message
   $result = restCommand('imbot.message.add', 
      array(
         "DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
         "MESSAGE"   => $arReport,
      ), 
      $_REQUEST["auth"]);
   writeToLog($result, 'Message Add result'); // Log message add result
} // receive event "open private dialog with bot" or "join bot to group chat"
else {
   if ($_REQUEST['event'] == 'ONIMBOTJOINCHAT') {
      // check the event - register this application or not
      if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
         return false;
      }
      // send help message how to use chat-bot. For private chat and for group chat need send different instructions.
      $result = restCommand('imbot.message.add', array(
         'DIALOG_ID' => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
         'MESSAGE'   => 'Привет! Я Самый интеллектуальный чат-бот Битрикс24.',
      ), $_REQUEST["auth"]);
      writeToLog($result, 'Join Chat result'); // Log join chat result
   } // receive event "delete chat-bot"
   else {
      if ($_REQUEST['event'] == 'ONIMBOTDELETE') {
         // check the event - register this application or not
         if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
            return false;
         }
         // unset application variables
         unset($appsConfig[$_REQUEST['auth']['application_token']]);
         // save params
         saveParams($appsConfig);
      } // receive event "Application install"
      else {
         if ($_REQUEST['event'] == 'ONAPPINSTALL') {
            // handler for events
            $handlerBackUrl = 'https://lightly-legal-sunbeam.ngrok-free.app/smartest_bot.php';
            // register new bot
            $result = restCommand('imbot.register', array(
               'CODE'                  => 'SmartChatBot',
               'TYPE'                  => 'B',
               'EVENT_MESSAGE_ADD'     => $handlerBackUrl,
               'EVENT_WELCOME_MESSAGE' => $handlerBackUrl,
               'EVENT_BOT_DELETE'      => $handlerBackUrl,
               'PROPERTIES'            => array(
                  'NAME'              => 'Самый интеллектуальный чат-бот Битрикс24',
                  'LAST_NAME'         => '',
                  'COLOR'             => 'AQUA',
                  'EMAIL'             => 'no@mail.com',
                  'PERSONAL_BIRTHDAY' => '2016-03-23',
                  'WORK_POSITION'     => 'Самый интеллектуальный чат-бот',
                  'PERSONAL_GENDER'   => 'M',
               ),
            ), $_REQUEST["auth"]);
            writeToLog($result, 'Bot register result'); // Log registration result
            // save params
            if (isset($result['result'])) {
                $appsConfig[$_REQUEST['auth']['application_token']] = array(
                   'BOT_ID'      => $result['result'],
                   'LANGUAGE_ID' => $_REQUEST['data']['LANGUAGE_ID'],
                );
                saveParams($appsConfig);
            }
         }
      }
   }
}

/**
 * Save application configuration.
 *
 * @param $params
 *
 * @return bool
 */
function saveParams($params) {
   $config = "<?php\n";
   $config .= "\$appsConfig = " . var_export($params, true) . ";\n";
   $config .= "?>";
   $configFileName = '/config_' . trim(str_replace('.', '_', $_REQUEST['auth']['domain'])) . '.php';
   file_put_contents(__DIR__ . $configFileName, $config);
   return true;
}

/**
 * Send rest query to Bitrix24.
 *
 * @param       $method - Rest method, ex: methods
 * @param array $params - Method params, ex: array()
 * @param array $auth   - Authorize data, ex: array('domain' => 'https://test.bitrix24.com', 'access_token' => '7inpwszbuu8vnwr5jmabqa467rqur7u6')
 *
 * @return mixed
 */
function restCommand($method, array $params = array(), array $auth = array()) {
   $queryUrl  = 'https://' . $auth['domain'] . '/rest/' . $method;
   $queryData = http_build_query(array_merge($params, array('auth' => $auth['access_token'])));
   $curl = curl_init();
   curl_setopt_array($curl, array(
      CURLOPT_POST           => 1,
      CURLOPT_HEADER         => 0,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL            => $queryUrl,
      CURLOPT_POSTFIELDS     => $queryData,
   ));
   $result = curl_exec($curl);
   curl_close($curl);
   $result = json_decode($result, 1);
   writeToLog($result, 'Rest command result'); // Log REST command result
   return $result;
}

/**
 * Write data to log file.
 *
 * @param mixed  $data
 * @param string $title
 *
 * @return bool
 */
function writeToLog($data, $title = '') {
   $log = "\n------------------------\n";
   $log += date("Y.m.d G:i:s") + "\n";
   $log += (strlen($title) > 0 ? $title : 'DEBUG') + "\n";
   $log += print_r($data, 1);
   $log += "\n------------------------\n";
   file_put_contents(__DIR__ . '/imbot.log', $log, FILE_APPEND);
   return true;
}

/**
 * Формируем ответ по команде
 *
 * @param string $text строка, которую отправил юзер
 *
 * @return string
 */
function getAnswer($command = '') {
   $command = mb_strtolower($command);
   if (preg_match('/\b(здравствуйте|добрый день|привет)\b/ui', $command)) {
      return 'Здравствуйте';
   } elseif (preg_match('/\b(пока|до свидания)\b/ui', $command)) {
      return 'До свидания';
   } else {
      return 'Я не знаю';
   }
}
