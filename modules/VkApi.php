<?php
namespace HwBot\VkApi;


use const HwBot\COMMUNITY_ID;
use const HwBot\VK_API_ACCESS_TOKEN;
use const HwBot\VK_API_ENDPOINT;
use const HwBot\VK_API_VERSION;
use Utility\Utility as Util;

require_once __DIR__.'/../util/Utility.php';
require_once __DIR__.'/../config/config.php';

class VkApi {

    public static function send_message($user, $message, $reply_to = '', $attachments = []) {
        return self::callApi('messages.send', [
            'peer_id' => $user,
            'message' => $message,
            'reply_to' => $reply_to,
            'random_id' => mt_rand(),
            'attachment' => implode(',', $attachments),
        ]);
    }
    public static function delete_message($message_id, $delete_for_all = true) {
        return self::callApi('messages.delete', [
            'group_id' => COMMUNITY_ID,
            'message_ids' => "$message_id",
            'delete_for_all' => $delete_for_all ? '1' : '0',
        ]);
    }
    public static function get_session() {
        return self::callApi('groups.getLongPollServer', [
            'group_id' => COMMUNITY_ID,
        ]);
    }
    public static function get_admins() {
        $resp = self::callApi('groups.getMembers', [
            'group_id' => COMMUNITY_ID,
            'filter' => 'managers'
        ]);
        $result = [];
        foreach($resp['items'] as $admin) {
            if($admin['role'] === 'administrator' || $admin['role'] === 'creator' || $admin['role'] === 'editor')
                $result[] = $admin['id'];
        }
        return $result;
    }
    public static function getMessagesUploadServer($peer_id) {
        return self::callApi('photos.getMessagesUploadServer', [
            'peer_id' => $peer_id,
        ]);
    }
    public static function saveMessagesPhoto($photo, $server, $hash) {
        return self::callApi('photos.saveMessagesPhoto', array(
            'photo'  => $photo,
            'server' => $server,
            'hash'   => $hash,
        ));
    }
    public static function upload($url, $file) {
        if(!file_exists($file)) throw new \Exception("File ".realpath($file)." doesn't exist");
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, array('file' => new \CURLfile($file)));
        $json = curl_exec($curl);
        $error = curl_error($curl);
        if ($error) {
            Util::log_err($error);
            return false;
        }
        curl_close($curl);
        $response = json_decode($json, true);
        if (!$response) {
            Util::log_err($json);
            exit;
        }
        return $response;
    }
    public static function getUser($user_id) {
        return self::callApi('users.get', [
            'user_id' => $user_id,
        ]);
    }

    private static function callApi($method, $params = []) {
        $params['access_token'] = VK_API_ACCESS_TOKEN;
        $params['v'] = VK_API_VERSION;

        $query = http_build_query($params);
        $url = VK_API_ENDPOINT.$method.'?'.$query;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($curl);
        $error = curl_error($curl);
        if ($error) {
            Util::log_err($error);
            exit;
        }

        curl_close($curl);

        $response = json_decode($json, true);
        if (!$response || !isset($response['response'])) {
            Util::log_err($json);
            exit;
        }

        return $response['response'];
    }
}