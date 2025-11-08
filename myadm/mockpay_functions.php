<?php
/**
 * mockpay 共用函式庫
 * 將 mockpay 的處理邏輯提取為可重複使用的函式
 */

/**
 * 生成模擬付款參數
 * @param array $datainfo 訂單資訊
 * @param string $pay_cp 金流類型
 * @return array 回傳 [tourl, params, endstr]
 */
function generateMockPayParams($datainfo, $pay_cp) {
    $nowtime = date("Y/m/d H:i:s");
    $tourl = "";
    $params = [];
    $endstr = "1|OK";
    
    switch ($pay_cp) {
        case "ecpay":
            $tourl = "r.php";
            $params = [
                'MerchantTradeNo' => $datainfo["orderid"],
                'RtnCode' => 1,
                'RtnMsg' => '模擬付款成功',
                'CheckMacValue' => 'system',
                'TradeAmt' => $datainfo["money"],
                'PaymentDate' => $nowtime,
                'PaymentTypeChargeFee' => 0
            ];
            break;
            
        case "ebpay":
            $foran = $datainfo["foran"];
            $result = [
                'MerchantOrderNo' => $datainfo["orderid"],
                'CheckCode' => 'system',
                'Amt' => $datainfo["money"],
                'PayTime' => $nowtime
            ];
            
            $tradeInfo = [
                'Status' => 'SUCCESS',
                'Message' => '模擬付款成功',
                'Result' => $result,
            ];
            
            $tourl = "ebpay_r.php?an=".$foran;
            $params = [
                'Status' => 'SUCCESS',
                'TradeInfo' => 'mock_encrypted_data'  // 簡化處理
            ];
            break;
            
        case "pchome":
            $result = [
                'status' => 'S',
                'order_id' => $datainfo["orderid"],
                'trade_amount' => $datainfo["money"],
                'pay_date' => $nowtime,
                'pp_fee' => 0
            ];
            
            $tourl = "pchome_r.php";
            $params = [
                'notify_type' => 'order_confirm',
                'notify_message' => json_encode($result)
            ];
            break;
            
        // 其他金流類型...
        default:
            return null;
    }
    
    $params["mockpay"] = 1;
    
    return [
        'tourl' => $tourl,
        'params' => $params,
        'endstr' => $endstr
    ];
}

/**
 * 執行模擬付款
 * @param string $orderid 訂單編號
 * @return array 回傳結果
 */
function executeMockPay($orderid) {
    $pdo = openpdo();
    $stmt = $pdo->prepare("SELECT * FROM servers_log WHERE orderid = ?");
    $stmt->execute([$orderid]);
    
    if (!$datainfo = $stmt->fetch()) {
        return ['success' => false, 'message' => '訂單不存在'];
    }
    
    if ($datainfo["stats"] != 0 && $datainfo["stats"] != 2) {
        return ['success' => false, 'message' => '付款狀態不符'];
    }
    
    $mockParams = generateMockPayParams($datainfo, $datainfo["pay_cp"]);
    if (!$mockParams) {
        return ['success' => false, 'message' => '不支援的金流類型'];
    }
    
    return [
        'success' => true,
        'message' => '模擬付款參數生成成功',
        'data' => $mockParams
    ];
}
?>
