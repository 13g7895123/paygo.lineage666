<?php
/**
 * 模擬付款 API
 * 將原本的 mockpay.php 重構為 API 格式
 * 支援多種金流的模擬付款處理
 * 
 * @author Custom Project Team
 * @version 2.0 API 版本
 */

// 載入資料庫連線函式
include("../include.php");

// 設定 HTTP 標頭確保正確回應
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 確保輸出緩衝區關閉，立即回應
if (ob_get_level()) {
    ob_end_clean();
}

// 記錄 API 開始處理
file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - MockPay API 開始處理\n", FILE_APPEND);

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
    
    // 開發環境可顯示更多除錯資訊
    if ($debug !== null && defined('DEBUG_MODE') && constant('DEBUG_MODE') === true) {
        $response["debug"] = $debug;
    }
    
    // 記錄錯誤日誌
    $logMessage = date('Y-m-d H:i:s') . " - MockPay API 錯誤: {$message}";
    if ($step) $logMessage .= " [步驟: {$step}]";
    if ($debug) $logMessage .= " [除錯: " . json_encode($debug, JSON_UNESCAPED_UNICODE) . "]";
    
    error_log($logMessage);
    file_put_contents('debug_log.txt', $logMessage . "\n", FILE_APPEND);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    flush();
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
    $logMessage = date('Y-m-d H:i:s') . " - MockPay API 成功: {$message}";
    error_log($logMessage);
    file_put_contents('debug_log.txt', $logMessage . "\n", FILE_APPEND);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    flush();
    exit;
}

/**
 * 生成模擬付款參數
 * @param array $orderInfo 訂單資訊
 * @param string $nowtime 當前時間
 * @return array 包含 tourl, params, endstr 的陣列
 */
function generateMockPayParams($orderInfo, $nowtime) {
    $tourl = "";
    $params = [];
    $endstr = "1|OK";
    
    switch ($orderInfo["pay_cp"]) {
        case "ecpay":
            // 綠界金流
            $tourl = "r.php";
            $params = [
                'MerchantTradeNo' => $orderInfo["orderid"],
                'RtnCode' => 1,
                'RtnMsg' => '模擬付款成功',
                'CheckMacValue' => 'system',
                'TradeAmt' => $orderInfo["money"],
                'PaymentDate' => $nowtime,
                'PaymentTypeChargeFee' => 0
            ];
            break;

        case "ebpay":
            // 藍新金流
            $foran = $orderInfo["foran"];
            $result = [
                'MerchantOrderNo' => $orderInfo["orderid"],
                'CheckCode' => 'system',
                'Amt' => $orderInfo["money"],
                'PayTime' => $nowtime
            ];

            $tradeInfo = [
                'Status' => 'SUCCESS',
                'Message' => '模擬付款成功',
                'Result' => $result,
            ];

            $tourl = "ebpay_r.php?an=" . $foran;
            $params = [
                'Status' => 'SUCCESS',
                'TradeInfo' => 'mock_encrypted_data_' . base64_encode(json_encode($tradeInfo))
            ];
            break;

        case "pchome":
            // 支付連
            $result = [
                'status' => 'S',
                'order_id' => $orderInfo["orderid"],
                'trade_amount' => $orderInfo["money"],
                'pay_date' => $nowtime,
                'pp_fee' => 0
            ];

            $tourl = "pchome_r.php";
            $params = [
                'notify_type' => 'order_confirm',
                'notify_message' => json_encode($result)
            ];
            break;

        case "gomypay":
            // 萬事達
            $tourl = "gomypay_r.php";
            $params = [
                'e_orderno' => $orderInfo["orderid"],
                'result' => 1,
                'ret_msg' => '模擬付款成功',
                'str_check' => 'system',
                'e_money' => $orderInfo["money"],
                'e_date' => date('Y-m-d', strtotime($nowtime)),
                'e_time' => date('H:i:s', strtotime($nowtime))
            ];
            break;

        case "smilepay":
            // 速買配
            $tourl = "smilepay_r.php";
            $params = [
                'Data_id' => $orderInfo["orderid"],
                'result' => 1,
                'Errdesc' => '模擬付款成功',
                'str_check' => 'system',
                'Amount' => $orderInfo["money"],
                'Process_date' => $nowtime
            ];
            $endstr = '<Roturlstatus>OK</Roturlstatus>';
            break;

        case "funpoint":
            // Fun點
            $tourl = "funpoint_r.php";
            $params = [
                'MerchantTradeNo' => $orderInfo["orderid"],
                'RtnCode' => 1,
                'RtnMsg' => '模擬付款成功',
                'CheckMacValue' => 'system',
                'TradeAmt' => $orderInfo["money"],
                'PaymentDate' => $nowtime,
                'PaymentTypeChargeFee' => 0
            ];
            break;

        case "szfu":
            // 數支付
            $tourl = "szfu_r.php";
            $params = [
                'TradeNo' => $orderInfo["orderid"],
                'RtnCode' => 1,
                'RtnMsg' => '模擬付款成功',
                'Price' => $orderInfo["money"],
                'PayDate' => $nowtime,
            ];
            $endstr = "1";
            break;

        default:
            returnError("不支援的金流類型", "金流驗證", [
                "pay_cp" => $orderInfo["pay_cp"],
                "supported" => ["ecpay", "ebpay", "pchome", "gomypay", "smilepay", "funpoint", "szfu"]
            ]);
            break;
    }
    
    // 加入模擬付款標記
    $params["mockpay"] = 1;
    
    return [
        'tourl' => $tourl,
        'params' => $params,
        'endstr' => $endstr
    ];
}

/**
 * 執行模擬金流回調
 * @param string $weburl 網站基礎 URL
 * @param string $tourl 回調 URL
 * @param array $params 回調參數
 * @return string 回調結果
 */
function executeMockCallback($weburl, $tourl, $params) {
    // 模擬 curl 請求
    $url = $weburl . $tourl;
    
    // 建立 POST 資料
    $postData = http_build_query($params);
    
    // 初始化 cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL 錯誤: " . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP 錯誤: " . $httpCode);
    }
    
    return $result;
}

// ### 主要處理流程 ###

try {
    // 解析請求參數
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    
    // 支援 GET 和 POST 參數
    $auton = $input['an'] ?? $_GET['an'] ?? $_POST['an'] ?? null;
    $payType = $input['type'] ?? $_GET['type'] ?? $_POST['type'] ?? null;
    
    // 記錄接收到的請求
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - MockPay API 接收請求: auton={$auton}, type={$payType}\n", FILE_APPEND);
    
    // 驗證必要參數
    if (empty($auton)) {
        returnError("缺少訂單編號 (an)", "參數驗證", ["auton" => $auton]);
    }
    
    if (!is_numeric($auton)) {
        returnError("訂單編號格式錯誤", "參數驗證", ["auton" => $auton, "type" => gettype($auton)]);
    }
    
    // 建立資料庫連線
    $pdo = openpdo();
    if (!$pdo) {
        returnError("無法連線至資料庫", "資料庫連線");
    }
    
    // 查詢訂單資訊
    $stmt = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ?");
    $stmt->execute([$auton]);
    $orderInfo = $stmt->fetch();
    
    if (!$orderInfo) {
        returnError("查無此筆訂單", "訂單查詢", ["auton" => $auton]);
    }
    
    // 檢查訂單狀態
    if ($orderInfo["stats"] != 0 && $orderInfo["stats"] != 2) {
        returnError("付款狀態不符", "狀態驗證", [
            "current_status" => $orderInfo["stats"],
            "allowed_status" => [0, 2],
            "auton" => $auton
        ]);
    }
    
    // 記錄處理進度
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - MockPay: 開始處理訂單 {$auton}, 金流: {$orderInfo["pay_cp"]}\n", FILE_APPEND);
    
    // 生成當前時間
    $nowtime = date("Y/m/d H:i:s");
    
    // 生成模擬付款參數
    $mockParams = generateMockPayParams($orderInfo, $nowtime);
    
    // 取得網站基礎 URL
    $weburl = getWebUrl();
    
    // 執行模擬回調
    $callbackResult = executeMockCallback($weburl, $mockParams['tourl'], $mockParams['params']);
    
    // 檢查回調結果
    $isSuccess = ($callbackResult == "1|OK" || $callbackResult == $mockParams['endstr']);
    
    if ($isSuccess) {
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - MockPay 成功: auton={$auton}, 回調結果={$callbackResult}\n", FILE_APPEND);
        
        returnSuccess("模擬付款完成", [
            "auton" => $auton,
            "orderid" => $orderInfo["orderid"],
            "amount" => $orderInfo["money"],
            "pay_cp" => $orderInfo["pay_cp"],
            "callback_result" => $callbackResult,
            "callback_url" => $weburl . $mockParams['tourl']
        ]);
    } else {
        returnError("模擬付款失敗", "回調處理", [
            "auton" => $auton,
            "callback_result" => $callbackResult,
            "expected" => $mockParams['endstr'],
            "callback_url" => $weburl . $mockParams['tourl']
        ]);
    }

} catch (Exception $e) {
    // 發生錯誤時的處理
    $errorDetails = [
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString(),
        "auton" => isset($auton) ? $auton : 'unknown'
    ];
    
    error_log("MockPay API 系統錯誤: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - MockPay API 系統異常: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    returnError("系統錯誤，請稍後再試", "系統異常", $errorDetails);
}

/**
 * 取得網站基礎 URL
 * @return string 網站基礎 URL
 */
function getWebUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host . '/';
    
    // 如果有定義 $weburl 全域變數，使用它
    global $weburl;
    if (isset($weburl) && !empty($weburl)) {
        return $weburl;
    }
    
    return $baseUrl;
}
?>
