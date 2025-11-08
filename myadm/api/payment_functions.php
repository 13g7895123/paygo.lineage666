<?php
// 處理 shop_user 類型的遊戲幣發放
function processShopUserPayment($orderData, $pdo) {
    try {
        // 連接遊戲資料庫
        $gamepdo = opengamepdo($orderData["db_ip"], $orderData["db_port"], $orderData["db_name"], $orderData["db_user"], $orderData["db_pass"]);
        if (!$gamepdo) {
            return ['success' => false, 'error' => ['message' => '無法連接遊戲資料庫', 'connection_info' => [$orderData["db_ip"], $orderData["db_port"], $orderData["db_name"]]]];
        }

        // 發放贊助幣
        $card = ($orderData["paytype"] == 5) ? 1 : 0;
        $stmt = $gamepdo->prepare("INSERT INTO shop_user (p_id, p_name, count, account, r_count, card) VALUES (?, '贊助幣', ?, ?, ?, ?)");
        $result = $stmt->execute([$orderData["db_pid"], $orderData["bmoney"], $orderData["gameid"], $orderData["money"], $card]);
        
        if (!$result) {
            return ['success' => false, 'error' => ['message' => '插入 shop_user 失敗', 'sql_error' => $stmt->errorInfo()]];
        }

        return ['success' => true, 'details' => ['affected_rows' => $stmt->rowCount(), 'p_id' => $orderData["db_pid"], 'amount' => $orderData["bmoney"]]];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]];
    }
}

// 處理 ezpay 類型的遊戲幣發放
function processEzpayPayment($orderData, $pdo) {
    try {
        // 連接遊戲資料庫
        $gamepdo = opengamepdo($orderData["db_ip"], $orderData["db_port"], $orderData["db_name"], $orderData["db_user"], $orderData["db_pass"]);
        if (!$gamepdo) {
            return ['success' => false, 'error' => ['message' => '無法連接遊戲資料庫', 'connection_info' => [$orderData["db_ip"], $orderData["db_port"], $orderData["db_name"]]]];
        }

        // 發放到 ezpay 資料表
        $stmt = $gamepdo->prepare("INSERT INTO ezpay (amount, payname) VALUES (?, ?)");
        $result = $stmt->execute([$orderData["bmoney"], $orderData["gameid"]]);
        
        if (!$result) {
            return ['success' => false, 'error' => ['message' => '插入 ezpay 失敗', 'sql_error' => $stmt->errorInfo()]];
        }

        return ['success' => true, 'details' => ['affected_rows' => $stmt->rowCount(), 'amount' => $orderData["bmoney"]]];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]];
    }
}
?>
