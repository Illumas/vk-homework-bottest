<?php
namespace Utility;

use const HwBot\DB_NAME;
use const HwBot\DB_PW;
use const HwBot\DB_USER;
use const HwBot\DEV_MODE;
use PDO;

require_once __DIR__ . '/../config/config.php';

class Utility {

    private const LOGS_FOLDER = __DIR__.'/../logs';
    private const TIME_ZONE = 'Asia/Vladivostok';

    public static function log_msg($msg) {
        self::log_write("[INFO] $msg");
    }

    public static function log_err($err) {
        self::log_write("[ERROR] $err");
    }

    protected static function log_write($msg) {
        if(!is_scalar($msg)) $msg = json_encode($msg);

        $trace = debug_backtrace();
        $function_name = isset($trace[2]) ? $trace[2]['function'] : '-';
        $mark = date("H:i:s") . ' [' . $function_name . ']';
        $log_name = self::LOGS_FOLDER . '/log_' . date("m.d.Y") . '.txt';
        file_put_contents($log_name, $mark . " : " . $msg . "\n", FILE_APPEND);
    }

    public static function set_timezone() {
        date_default_timezone_set(self::TIME_ZONE);
    }

    public static function get_vk_request() {
        return json_decode(file_get_contents('php://input'), true);
    }

    public static function dbConnect() {
        try {
            // TODO: set constants
            $conn = new PDO(DEV_MODE ? "pgsql:host=localhost;dbname=".DB_NAME : "mysql:host=localhost;dbname=".DB_NAME,
                DB_USER, DB_PW);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        } catch(\PDOException $e) {
            self::log_err($e->getMessage());
            exit;
        }
    }
    public static function mb_ucfirst($text) {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}