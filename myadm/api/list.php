<?php
/**
 * 列表處理 API
 * 處理手動付款等列表操作功能
 * 
 * @author Custom Project Team
 * @version 2.0 優化版
 */

// 開啟錯誤報告並捕獲致命錯誤
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 設定錯誤處理器
function errorHandler($errno, $errstr, $errfile, $errline) {
    $error = "PHP Error: [$errno] $errstr in $errfile on line $errline";
    error_log($error);
    echo json_encode(["status" => "error", "msg" => "系統錯誤", "debug" => $error], JSON_UNESCAPED_UNICODE);
    exit;
}
set_error_handler("errorHandler");

// 嘗試載入必要檔案
try {
    // 載入資料庫連線函式
    if (!file_exists("../include.php")) {
        throw new Exception("include.php 檔案不存在");
    }
    include_once("../include.php");
    
    // 檢查核心函式是否存在
    if (!function_exists('openpdo')) {
        throw new Exception("openpdo 函式不存在");
    }
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error", 
        "msg" => "載入核心檔案失敗", 
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 設定 HTTP 標頭確保正確回應
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 確保輸出緩衝區關閉，立即回應
while (ob_get_level()) {
    ob_end_clean();
}

// 記錄 API 開始處理
file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - List API 開始處理\n", FILE_APPEND);

/**
 * 回傳 JSON 格式錯誤訊息並結束程式
 * @param string $message 錯誤訊息
 * @param string $step 錯誤發生的步驟 (可選)
 * @param mixed $debug 除錯資訊 (可選)
 */
function returnError($message, $step = null, $debug = null) {
    $response = [
        "status" => "error",
        "msg" => $message
    ];
    
    if ($step !== null) {
        $response["error_step"] = $step;
    }
    
    // 記錄錯誤日誌
    $logMessage = date('Y-m-d H:i:s') . " - List API 錯誤: {$message}";
    if ($step) $logMessage .= " [步驟: {$step}]";
    if ($debug) $logMessage .= " [除錯: " . json_encode($debug, JSON_UNESCAPED_UNICODE) . "]";
    
    error_log($logMessage);
    file_put_contents('debug_log.txt', $logMessage . "\n", FILE_APPEND);
    
    // 確保沒有額外輸出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 回傳 JSON 格式成功訊息並結束程式
 * @param string $message 成功訊息
 * @param mixed $data 額外資料 (可選)
 */
function returnSuccess($message, $data = null) {
    $response = [
        "status" => "success",
        "msg" => $message
    ];
    
    if ($data !== null) {
        $response["data"] = $data;
    }
    
    // 記錄成功日誌
    $logMessage = date('Y-m-d H:i:s') . " - List API 成功: {$message}";
    error_log($logMessage);
    file_put_contents('debug_log.txt', $logMessage . "\n", FILE_APPEND);
    
    // 確保沒有額外輸出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? null;
// 以 JSON 格式接收 POST 請求資料，解析出 auton 參數
$inputJSON = file_get_contents('php://input'); // 取得原始 POST 資料
$input = json_decode($inputJSON, true);        // 解析 JSON 為陣列
$auton = isset($input['id']) ? $input['id'] : null; // 取得 id 欄位作為 auton
$is_mock = isset($input['is_mock']) ? $input['is_mock'] : 0; // 取得 is_mock 欄位作為 is_mock

// 記錄接收到的請求
file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - List API 接收請求: action={$action}, input=" . json_encode($input, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

// 驗證必要參數
if (empty($action)) {
    returnError("缺少 action 參數", "參數驗證", ["action" => $action]);
}

// 依照指示，將指定 auton 的紀錄進行手動付款狀態更新
// 1. 連接資料庫
// 2. 執行更新指令，將 stats 設為 1，RtnMsg 設為 '手動付款完成'
// 3. 回傳執行結果

try {
    switch($action) {
        case "hand_pay":
            hand_pay($auton, $is_mock);
            break;
        case "test_basic":
            // 基本測試端點
            returnSuccess("List API 正常運作", [
                "version" => "2.0",
                "timestamp" => date('Y-m-d H:i:s'),
                "php_version" => phpversion(),
                "include_loaded" => function_exists('openpdo'),
                "action" => $action
            ]);
            break;
        default:
            returnError("不支援的操作", "操作驗證", ["action" => $action]);
            break;
    }
} catch (Exception $e) {
    // 記錄詳細錯誤
    $errorDetails = [
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "action" => $action,
        "auton" => $auton
    ];
    
    error_log("List API 系統錯誤: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - List API 系統異常: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    returnError("系統錯誤，請稍後再試", "系統異常", $errorDetails);
}

// 手動付款
function hand_pay($auton, $is_mock=0) {
    // 記錄手動付款開始
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 手動付款開始: auton={$auton}, is_mock={$is_mock}\n", FILE_APPEND);
    
    // 驗證輸入參數
    if (empty($auton)) {
        returnError("缺少訂單 ID", "參數驗證", ["auton" => $auton]);
    }
    
    // 檢查 auton 是否為有效數字，避免 SQL Injection
    if (!is_numeric($auton)) {
        returnError("訂單 ID 格式錯誤", "參數驗證", ["auton" => $auton, "type" => gettype($auton)]);
    }

    // 開啟 PDO 連線
    try {
        $pdo = openpdo();
        if (!$pdo) {
            returnError("無法連線至資料庫", "資料庫連線");
        }
    } catch (Exception $e) {
        returnError("資料庫連線失敗", "資料庫連線", ["error" => $e->getMessage()]);
    }

    // 準備 SQL 更新語句
    // 注意：SQL 語法需使用逗號分隔欄位，不能用 and
    $msg = $is_mock == 1 ? "模擬付款完成" : "手動付款完成";
    $stats = $is_mock == 1 ? 3 : 1;

    // 記錄處理步驟
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 手動付款: 開始查詢訂單 {$auton}\n", FILE_APPEND);

    // 先撈出指定 auton 的資料，取得 money 欄位
    try {
        $query = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ?");
        $query->execute([$auton]);
        $row = $query->fetch();

        if (!$row) {
            returnError("查無此筆資料", "訂單查詢", [
                "auton" => $auton,
                "query_result" => "empty"
            ]);
        }
    } catch (Exception $e) {
        returnError("查詢訂單失敗", "訂單查詢", [
            "auton" => $auton,
            "sql_error" => $e->getMessage()
        ]);
    }

    // 取得 money 欄位資料
    $money = $row['money'];
    
    // 記錄訂單資訊
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 手動付款: 找到訂單資料, money={$money}, 準備先處理遊戲端發放\n", FILE_APPEND);

    // 新流程：先處理遊戲端發放，成功後才調整訂單狀態
    $paymentType = ($stats == 1) ? "真實付款" : "模擬付款";
    $isMockPayment = ($stats == 3);
    
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - {$paymentType}，開始處理遊戲端發放: auton={$auton}\n", FILE_APPEND);
    
    // 步驟 1: 先處理遊戲端發放
    try {
        if (!file_exists("payment_processor.php")) {
            throw new Exception("payment_processor.php 檔案不存在");
        }
        
        // print_r("before payment_processor.php"); die();
        include_once("payment_processor.php");
        
        if (!function_exists('processGamePayment')) {
            throw new Exception("processGamePayment 函式不存在");
        }
        
        // 調用遊戲端處理邏輯，傳入是否為模擬付款的標記
        $gameResult = processGamePayment($pdo, $auton, $isMockPayment);
        
        if (!$gameResult['success']) {
            // 遊戲端處理失敗，不調整訂單狀態
            $errorMsg = "遊戲端發放失敗: " . $gameResult['error'];
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - {$errorMsg}: auton={$auton}，不調整訂單狀態\n", FILE_APPEND);
            
            returnError($errorMsg, "遊戲端處理", $gameResult);
        } else {
            // 遊戲端處理成功，記錄日誌
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 遊戲端發放成功: auton={$auton}，開始調整訂單狀態\n", FILE_APPEND);
        }
        
    } catch (Exception $e) {
        // 遊戲端處理模組載入失敗，不調整訂單狀態
        $errorMsg = "遊戲端處理模組錯誤: " . $e->getMessage();
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - {$errorMsg}: auton={$auton}，不調整訂單狀態\n", FILE_APPEND);
        
        returnError($errorMsg, "遊戲端模組載入", [
            "error" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]);
    }
    
    // 步驟 2: 遊戲端發放成功後，才調整訂單狀態
    try {
        // 使用完全參數化的查詢，避免 SQL 注入
        $sql = "UPDATE servers_log SET stats = ?, RtnMsg = ?, rmoney = ?, paytimes = now() WHERE auton = ?";
        $stmt = $pdo->prepare($sql);
        
        // 綁定參數並執行
        $result = $stmt->execute([$stats, $msg, $money, $auton]);
        
        if ($result) {
            // 檢查實際影響的行數
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows > 0) {
                // 更新成功，記錄日誌
                file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 訂單狀態調整成功: auton={$auton}, 影響行數={$affectedRows}\n", FILE_APPEND);
                
                // 返回完整成功結果
                returnSuccess($msg, [
                    "auton" => $auton,
                    "stats" => $stats,
                    "money" => $money,
                    "affected_rows" => $affectedRows,
                    "payment_type" => $paymentType,
                    "is_mock" => $isMockPayment,
                    "processing_order" => "遊戲端發放 → 訂單狀態調整",
                    "game_processing" => $gameResult['game_results'],
                    "order_info" => $gameResult['order_info']
                ]);
            } else {
                // 遊戲端已成功，但訂單狀態調整失敗 - 這是嚴重問題
                file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 嚴重警告: 遊戲端發放成功但訂單狀態調整失敗: auton={$auton}\n", FILE_APPEND);
                
                returnError("遊戲端發放成功，但訂單狀態調整失敗", "訂單狀態調整", [
                    "auton" => $auton,
                    "affected_rows" => $affectedRows,
                    "game_processing" => "已完成",
                    "critical_warning" => "遊戲端已發放，但資料庫狀態未調整，需要手動檢查"
                ]);
            }
        } else {
            // 遊戲端已成功，但訂單調整 SQL 執行失敗
            $errorInfo = $stmt->errorInfo();
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - 嚴重警告: 遊戲端發放成功但 SQL 執行失敗: auton={$auton}\n", FILE_APPEND);
            
            returnError("遊戲端發放成功，但訂單狀態 SQL 執行失敗", "SQL執行", [
                "auton" => $auton,
                "sql_error" => $errorInfo,
                "sql_state" => $errorInfo[0] ?? 'unknown',
                "error_code" => $errorInfo[1] ?? 0,
                "error_message" => $errorInfo[2] ?? 'unknown error',
                "game_processing" => "已完成",
                "critical_warning" => "遊戲端已發放，但資料庫狀態未調整，需要手動檢查"
            ]);
        }
        
    } catch (Exception $e) {
        returnError("更新訂單狀態時發生異常", "狀態更新", [
            "auton" => $auton,
            "exception" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]);
    }
}







?>