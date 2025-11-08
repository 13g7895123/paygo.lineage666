<?php
/**
 * 支付處理核心函式庫
 * 將 pay.php 的遊戲端處理邏輯抽取為可重用的函式
 * 
 * @author Custom Project Team
 * @version 1.0
 */

// 載入資料庫連線函式
include_once("../include.php");

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
        return false;
    }
    return true;
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

/**
 * 完整的遊戲端支付處理函式
 * @param PDO $pdo 主資料庫連線
 * @param int $dataId 訂單ID
 * @param bool $isMock 是否為模擬付款
 * @return array 處理結果
 */
function processGamePayment($pdo, $dataId, $isMock = false) {
    try {
        // 查詢並鎖定訂單資料
        $stmt = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ? FOR UPDATE");
        $stmt->execute([$dataId]);
        $serverLog = $stmt->fetch();

        // 驗證訂單是否存在
        if (!$serverLog) {
            return [
                'success' => false,
                'error' => '查無繳費資訊',
                'step' => '訂單查詢'
            ];
        }

        // 取得訂單資訊
        $money = $serverLog["money"];        // 付款金額
        $bmoney = $serverLog["bmoney"];      // 贊助幣數量
        $gameid = $serverLog["gameid"];      // 遊戲帳號

        // 查詢伺服器資訊
        $stmt = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
        $stmt->execute([$serverLog["foran"]]);
        $server = $stmt->fetch();

        if (!$server) {
            return [
                'success' => false,
                'error' => '查無伺服器資訊',
                'step' => '伺服器查詢'
            ];
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
            return [
                'success' => false,
                'error' => '無法連線至遊戲資料庫',
                'step' => '遊戲資料庫連線'
            ];
        }

        $gameResults = [];

        // 處理 ezpay 支付方式
        if ($server["paytable"] == "ezpay") {
            // ezpay 專用的贊助金處理
            $stmt = $gamepdo->prepare("INSERT INTO ezpay (amount, payname) VALUES (?, ?)");
            if (!$stmt->execute([$bmoney, $gameid])) {
                return [
                    'success' => false,
                    'error' => '存入贊助幣時發生錯誤',
                    'step' => 'ezpay發放',
                    'sql_error' => $stmt->errorInfo()
                ];
            }
            
            $gameResults['ezpay'] = [
                'amount' => $bmoney,
                'gameid' => $gameid
            ];
        } else {
            // 處理一般支付方式 - 發放贊助幣
            $card = 0;
            $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account, r_count, card) VALUES (?, '贊助幣', ?, ?, ?, ?)");
            if (!$stmt->execute([$pid, $bmoney, $gameid, $money, $card])) {
                return [
                    'success' => false,
                    'error' => '存入贊助幣時發生錯誤',
                    'step' => '贊助幣發放',
                    'sql_error' => $stmt->errorInfo()
                ];
            }

            $gameResults['donation_coin'] = [
                'product_id' => $pid,
                'amount' => $bmoney,
                'gameid' => $gameid
            ];

            // 處理紅利幣發放
            if (!empty($bonusid) && $bonusrate > 0) {
                $bonusmoney = $money * ($bonusrate / 100);
                $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account, r_count) VALUES (?, '紅利幣', ?, ?, ?)");
                if (!$stmt->execute([$bonusid, $bonusmoney, $gameid, $money])) {
                    return [
                        'success' => false,
                        'error' => '存入紅利幣時發生錯誤',
                        'step' => '紅利幣發放',
                        'sql_error' => $stmt->errorInfo()
                    ];
                }

                $gameResults['bonus_coin'] = [
                    'bonus_id' => $bonusid,
                    'amount' => $bonusmoney,
                    'rate' => $bonusrate
                ];
            }

            // 處理各種禮品機制 (模擬付款和真實付款都需要處理)
            $giftResults = [];
            $giftResults['amount_gift'] = processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 1);
            $giftResults['first_purchase'] = processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 2);
            $giftResults['event_first_purchase'] = processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 4);
            $giftResults['accumulative'] = processGift($pdo, $gamepdo, $serverLog, $money, $gameid, 3);
            
            $gameResults['gifts'] = $giftResults;
        }

        return [
            'success' => true,
            'game_results' => $gameResults,
            'order_info' => [
                'order_id' => $dataId,
                'money' => $money,
                'bmoney' => $bmoney,
                'gameid' => $gameid,
                'server_id' => $serverLog["foran"],
                'paytable' => $server["paytable"],
                'is_mock' => $isMock
            ]
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => '系統錯誤: ' . $e->getMessage(),
            'step' => '系統異常',
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ];
    }
}
?>
