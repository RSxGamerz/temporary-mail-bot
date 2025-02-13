<?php

/*
 * Temporary Mail Bot
 * By NimaH79
 * NimaH79.ir
 * @NimaH79
*/

// Only for Nginx + FastCGI (for better update handling)
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

define('BOT_TOKEN', '6555188467:AAGS3QMJuwnnF9b4-3Dre2PVzQ-_t0zsfI0');

function teleRequest($method, $parameters)
{
    foreach ($parameters as $key => &$val) {
        if (is_array($val)) {
            $val = json_encode($val);
        }
    }
    $ch = curl_init('https://api.telegram.org/bot'.BOT_TOKEN.'/'.$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function curl_get_contents($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function getMailDomains()
{
    $mail_domains = curl_get_contents('https://getnada.com/api/v1/domains');
    $mail_domains = json_decode($mail_domains, true);

    return $mail_domains;
}

function getInbox($address)
{
    $inbox = curl_get_contents('https://getnada.com/api/v1/inboxes/'.$address);
    $inbox = json_decode($inbox, true);

    return $inbox;
}

function getMessage($id)
{
    $message = curl_get_contents('https://getnada.com/api/v1/messages/'.$id);
    $message = json_decode($message, true);

    return $message;
}

function generateRandomString($length = 5)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $characters_length = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $characters_length - 1)];
    }

    return $randomString;
}

$update = file_get_contents('php://input');

if (!empty($update)) {
    $update = json_decode($update, true);
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $first_name = $message['from']['first_name'];
        if (isset($message['text'])) {
            $text = mb_strtolower($message['text']);
            if ($text == '/start') {
                teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'Hi, '.$first_name."! Press NEW ADDRESS to get new temporary e-mail address.\n\nBy @Radio_Nima", 'reply_markup' => ['keyboard' => [[['text' => 'NEW ADDRESS']]], 'resize_keyboard' => true]]);
            } elseif ($text == 'new address') {
                $mail_domains = getMailDomains();
                $mail_address = generateRandomString(rand(5, 6)).'@'.$mail_domains[array_rand($mail_domains)]['name'];
                teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'E-Mail: '.$mail_address, 'reply_markup' => ['inline_keyboard' => [[['text' => '📩 Inbox', 'callback_data' => $mail_address]]]]]);
            } elseif (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                if (preg_match('/^([a-zA-Z0-9\._]+[A-Za-z0-9_])@(.*?)$/u', $text, $mail)) {
                    $mail_domains = getMailDomains();
                    if (in_array($mail[2], $mail_domains)) {
                        $mail_address = $mail[1].'@'.$mail[2];
                        teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'E-Mail: '.$mail_address, 'reply_markup' => ['inline_keyboard' => [[['text' => '📩 Inbox', 'callback_data' => $mail_address]]]]]);
                    }
                } else {
                    teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'This address has been expired.', 'reply_markup' => ['keyboard' => [[['text' => 'NEW ADDRESS']]], 'resize_keyboard' => true]]);
                }
            } else {
                teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'Hi, '.$first_name."! Press NEW ADDRESS to get new temporary e-mail address.\n\nBy @Radio_Nima", 'reply_markup' => ['keyboard' => [[['text' => 'NEW ADDRESS']]], 'resize_keyboard' => true]]);
            }
        }
    } elseif (isset($update['callback_query'])) {
        $callback_query = $update['callback_query'];
        $callback_query_id = $callback_query['id'];
        $callback_data = $callback_query['data'];
        $chat_id = $callback_query['from']['id'];
        $inbox = getInbox($callback_data);
        $inbox = $inbox['msgs'];
        if (!empty($inbox)) {
            teleRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
            teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'Inbox of '.$callback_data.':']);
            foreach ($inbox as $message) {
                $message_content = getMessage($message['uid']);
                $message_text = 'From: '.$message['f']."\nAt: ".$message['rf']."\nContent:\n".$message_content['text'];
                teleRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $message_text]);
            }
        } else {
            teleRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => 'No letters in '.$callback_data]);
        }
    }
}
