<?php
/**
 * Funpoint 錯誤日誌記錄機制
 * 提供統一的錯誤日誌記錄功能
 */

/**
 * 記錄 Funpoint 相關錯誤
 *
 * @param string $error_code 錯誤代碼
 * @param string $error_msg 錯誤訊息
 * @param array $context 上下文資訊（可選）
 * @return bool 是否成功記錄
 */
function log_funpoint_error($error_code, $error_msg, $context = []) {
    // 確保 logs 目錄存在
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/funpoint_error.log';

    // 組合日誌內容
    $log_entry = sprintf(
        "[%s] Code: %s, Message: %s, Context: %s\n",
        date('Y-m-d H:i:s'),
        $error_code,
        $error_msg,
        json_encode($context, JSON_UNESCAPED_UNICODE)
    );

    // 寫入日誌
    $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

    // 同時使用 PHP error_log 記錄
    error_log("[Funpoint] {$error_code}: {$error_msg}");

    return $result !== false;
}

/**
 * 記錄 Funpoint 資訊
 *
 * @param string $message 訊息內容
 * @param array $context 上下文資訊（可選）
 * @return bool 是否成功記錄
 */
function log_funpoint_info($message, $context = []) {
    // 確保 logs 目錄存在
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/funpoint_info.log';

    // 組合日誌內容
    $log_entry = sprintf(
        "[%s] Info: %s, Context: %s\n",
        date('Y-m-d H:i:s'),
        $message,
        json_encode($context, JSON_UNESCAPED_UNICODE)
    );

    // 寫入日誌
    $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

    return $result !== false;
}

/**
 * 記錄 Funpoint 交易資訊
 *
 * @param string $order_id 訂單編號
 * @param string $action 動作（如：payment_start, payment_success, payment_fail）
 * @param array $details 詳細資訊
 * @return bool 是否成功記錄
 */
function log_funpoint_transaction($order_id, $action, $details = []) {
    // 確保 logs 目錄存在
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/funpoint_transaction.log';

    // 組合日誌內容
    $log_entry = sprintf(
        "[%s] OrderID: %s, Action: %s, Details: %s\n",
        date('Y-m-d H:i:s'),
        $order_id,
        $action,
        json_encode($details, JSON_UNESCAPED_UNICODE)
    );

    // 寫入日誌
    $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

    return $result !== false;
}
?>
