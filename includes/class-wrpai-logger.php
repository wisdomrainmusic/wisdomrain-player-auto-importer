<?php

class WRPAI_Logger {

    public static function log($message) {
        error_log("[WRPAI] " . $message);
    }
}
