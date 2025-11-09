<?php
/**
 * Funpoint 伺服器端回調接收頁面
 * 接收 FunPoint 的支付結果通知，處理訂單並發放遊戲虛擬貨幣
 */

include("myadm/include.php");
require_once('src/payment_class.php');
require_once('funpoint_logger.php');

// 回調成功回應
$endstr = '1|OK';

// ========================================
// 1. 接收回調參數
// ========================================
$MerchantID = $_REQUEST["MerchantID"] ?? '';
$MerchantTradeNo = $_REQUEST["MerchantTradeNo"] ?? '';
$RtnCode = $_REQUEST["RtnCode"] ?? '';
$RtnMsg = $_REQUEST["RtnMsg"] ?? '';
$rCheckMacValue = $_REQUEST["CheckMacValue"] ?? '';
$TradeAmt = $_REQUEST["TradeAmt"] ?? '';
$PaymentDate = $_REQUEST["PaymentDate"] ?? '';
$PaymentTypeChargeFee = $_REQUEST["PaymentTypeChargeFee"] ?? 0;

// 連接資料庫
$pdo = openpdo();

try {
    // ========================================
    // 2. 開始事務
    // ========================================
    $pdo->beginTransaction();

    // ========================================
    // 3. 查詢訂單記錄（FOR UPDATE 鎖定）
    // ========================================
    $query = $pdo->prepare("SELECT * FROM servers_log WHERE orderid = ? FOR UPDATE");
    $query->execute(array($MerchantTradeNo));

    if (!$datalist = $query->fetch()) {
        log_funpoint_error('ORDER_NOT_FOUND', '找不到訂單記錄', [
            'order_id' => $MerchantTradeNo,
            'merchant_id' => $MerchantID
        ]);
        die("0");
    }

    // 檢查訂單是否已處理（允許測試重複提交）
    if ($datalist["stats"] != 0 && $_POST["mockpay"] != 1) {
        log_funpoint_error('ORDER_ALREADY_PROCESSED', '訂單已處理', [
            'order_id' => $MerchantTradeNo,
            'current_status' => $datalist["stats"]
        ]);
        die("0");
    }

    // ========================================
    // 4. 記錄回調接收
    // ========================================
    log_funpoint_transaction($MerchantTradeNo, 'callback_received', [
        'merchant_id' => $MerchantID,
        'rtn_code' => $RtnCode,
        'rtn_msg' => $RtnMsg,
        'trade_amt' => $TradeAmt
    ]);

    // ========================================
    // 5. 取得伺服器設定
    // ========================================
    $foran = $datalist["foran"];
    $paytype = $datalist["paytype"];
    $money = $datalist["money"];
    $gameid = $datalist["gameid"];

    $qquery = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
    $qquery->execute(array($foran));

    if (!$server_info = $qquery->fetch()) {
        log_funpoint_error('SERVER_NOT_FOUND', 'CheckMacValue 驗證失敗：找不到伺服器設定', [
            'order_id' => $MerchantTradeNo,
            'foran' => $foran
        ]);
        die("0");
    }

    // ========================================
    // 6. 根據支付類型取得 HashKey 和 HashIV
    // ========================================
    $HashKey = '';
    $HashIV = '';

    if ($paytype == 5) {
        // 信用卡
        $env = $server_info["gstats"];
        if ($env == 1) {
            $HashKey = $server_info["HashKey"];
            $HashIV = $server_info["HashIV"];
        } else {
            $HashKey = "265flDjIvesceXWM";
            $HashIV = "pOOvhGd1V2pJbjfX";
        }
    } else if ($paytype == 2) {
        // ATM 轉帳
        $env = $server_info["gstats_bank"] ?? $server_info["gstats2"];
        if ($env == 1) {
            $HashKey = $server_info["HashKey2"] ?? $server_info["HashKey"];
            $HashIV = $server_info["HashIV2"] ?? $server_info["HashIV"];
        } else {
            $HashKey = "265flDjIvesceXWM";
            $HashIV = "pOOvhGd1V2pJbjfX";
        }
    } else {
        // 其他支付方式
        $env = $server_info["gstats2"];
        if ($env == 1) {
            $HashKey = $server_info["HashKey2"];
            $HashIV = $server_info["HashIV2"];
        } else {
            $HashKey = "265flDjIvesceXWM";
            $HashIV = "pOOvhGd1V2pJbjfX";
        }
    }

    // ========================================
    // 7. CheckMacValue 驗證
    // ========================================
    $checkParams = $_REQUEST;
    unset($checkParams['CheckMacValue']);

    // 計算 CheckMacValue
    $calculatedCheckMacValue = funpoint::generate($checkParams, $HashKey, $HashIV);

    // 驗證 CheckMacValue
    if ($rCheckMacValue !== $calculatedCheckMacValue) {
        log_funpoint_error('CHECKMAC_VERIFY_FAILED', 'CheckMacValue 驗證失敗', [
            'order_id' => $MerchantTradeNo,
            'received' => $rCheckMacValue,
            'calculated' => $calculatedCheckMacValue,
            'paytype' => $paytype
        ]);

        // 更新訂單錯誤訊息
        $qud = $pdo->prepare("UPDATE servers_log SET errmsg = 'CheckMacValue 驗證失敗' WHERE orderid = ?");
        $qud->execute(array($MerchantTradeNo));

        $pdo->commit();
        die("0");
    }

    // ========================================
    // 8. CheckMacValue 驗證成功
    // ========================================
    log_funpoint_info('CheckMacValue 驗證成功', ['order_id' => $MerchantTradeNo]);

    // 判斷支付結果
    if ($RtnCode == 1) {
        $rstat = ($RtnMsg == '模擬付款成功') ? 3 : 1;
        log_funpoint_transaction($MerchantTradeNo, 'payment_success', [
            'is_test' => ($RtnMsg == '模擬付款成功'),
            'amount' => $TradeAmt,
            'charge_fee' => $PaymentTypeChargeFee
        ]);
    } else {
        $rstat = 2;
        log_funpoint_transaction($MerchantTradeNo, 'payment_fail', [
            'rtn_code' => $RtnCode,
            'rtn_msg' => $RtnMsg
        ]);
    }

    // ========================================
    // 9. 更新訂單狀態
    // ========================================
    $qud = $pdo->prepare("UPDATE servers_log SET stats = ?, hmoney = ?, paytimes = ?, rmoney = ?, rCheckMacValue = ?, RtnCode = ?, RtnMsg = ? WHERE orderid = ?");
    $rr = $qud->execute(array($rstat, $PaymentTypeChargeFee, $PaymentDate, $TradeAmt, $rCheckMacValue, $RtnCode, $RtnMsg, $MerchantTradeNo));

    // 測試狀態轉換為成功狀態（3->1）
    $rstat = ($rstat == 3) ? 1 : $rstat;

    // ========================================
    // 10. 發放遊戲虛擬貨幣（支付成功時）
    // ========================================
    if ($rr == 1 && $rstat == 1) {
        $qquerylog = $pdo->prepare("SELECT * FROM servers_log WHERE orderid = ?");
        $qquerylog->execute(array($MerchantTradeNo));
        $ddlog = $qquerylog->fetch();

        $money = $ddlog["money"];
        $bmoney = $ddlog["bmoney"];
        $gameid = $ddlog["gameid"];
        $paytype = $ddlog["paytype"];

        // 使用已查詢的 $server_info
        $dd = $server_info;
        $ip = $dd["db_ip"];
        $port = $dd["db_port"];
        $dbname = $dd["db_name"];
        $user = $dd["db_user"];
        $pass = $dd["db_pass"];
        $pid = $dd["db_pid"];
        $bonusid = $dd["db_bonusid"];
        $bonusrate = $dd["db_bonusrate"];

        // 取得資料表名稱
        $paytable = $dd["paytable"];
        $paytable = ($paytable == 'custom') ? $dd["paytable_custom"] : $paytable;

        // 連接遊戲資料庫
        $gamepdo = opengamepdo($ip, $port, $dbname, $user, $pass);

        // ========================================
        // 11. 處理不同的資料表類型
        // ========================================
        if ($dd["paytable"] == "ezpay") {
            // ezpay 表處理
            $gamei = array(':amount' => $bmoney, ':payname' => $gameid);
            $gameq = $gamepdo->prepare("INSERT INTO ezpay (amount, payname, state) VALUES(:amount,:payname, 1)");

            if (!$rr = $gameq->execute($gamei)) {
                $qud = $pdo->prepare("UPDATE servers_log SET errmsg = '存入贊助幣時發生錯誤' WHERE orderid = ?");
                $qud->execute(array($MerchantTradeNo));
                die("0");
            }

            $pdo->commit();
            echo $endstr;
            exit;
        }

        // ========================================
        // 12. 發放贊助幣
        // ========================================
        $card = ($paytype == 5) ? 1 : 0;
        $gamei = array(':p_id' => $pid, ':p_name' => '贊助幣', ':count' => $bmoney, ':account' => $gameid, ':r_count' => $money, ':card' => $card);
        $gameq = $gamepdo->prepare("INSERT INTO $paytable (p_id, p_name, count, account, r_count, card) VALUES(:p_id, :p_name, :count, :account, :r_count, :card)");

        if (!$gameq->execute($gamei)) {
            $qud = $pdo->prepare("UPDATE servers_log SET errmsg = '存入贊助幣時發生錯誤' WHERE orderid = ?");
            $qud->execute(array($MerchantTradeNo));
            die("0");
        }

        // ========================================
        // 13. 發放紅利幣
        // ========================================
        if (!empty($bonusid) && $bonusrate > 0) {
            $bonusmoney = $money * ($bonusrate / 100);
            $gamei = array(':p_id' => $bonusid, ':p_name' => '紅利幣', ':count' => $bonusmoney, ':account' => $gameid, ':r_count' => $money);
            $gameq = $gamepdo->prepare("INSERT INTO $paytable (p_id, p_name, count, account, r_count) VALUES(:p_id, :p_name, :count, :account, :r_count)");

            if (!$gameq->execute($gamei)) {
                $qud = $pdo->prepare("UPDATE servers_log SET errmsg = '存入紅利幣時發生錯誤' WHERE orderid = ?");
                $qud->execute(array($MerchantTradeNo));
                die("0");
            }
        }

        // ========================================
        // 14. 處理活動獎勵 - 滿額贈禮
        // ========================================
        $gift1 = 0;
        $qquerylog1 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 1 AND pid = 'stat'");
        $qquerylog1->execute(array($foran));

        if ($ddlog1 = $qquerylog1->fetch()) {
            if ($ddlog1["sizes"] == 1) {
                $gift1 = 1;
            }
        }

        if ($gift1 == 1) {
            $qquerylog1 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 1 AND pid != 'stat' FOR UPDATE");
            $qquerylog1->execute(array($foran));

            if ($ddlog1 = $qquerylog1->fetchAll()) {
                foreach ($ddlog1 as $ddl1) {
                    $m1 = $ddl1["m1"];
                    $m2 = $ddl1["m2"];
                    $pid = $ddl1["pid"];
                    $sizes = $ddl1["sizes"];

                    if ($money >= $m1 && $money <= $m2 && $sizes > 0) {
                        $gamepdo_add = $gamepdo->prepare("INSERT INTO $paytable (p_id, p_name, count, account) VALUES(?, ?, ?, ?)");
                        $gamepdo_add->execute(array($pid, '滿額贈禮', $sizes, $gameid));
                    }
                }
            }
        }

        // ========================================
        // 15. 處理活動獎勵 - 首購禮
        // ========================================
        $gift2 = 0;
        $qquerylog2 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 2 AND pid = 'stat'");
        $qquerylog2->execute(array($foran));

        if ($ddlog2 = $qquerylog2->fetch()) {
            if ($ddlog2["sizes"] == 1) {
                $gift2 = 1;
            }
        }

        if ($gift2 == 1) {
            // 確認是否為首購
            $gamepdo_query = $gamepdo->prepare("SELECT COUNT(*) FROM $paytable WHERE account = ? AND p_name = '贊助幣'");
            $gamepdo_query->execute(array($gameid));

            if ($gamepdo_query->fetchColumn() == 1) {
                $qquerylog2 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 2 AND pid != 'stat' FOR UPDATE");
                $qquerylog2->execute(array($foran));

                if ($ddlog2 = $qquerylog2->fetchAll()) {
                    foreach ($ddlog2 as $ddl2) {
                        $m1 = $ddl2["m1"];
                        $m2 = $ddl2["m2"];
                        $pid = $ddl2["pid"];
                        $sizes = $ddl2["sizes"];

                        if ($money >= $m1 && $money <= $m2 && $sizes > 0) {
                            $gamepdo_add = $gamepdo->prepare("INSERT INTO $paytable (p_id, p_name, count, account) VALUES(?, ?, ?, ?)");
                            $gamepdo_add->execute(array($pid, '首購禮', $sizes, $gameid));
                        }
                    }
                }
            }
        }

        // ========================================
        // 16. 處理活動獎勵 - 活動首購禮
        // ========================================
        $gift4 = 0;
        $gift4time = 0;
        $qquerylog4 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 4 AND pid = 'stat'");
        $qquerylog4->execute(array($foran));

        if ($ddlog4 = $qquerylog4->fetch()) {
            if ($ddlog4["sizes"] == 1) {
                $gift4 = 1;
            }
        }

        if ($gift4 == 1) {
            // 確認是否在活動時間內
            $qquerylog4t = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 4 AND pid IN ('time1', 'time2')");
            $qquerylog4t->execute(array($foran));
            $time41 = '';
            $time42 = '';

            if ($ddlog4tt = $qquerylog4t->fetchAll()) {
                foreach ($ddlog4tt as $ddlog4t) {
                    if ($ddlog4t["pid"] == "time1") {
                        $time41 = $ddlog4t["dd"];
                    }
                    if ($ddlog4t["pid"] == "time2") {
                        $time42 = $ddlog4t["dd"];
                    }
                }
            }

            if (strtotime($time41) !== false && strtotime($time42) !== false) {
                if (time() >= strtotime($time41) && time() <= strtotime($time42)) {
                    $gift4time = 1;
                }
            }

            if ($gift4time === 1) {
                // 確認是否為首購
                $gamepdo_query = $gamepdo->prepare("SELECT COUNT(*) FROM $paytable WHERE account = ? AND p_name = '贊助幣' AND create_time BETWEEN ? AND ?");
                $gamepdo_query->execute(array($gameid, $time41, $time42));

                if ($gamepdo_query->fetchColumn() == 1) {
                    $qquerylog4 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 4 AND pid != 'stat' FOR UPDATE");
                    $qquerylog4->execute(array($foran));

                    if ($ddlog4 = $qquerylog4->fetchAll()) {
                        foreach ($ddlog4 as $ddl4) {
                            $m1 = $ddl4["m1"];
                            $m2 = $ddl4["m2"];
                            $pid = $ddl4["pid"];
                            $sizes = $ddl4["sizes"];

                            if ($money >= $m1 && $money <= $m2 && $sizes > 0) {
                                $gamepdo_add = $gamepdo->prepare("INSERT INTO $paytable (p_id, p_name, count, account) VALUES(?, ?, ?, ?)");
                                $gamepdo_add->execute(array($pid, '活動首購禮', $sizes, $gameid));
                            }
                        }
                    }
                }
            }
        }

        // ========================================
        // 17. 處理活動獎勵 - 累積儲值
        // ========================================
        $gift3 = 0;
        $qquerylog3 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 3 AND pid = 'stat'");
        $qquerylog3->execute(array($foran));

        if ($ddlog3 = $qquerylog3->fetch()) {
            if ($ddlog3["sizes"] == 1) {
                $gift3 = 1;
            }
        }

        if ($gift3 == 1) {
            // 統計累積儲值金額
            $gamepdo_query = $gamepdo->prepare("SELECT SUM(r_count) FROM $paytable WHERE account = ? AND p_name = '贊助幣'");
            $gamepdo_query->execute(array($gameid));
            $total_pay = $gamepdo_query->fetchColumn();

            if ($total_pay > 0) {
                $qquerylog3 = $pdo->prepare("SELECT * FROM servers_gift WHERE foran = ? AND types = 3 AND pid != 'stat' FOR UPDATE");
                $qquerylog3->execute(array($foran));

                if ($ddlog3 = $qquerylog3->fetchAll()) {
                    foreach ($ddlog3 as $ddl3) {
                        $m1 = $ddl3["m1"];
                        $pid = $ddl3["pid"];
                        $sizes = $ddl3["sizes"];

                        if ($total_pay >= $m1 && $sizes > 0) {
                            $gamepdo_qq = $gamepdo->prepare("SELECT COUNT(*) FROM $paytable WHERE account = ? AND p_name = '累積儲值' AND r_count = ?");
                            $gamepdo_qq->execute(array($gameid, $m1));

                            if (!$gamepdo_qq->fetchColumn()) {
                                $gamepdo_add = $gamepdo->prepare("INSERT INTO $paytable (p_id, p_name, count, account, r_count) VALUES(?, ?, ?, ?, ?)");
                                $gamepdo_add->execute(array($pid, '累積儲值', $sizes, $gameid, $m1));
                            }
                        }
                    }
                }
            }
        }
    }

    // ========================================
    // 18. 提交事務
    // ========================================
    $pdo->commit();

    // ========================================
    // 19. 回應成功
    // ========================================
    echo $endstr;

} catch (Exception $e) {
    // 發生異常，回滾事務
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_funpoint_error('TRANSACTION_ERROR', '事務處理錯誤：' . $e->getMessage(), [
        'order_id' => $MerchantTradeNo,
        'error' => $e->getMessage()
    ]);

    die("0");
}
?>
