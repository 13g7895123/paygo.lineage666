<?php
/**
 * 支付處理 API
 * 處理遊戲玩家的支付請求，包含贊助幣發放、紅利幣、各種禮品機制
 * 
 * @author Custom Project Team  
 * @version 2.0 優化版
 */

// 載入資料庫連線函式
include("../include.php");

// 除錯模式設定 (生產環境請設為 false 或移除此行)
// define('DEBUG_MODE', true);

/**
 * 回傳 JSON 格式錯誤訊息並結束程式
 * @param string $message 錯誤訊息
 * @param string $step 錯誤發生的步驟 (可選)
 * @param mixed $debug 除錯資訊 (可選，僅開發環境)
 */
function returnError($message, $step = null, $debug = null) {
    $response = [
            "status" => "error",
        "msg" => $message
    ];
    
    // 如果有指定錯誤步驟，加入回應中
    if ($step !== null) {
        $response["error_step"] = $step;
    }
    
    // 開發環境可顯示更多除錯資訊 (生產環境請移除或註解)
    if ($debug !== null && defined('DEBUG_MODE') && constant('DEBUG_MODE') === true) {
        $response["debug"] = $debug;
    }
    
    // 記錄詳細錯誤日誌
    $logMessage = "支付錯誤: {$message}";
    if ($step) $logMessage .= " [步驟: {$step}]";
    if ($debug) $logMessage .= " [除錯: " . json_encode($debug) . "]";
    error_log($logMessage);
    
    echo json_encode($response);
            exit;
        }

/**
 * 回傳 JSON 格式成功訊息並結束程式
 * @param string $message 成功訊息
 */
function returnSuccess($message) {
        echo json_encode([
            "status" => "success",
        "msg" => $message
        ]);
        exit;
    }

// ### 支付API ###

/**
 * 處理禮品發放邏輯
 * @param PDO $pdo 主資料庫連線
 * @param PDO $gamepdo 遊戲資料庫連線
 * @param array $serverLog 訂單資訊
 * @param int $money 付款金額
 * @param string $gameid 遊戲帳號
 * @param int $giftType 禮品類型 (1:滿額贈禮, 2:首購禮, 3:累積儲值, 4:活動首購禮)
 */
function processGift($pdo, $gamepdo, $serverLog, $money, $gameid, $giftType) {
    try {
        // 檢查禮品功能是否開啟
        $stmt = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = ? AND pid = 'stat'");
        $stmt->execute([$serverLog["foran"], $giftType]);
        $giftStatus = $stmt->fetch();
        
        if (!$giftStatus || $giftStatus["sizes"] != 1) {
            return; // 功能未開啟，直接返回
        }
        
        // 根據不同禮品類型處理
        switch ($giftType) {
            case 1: // 滿額贈禮
                processAmountGift($pdo, $gamepdo, $serverLog, $money, $gameid);
                break;
                
            case 2: // 首購禮
                processFirstPurchaseGift($pdo, $gamepdo, $serverLog, $money, $gameid, '首購禮');
                break;
                
            case 3: // 累積儲值
                processAccumulativeGift($pdo, $gamepdo, $serverLog, $money, $gameid);
                break;
                
            case 4: // 活動首購禮
                processEventFirstPurchaseGift($pdo, $gamepdo, $serverLog, $money, $gameid);
                break;
        }
    } catch (Exception $e) {
        $errorMsg = "禮品處理錯誤 (類型:{$giftType}): " . $e->getMessage();
        error_log($errorMsg . " [檔案:" . $e->getFile() . " 行號:" . $e->getLine() . "]");
        // 禮品處理失敗不中斷主流程，僅記錄錯誤
    }
}

/**
 * 處理滿額贈禮
 */
function processAmountGift($pdo, $gamepdo, $serverLog, $money, $gameid) {
    $stmt = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 1 AND pid != 'stat'");
    $stmt->execute([$serverLog["foran"]]);
    $gifts = $stmt->fetchAll();
    
    foreach ($gifts as $gift) {
        if ($money >= $gift["m1"] && $money <= $gift["m2"] && $gift["sizes"] > 0) {
            $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account) VALUES (?, '滿額贈禮', ?, ?)");
            $stmt->execute([$gift["pid"], $gift["sizes"], $gameid]);
        }
    }
}

/**
 * 處理首購禮
 */
function processFirstPurchaseGift($pdo, $gamepdo, $serverLog, $money, $gameid, $giftName) {
    // 檢查是否為首購
    $stmt = $gamepdo->prepare("SELECT COUNT(*) FROM shop_user WHERE account = ? AND p_name = '贊助幣'");
    $stmt->execute([$gameid]);
    
    if ($stmt->fetchColumn() == 1) { // 是首購
        $stmt = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 2 AND pid != 'stat'");
        $stmt->execute([$serverLog["foran"]]);
        $gifts = $stmt->fetchAll();
        
        foreach ($gifts as $gift) {
            if ($money >= $gift["m1"] && $money <= $gift["m2"] && $gift["sizes"] > 0) {
                $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account) VALUES (?, ?, ?, ?)");
                $stmt->execute([$gift["pid"], $giftName, $gift["sizes"], $gameid]);
            }
        }
    }
}

/**
 * 處理活動首購禮
 */
function processEventFirstPurchaseGift($pdo, $gamepdo, $serverLog, $money, $gameid) {
    // 取得活動時間
    $stmt = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 4 AND pid IN ('time1', 'time2')");
    $stmt->execute([$serverLog["foran"]]);
    $times = $stmt->fetchAll();
    
    $time1 = $time2 = null;
    foreach ($times as $time) {
        if ($time["pid"] == "time1") $time1 = $time["dd"];
        if ($time["pid"] == "time2") $time2 = $time["dd"];
    }
    
    // 檢查是否在活動時間內
    if ($time1 && $time2 && time() >= strtotime($time1) && time() <= strtotime($time2)) {
        // 檢查活動期間是否為首購
        $stmt = $gamepdo->prepare("SELECT COUNT(*) FROM shop_user WHERE account = ? AND p_name = '贊助幣' AND create_time BETWEEN ? AND ?");
        $stmt->execute([$gameid, $time1, $time2]);
        
        if ($stmt->fetchColumn() == 1) { // 活動期間首購
            $stmt = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 4 AND pid NOT IN ('stat', 'time1', 'time2')");
            $stmt->execute([$serverLog["foran"]]);
            $gifts = $stmt->fetchAll();
            
            foreach ($gifts as $gift) {
                if ($money >= $gift["m1"] && $money <= $gift["m2"] && $gift["sizes"] > 0) {
                    $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account) VALUES (?, '活動首購禮', ?, ?)");
                    $stmt->execute([$gift["pid"], $gift["sizes"], $gameid]);
                }
            }
        }
    }
}

/**
 * 處理累積儲值禮品
 */
function processAccumulativeGift($pdo, $gamepdo, $serverLog, $money, $gameid) {
    // 計算累積儲值金額
    $stmt = $gamepdo->prepare("SELECT SUM(r_count) FROM shop_user WHERE account = ? AND p_name = '贊助幣'");
    $stmt->execute([$gameid]);
    $totalPay = $stmt->fetchColumn();
    
    if ($totalPay > 0) {
        $stmt = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 3 AND pid != 'stat'");
        $stmt->execute([$serverLog["foran"]]);
        $gifts = $stmt->fetchAll();
        
        foreach ($gifts as $gift) {
            if ($totalPay >= $gift["m1"] && $gift["sizes"] > 0) {
                // 檢查是否已經領取過此累積獎勵
                $stmt = $gamepdo->prepare("SELECT COUNT(*) FROM shop_user WHERE account = ? AND p_name = '累積儲值' AND r_count = ?");
                $stmt->execute([$gameid, $gift["m1"]]);
                
                if ($stmt->fetchColumn() == 0) { // 尚未領取
                    $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account, r_count) VALUES (?, '累積儲值', ?, ?, ?)");
                    $stmt->execute([$gift["pid"], $gift["sizes"], $gameid, $gift["m1"]]);
                }
            }
        }
    }
}

// ### 主要處理流程 ###

// 解析 JSON 請求資料
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$dataId = isset($input['id']) ? $input['id'] : null;

// 驗證輸入參數
if (empty($dataId)) {
    returnError("缺少必要參數 id", "參數驗證", ["input" => $input, "dataId" => $dataId]);
}

// 建立資料庫連線
$pdo = openpdo();

try {
    // 開始資料庫交易
    $pdo->beginTransaction();
    
    // 查詢並鎖定訂單資料
    $stmt = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ? FOR UPDATE");
    $stmt->execute([$dataId]);
    $serverLog = $stmt->fetch();

    // 驗證訂單是否存在
    if (!$serverLog) {
        $pdo->rollBack();
        returnError("查無繳費資訊", "訂單查詢", ["dataId" => $dataId]);
    }

    // 驗證繳費狀態
    if ($serverLog["stats"] != 0) {
        $pdo->rollBack();
        returnError("繳費狀態不為等待付款", "狀態驗證", [
            "current_status" => $serverLog["stats"], 
            "order_id" => $dataId,
            "expected_status" => 0
        ]);
    }

    // 取得訂單資訊
    $money = $serverLog["money"];        // 付款金額
    $bmoney = $serverLog["bmoney"];      // 贊助幣數量
    $gameid = $serverLog["gameid"];      // 遊戲帳號
    $PaymentDate = date("Y-m-d H:i:s");  // 繳費時間
    $TradeAmt = $money;                   // 交易金額

    // 更新繳費狀態為已付款
    $stmt = $pdo->prepare("UPDATE servers_log SET stats = 1, paytimes = ?, rmoney = ? WHERE auton = ?");
    if (!$stmt->execute([$PaymentDate, $TradeAmt, $dataId])) {
        $pdo->rollBack();
        returnError("更新訂單狀態失敗", "訂單更新", [
            "order_id" => $dataId,
            "sql_error" => $stmt->errorInfo()
        ]);
    }
    
    // 記錄處理進度
    error_log("支付處理進度: 訂單狀態已更新 [訂單:{$dataId}]");

    // 查詢伺服器資訊
    $stmt = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
    $stmt->execute([$serverLog["foran"]]);
    $server = $stmt->fetch();

    if (!$server) {
        $pdo->rollBack();
        returnError("查無伺服器資訊", "伺服器查詢", [
            "server_id" => $serverLog["foran"],
            "order_id" => $dataId
        ]);
    }

    // 取得遊戲伺服器連線資訊
    $ip = $server["db_ip"];              // 資料庫 IP
    $port = $server["db_port"];          // 資料庫埠號
    $dbname = $server["db_name"];        // 資料庫名稱
    $user = $server["db_user"];          // 資料庫使用者
    $pass = $server["db_pass"];          // 資料庫密碼
    $pid = $server["db_pid"];            // 贊助幣商品 ID
    $bonusid = $server["db_bonusid"];    // 紅利幣商品 ID
    $bonusrate = $server["db_bonusrate"]; // 紅利幣比率

    // 建立遊戲資料庫連線
    $gamepdo = opengamepdo($ip, $port, $dbname, $user, $pass);
    if (!$gamepdo) {
        $pdo->rollBack();
        returnError("無法連線至遊戲資料庫", "遊戲資料庫連線", [
            "db_host" => $ip . ":" . $port,
            "db_name" => $dbname,
            "db_user" => $user,
            "server_id" => $serverLog["foran"]
        ]);
    }

    // 處理 ezpay 支付方式
    if ($server["paytable"] == "ezpay") {
        // ezpay 專用的贊助金處理
        $stmt = $gamepdo->prepare("INSERT INTO ezpay (amount, payname) VALUES (?, ?)");
        if (!$stmt->execute([$bmoney, $gameid])) {
            // 記錄錯誤訊息
            $errorInfo = $stmt->errorInfo();
            $stmt = $pdo->prepare("UPDATE servers_log SET errmsg = '存入贊助幣時發生錯誤' WHERE auton = ?");
            $stmt->execute([$dataId]);
            $pdo->commit();
            returnError("存入贊助幣時發生錯誤", "ezpay發放", [
                "sql_error" => $errorInfo,
                "amount" => $bmoney,
                "gameid" => $gameid,
                "order_id" => $dataId
            ]);
        }
        
        error_log("支付處理進度: ezpay 發放完成 [訂單:{$dataId}]");
        $pdo->commit();
        returnSuccess("繳費成功");
    }

    // 處理一般支付方式 - 發放贊助幣
    $card = 0;
    $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account, r_count, card) VALUES (?, '贊助幣', ?, ?, ?, ?)");
    if (!$stmt->execute([$pid, $bmoney, $gameid, $money, $card])) {
        // 記錄錯誤訊息
        $errorInfo = $stmt->errorInfo();
        $stmt = $pdo->prepare("UPDATE servers_log SET errmsg = '存入贊助幣時發生錯誤' WHERE auton = ?");
        $stmt->execute([$dataId]);
        $pdo->commit();
        
        returnError("存入贊助幣時發生錯誤", "贊助幣發放", [
            "sql_error" => $errorInfo,
            "product_id" => $pid,
            "amount" => $bmoney,
            "gameid" => $gameid,
            "order_id" => $dataId
        ]);
    }

    // 處理紅利幣發放
    if (!empty($bonusid) && $bonusrate > 0) {
        $bonusmoney = $money * ($bonusrate / 100);
        $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account, r_count) VALUES (?, '紅利幣', ?, ?, ?)");
        if (!$stmt->execute([$bonusid, $bonusmoney, $gameid, $money])) {
            // 記錄錯誤訊息
            $errorInfo = $stmt->errorInfo();
            $stmt = $pdo->prepare("UPDATE servers_log SET errmsg = '存入紅利幣時發生錯誤' WHERE auton = ?");
            $stmt->execute([$dataId]);
            $pdo->commit();
            returnError("存入紅利幣時發生錯誤", "紅利幣發放", [
                "sql_error" => $errorInfo,
                "bonus_id" => $bonusid,
                "bonus_amount" => $bonusmoney,
                "bonus_rate" => $bonusrate,
                "base_amount" => $money,
                "gameid" => $gameid,
                "order_id" => $dataId
            ]);
        }
    }

    // 記錄核心發放完成
    error_log("支付處理進度: 贊助幣與紅利幣發放完成 [訂單:{$dataId}]");

    // 處理各種禮品機制
    error_log("支付處理進度: 開始處理禮品機制 [訂單:{$dataId}]");
    processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 1); // 滿額贈禮
    processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 2); // 首購禮
    processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 4); // 活動首購禮
    processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 3); // 累積儲值
    error_log("支付處理進度: 禮品機制處理完成 [訂單:{$dataId}]");

    // 提交所有交易
    $pdo->commit();

    // 返回成功結果
    returnSuccess("繳費成功");

} catch (Exception $e) {
    // 發生錯誤時回滾交易
    if ($pdo->inTransaction()) {
    $pdo->rollBack();
    }
    
    // 記錄詳細錯誤日誌
    $errorDetails = [
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString(),
        "order_id" => isset($dataId) ? $dataId : 'unknown',
        "gameid" => isset($gameid) ? $gameid : 'unknown'
    ];
    
    error_log("支付處理系統錯誤: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
    
    // 返回錯誤訊息 (生產環境不顯示詳細錯誤)
    $showDebug = defined('DEBUG_MODE') && constant('DEBUG_MODE') === true;
    returnError("系統錯誤，請稍後再試", "系統異常", 
        $showDebug ? $errorDetails : null
    );
}
?>