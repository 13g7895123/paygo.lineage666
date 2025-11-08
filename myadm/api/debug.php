<?php
/**
 * 除錯 API
 * 提供各種除錯功能，包括日誌查看、連線測試等
 * 
 * @author Custom Project Team
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 載入資料庫連線函式
include("../include.php");

/**
 * 回傳 JSON 回應
 */
function returnResponse($data, $success = true) {
    echo json_encode([
        "status" => $success ? "success" : "error",
        "data" => $data,
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 解析請求參數
$action = $_GET['action'] ?? $_POST['action'] ?? 'help';

switch ($action) {
    case 'logs':
        // 查看最新日誌
        $lines = $_GET['lines'] ?? 50;
        $logFile = 'debug_log.txt';
        
        if (!file_exists($logFile)) {
            returnResponse("日誌檔案不存在", false);
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        $recentLogs = array_slice($logs, -$lines);
        
        returnResponse([
            "total_lines" => count($logs),
            "showing_lines" => count($recentLogs),
            "logs" => $recentLogs
        ]);
        break;
        
    case 'clear_logs':
        // 清空日誌
        $logFile = 'debug_log.txt';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            returnResponse("日誌已清空");
        } else {
            returnResponse("日誌檔案不存在", false);
        }
        break;
        
    case 'test_db':
        // 測試資料庫連線
        try {
            $pdo = openpdo();
            if ($pdo) {
                // 測試基本查詢
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM servers_log LIMIT 1");
                $result = $stmt->fetch();
                
                returnResponse([
                    "main_db" => "連線成功",
                    "test_query" => "查詢成功",
                    "servers_log_accessible" => "可存取"
                ]);
            } else {
                returnResponse("無法建立資料庫連線", false);
            }
        } catch (Exception $e) {
            returnResponse([
                "error" => "資料庫連線失敗",
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ], false);
        }
        break;
        
    case 'test_order':
        // 測試訂單查詢
        $orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? null;
        
        if (!$orderId) {
            returnResponse("請提供 order_id 參數", false);
        }
        
        try {
            $pdo = openpdo();
            $stmt = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if ($order) {
                // 隱藏敏感資訊
                unset($order['gameid']);
                returnResponse([
                    "order_found" => true,
                    "order_data" => $order
                ]);
            } else {
                returnResponse([
                    "order_found" => false,
                    "message" => "找不到指定的訂單"
                ], false);
            }
        } catch (Exception $e) {
            returnResponse([
                "error" => "查詢訂單時發生錯誤",
                "message" => $e->getMessage()
            ], false);
        }
        break;
        
    case 'test_game_db':
        // 測試遊戲資料庫連線
        $serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? null;
        
        if (!$serverId) {
            returnResponse("請提供 server_id 參數", false);
        }
        
        try {
            $pdo = openpdo();
            $stmt = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch();
            
            if (!$server) {
                returnResponse("找不到指定的伺服器", false);
            }
            
            // 測試遊戲資料庫連線
            $gamepdo = opengamepdo(
                $server["db_ip"],
                $server["db_port"],
                $server["db_name"],
                $server["db_user"],
                $server["db_pass"]
            );
            
            if ($gamepdo) {
                // 測試查詢
                if ($server["paytable"] == "ezpay") {
                    $testStmt = $gamepdo->query("SHOW TABLES LIKE 'ezpay'");
                    $tableExists = $testStmt->fetch() ? true : false;
                } else {
                    $testStmt = $gamepdo->query("SHOW TABLES LIKE 'shop_user'");
                    $tableExists = $testStmt->fetch() ? true : false;
                }
                
                returnResponse([
                    "game_db_connection" => "成功",
                    "server_info" => [
                        "host" => $server["db_ip"] . ":" . $server["db_port"],
                        "database" => $server["db_name"],
                        "paytable" => $server["paytable"]
                    ],
                    "table_exists" => $tableExists
                ]);
            } else {
                returnResponse("無法連線至遊戲資料庫", false);
            }
            
        } catch (Exception $e) {
            returnResponse([
                "error" => "測試遊戲資料庫時發生錯誤",
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ], false);
        }
        break;
        
    case 'get_game_data':
        // 撈取遊戲資料庫中對應資料表的最後5筆資料
        $serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? null;
        
        if (!$serverId) {
            returnResponse("請提供 server_id 參數", false);
        }
        
        try {
            $pdo = openpdo();
            $stmt = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch();
            
            if (!$server) {
                returnResponse("找不到指定的伺服器", false);
            }
            
            // 連線至遊戲資料庫
            $gamepdo = opengamepdo(
                $server["db_ip"],
                $server["db_port"],
                $server["db_name"],
                $server["db_user"],
                $server["db_pass"]
            );
            
            if (!$gamepdo) {
                returnResponse("無法連線至遊戲資料庫", false);
            }
            
            // 根據 paytable 決定要查詢的資料表
            $tableName = ($server["paytable"] == "ezpay") ? "ezpay" : "shop_user";
            
            // 檢查資料表是否存在
            $checkStmt = $gamepdo->query("SHOW TABLES LIKE '{$tableName}'");
            if (!$checkStmt->fetch()) {
                returnResponse("資料表 {$tableName} 不存在", false);
            }
            
            // 查詢最後5筆資料
            if ($server["paytable"] == "ezpay") {
                // ezpay 資料表結構
                $dataStmt = $gamepdo->query("SELECT * FROM ezpay ORDER BY id DESC LIMIT 5");
            } else {
                // shop_user 資料表結構  
                $dataStmt = $gamepdo->query("SELECT * FROM shop_user ORDER BY id DESC LIMIT 5");
            }
            
            $gameData = $dataStmt->fetchAll();
            
            // 隱藏敏感資訊
            foreach ($gameData as &$row) {
                if (isset($row['password'])) {
                    $row['password'] = '***隱藏***';
                }
                if (isset($row['token'])) {
                    $row['token'] = '***隱藏***';
                }
            }
            
            returnResponse([
                "server_info" => [
                    "server_id" => $serverId,
                    "host" => $server["db_ip"] . ":" . $server["db_port"],
                    "database" => $server["db_name"],
                    "table" => $tableName,
                    "paytable" => $server["paytable"]
                ],
                "data_count" => count($gameData),
                "latest_records" => $gameData
            ]);
            
        } catch (Exception $e) {
            returnResponse([
                "error" => "撈取遊戲資料時發生錯誤",
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine()
            ], false);
        }
        break;
        
    case 'php_info':
        // PHP 環境資訊
        returnResponse([
            "php_version" => phpversion(),
            "extensions" => [
                "pdo" => extension_loaded('pdo'),
                "pdo_mysql" => extension_loaded('pdo_mysql'),
                "json" => extension_loaded('json'),
                "mbstring" => extension_loaded('mbstring')
            ],
            "memory_limit" => ini_get('memory_limit'),
            "max_execution_time" => ini_get('max_execution_time'),
            "file_uploads" => ini_get('file_uploads'),
            "upload_max_filesize" => ini_get('upload_max_filesize')
        ]);
        break;
        
    case 'simulate_pay':
        // 模擬支付測試 (僅檢查流程，不實際寫入)
        $testOrderId = $_GET['order_id'] ?? $_POST['order_id'] ?? null;
        
        if (!$testOrderId) {
            returnResponse("請提供 order_id 參數", false);
        }
        
        $steps = [];
        
        try {
            // 步驟 1: 檢查訂單
            $pdo = openpdo();
            $stmt = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ?");
            $stmt->execute([$testOrderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $steps[] = "❌ 步驟1: 訂單不存在";
                returnResponse(["steps" => $steps], false);
            }
            $steps[] = "✅ 步驟1: 訂單查詢成功";
            
            // 步驟 2: 檢查訂單狀態
            if ($order["stats"] != 0) {
                $steps[] = "❌ 步驟2: 訂單狀態不正確 (當前: {$order["stats"]})";
                returnResponse(["steps" => $steps], false);
            }
            $steps[] = "✅ 步驟2: 訂單狀態正確";
            
            // 步驟 3: 檢查伺服器設定
            $stmt = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
            $stmt->execute([$order["foran"]]);
            $server = $stmt->fetch();
            
            if (!$server) {
                $steps[] = "❌ 步驟3: 伺服器設定不存在";
                returnResponse(["steps" => $steps], false);
            }
            $steps[] = "✅ 步驟3: 伺服器設定存在";
            
            // 步驟 4: 測試遊戲資料庫連線
            $gamepdo = opengamepdo(
                $server["db_ip"],
                $server["db_port"], 
                $server["db_name"],
                $server["db_user"],
                $server["db_pass"]
            );
            
            if (!$gamepdo) {
                $steps[] = "❌ 步驟4: 遊戲資料庫連線失敗";
                returnResponse(["steps" => $steps], false);
            }
            $steps[] = "✅ 步驟4: 遊戲資料庫連線成功";
            
            // 步驟 5: 檢查目標資料表
            $tableName = ($server["paytable"] == "ezpay") ? "ezpay" : "shop_user";
            $stmt = $gamepdo->query("SHOW TABLES LIKE '{$tableName}'");
            if (!$stmt->fetch()) {
                $steps[] = "❌ 步驟5: 目標資料表 {$tableName} 不存在";
                returnResponse(["steps" => $steps], false);
            }
            $steps[] = "✅ 步驟5: 目標資料表 {$tableName} 存在";
            
            $steps[] = "🎉 所有檢查通過，支付流程應該可以正常執行";
            
            returnResponse([
                "simulation_result" => "成功",
                "steps" => $steps,
                "order_info" => [
                    "order_id" => $testOrderId,
                    "money" => $order["money"],
                    "bmoney" => $order["bmoney"],
                    "gameid" => "***隱藏***",
                    "paytable" => $server["paytable"]
                ]
            ]);
            
        } catch (Exception $e) {
            $steps[] = "❌ 發生異常: " . $e->getMessage();
            returnResponse([
                "simulation_result" => "失敗",
                "steps" => $steps,
                "error" => $e->getMessage()
            ], false);
        }
        break;
        
    case 'help':
    default:
        // 顯示可用的除錯功能
        returnResponse([
            "available_actions" => [
                "logs" => "查看最新日誌 (?action=logs&lines=50)",
                "clear_logs" => "清空日誌 (?action=clear_logs)",
                "test_db" => "測試主資料庫連線 (?action=test_db)",
                "test_order" => "測試訂單查詢 (?action=test_order&order_id=123)",
                "test_game_db" => "測試遊戲資料庫連線 (?action=test_game_db&server_id=1)",
                "get_game_data" => "撈取遊戲資料庫最後5筆資料 (?action=get_game_data&server_id=1)",
                "test_list_basic" => "基本測試 list.php 是否正常運作 (?action=test_list_basic)",
                "test_manual_payment" => "測試手動付款完整流程 (?action=test_manual_payment&order_id=123&is_mock=1)",
                "php_info" => "查看 PHP 環境資訊 (?action=php_info)",
                "simulate_pay" => "模擬支付流程 (?action=simulate_pay&order_id=123)",
                "help" => "顯示此說明 (?action=help)"
            ],
            "examples" => [
                "debug.php?action=logs",
                "debug.php?action=test_db",
                "debug.php?action=get_game_data&server_id=1",
                "debug.php?action=simulate_pay&order_id=YOUR_ORDER_ID"
            ]
        ]);
        break;
}
?>
