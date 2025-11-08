<?php
/**
 * Funpoint 支付處理頁面（直接串接版本）
 * 移除跳板依賴，直接與 FunPoint API 通訊
 */

session_start();
require_once('myadm/include.php');
require_once('src/payment_class.php');
require_once('src/funpoint_logger.php');

// 1. 檢查 SESSION 資料
$foran = $_SESSION["foran"] ?? null;
$serverid = $_SESSION["serverid"] ?? null;
$lastan = $_SESSION["lastan"] ?? null;

if (!$foran) {
    log_funpoint_error('8000201', '伺服器資料錯誤：foran 為空', ['session' => $_SESSION]);
    die("伺服器資料錯誤-8000201");
}
if (!$serverid) {
    log_funpoint_error('8000202', '伺服器資料錯誤：serverid 為空', ['session' => $_SESSION]);
    die("伺服器資料錯誤-8000202");
}
if (!$lastan) {
    log_funpoint_error('8000203', '伺服器資料錯誤：lastan 為空', ['session' => $_SESSION]);
    die("伺服器資料錯誤-8000203");
}

// 2. 連接資料庫
$pdo = openpdo();

// 3. 查詢訂單記錄
$stmt = $pdo->prepare("SELECT * FROM servers_log WHERE auton = ?");
$stmt->execute([$lastan]);
$server_log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$server_log) {
    log_funpoint_error('8000207', '不明錯誤：找不到訂單記錄', ['lastan' => $lastan]);
    die("不明錯誤-8000207");
}

// 檢查訂單狀態
if ($server_log["stats"] != 0) {
    log_funpoint_error('8000208', '金流狀態有誤：訂單已處理', [
        'order_id' => $server_log["orderid"],
        'stats' => $server_log["stats"]
    ]);
    die("金流狀態有誤-8000208");
}

// 4. 取得支付類型和金額
$paytype = $server_log["paytype"];
$money = $server_log["money"];
$gameid = $server_log["gameid"];
$orderid = $server_log["orderid"];

// 5. 取得伺服器設定
$stmt = $pdo->prepare("SELECT * FROM servers WHERE auton = ?");
$stmt->execute([$foran]);
$server_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$server_info) {
    log_funpoint_error('8000204', '不明錯誤：找不到伺服器記錄', ['foran' => $foran]);
    die("不明錯誤-8000204");
}

// 6. 根據支付類型取得商店設定
$MerchantID = '';
$HashKey = '';
$HashIV = '';
$env = 0;

if ($paytype == 5) {
    // 信用卡
    $env = $server_info["gstats"];
    if ($env == 1) {
        $MerchantID = $server_info["MerchantID"];
        $HashKey = $server_info["HashKey"];
        $HashIV = $server_info["HashIV"];
    } else {
        // 測試環境
        $MerchantID = "1000031";
        $HashKey = "265flDjIvesceXWM";
        $HashIV = "pOOvhGd1V2pJbjfX";
    }
} else if ($paytype == 2) {
    // ATM 轉帳（從 bank_funds 取得或使用 servers 表的設定）
    $env = $server_info["gstats_bank"] ?? $server_info["gstats2"];
    if ($env == 1) {
        // 正式環境 - 優先從 bank_funds 取得，否則使用 servers 表
        // 這裡簡化處理，直接使用 servers 表的設定
        $MerchantID = $server_info["MerchantID2"] ?? $server_info["MerchantID"];
        $HashKey = $server_info["HashKey2"] ?? $server_info["HashKey"];
        $HashIV = $server_info["HashIV2"] ?? $server_info["HashIV"];
    } else {
        $MerchantID = "1000031";
        $HashKey = "265flDjIvesceXWM";
        $HashIV = "pOOvhGd1V2pJbjfX";
    }
} else {
    // 其他支付方式（超商代碼、條碼、ibon、WebATM）
    $env = $server_info["gstats2"];
    if ($env == 1) {
        $MerchantID = $server_info["MerchantID2"];
        $HashKey = $server_info["HashKey2"];
        $HashIV = $server_info["HashIV2"];
    } else {
        $MerchantID = "1000031";
        $HashKey = "265flDjIvesceXWM";
        $HashIV = "pOOvhGd1V2pJbjfX";
    }
}

// 檢查商店資訊
if (!$MerchantID || !$HashKey || !$HashIV) {
    log_funpoint_error('8000206', '金流錯誤：商店資訊不完整', [
        'paytype' => $paytype,
        'has_merchant_id' => !empty($MerchantID),
        'has_hashkey' => !empty($HashKey),
        'has_hashiv' => !empty($HashIV)
    ]);
    die("金流錯誤-8000206");
}

// 7. 設定支付方式
$paytype_mapping = [
    1 => ['ChoosePayment' => 'BARCODE', 'ChooseSubPayment' => 'BARCODE'],
    2 => ['ChoosePayment' => 'ATM', 'ChooseSubPayment' => 'ESUN'],
    3 => ['ChoosePayment' => 'CVS', 'ChooseSubPayment' => 'CVS'],
    4 => ['ChoosePayment' => 'CVS', 'ChooseSubPayment' => 'IBON'],
    5 => ['ChoosePayment' => 'Credit', 'ChooseSubPayment' => ''],
    6 => ['ChoosePayment' => 'WebATM', 'ChooseSubPayment' => '']
];

$ptt = $paytype_mapping[$paytype]['ChoosePayment'] ?? 'Credit';
$csp = $paytype_mapping[$paytype]['ChooseSubPayment'] ?? '';

// 8. 組合支付參數
$nowtime = date("Y/m/d H:i:s");
$ItemName = random_products();  // 隨機商品名稱
$TradeDesc = "帳單中心";
$tradeno = $orderid;

// 設定 URL（直接指向自己的網站）
$base_url = rtrim($weburl, '/');  // 使用 include.php 中的 $weburl
$rurl = $base_url . "/src/funpoint_r.php";           // 伺服器端通知
$rurl2 = $base_url . "/src/funpoint_payok.php";      // 前端返回

// 設定 FunPoint API URL
$payment_url = ($env == 1)
    ? "https://payment.funpoint.com.tw/Cashier/AioCheckOut/V5"
    : "https://payment-stage.funpoint.com.tw/Cashier/AioCheckOut/V5";

$CheckMacData = [
    'ChoosePayment'      => $ptt,
    'ChooseSubPayment'   => $csp,
    'ClientRedirectURL'  => $rurl2,
    'EncryptType'        => 1,
    'ItemName'           => $ItemName,
    'MerchantID'         => $MerchantID,
    'MerchantTradeDate'  => $nowtime,
    'MerchantTradeNo'    => $tradeno,
    'PaymentType'        => 'aio',
    'ReturnURL'          => $rurl,
    'TotalAmount'        => $money,
    'TradeDesc'          => $TradeDesc
];

// 9. 生成 CheckMacValue
$CheckMacValue = funpoint::generate($CheckMacData, $HashKey, $HashIV);
$CheckMacData['CheckMacValue'] = $CheckMacValue;

// 10. 更新訂單記錄
$stmt = $pdo->prepare("UPDATE servers_log SET CheckMacValue = ?, forname = ? WHERE auton = ?");
$stmt->execute([$CheckMacValue, $server_info["names"], $lastan]);

// 記錄交易開始
log_funpoint_transaction($orderid, 'payment_start', [
    'paytype' => $paytype,
    'money' => $money,
    'merchant_id' => $MerchantID,
    'environment' => $env == 1 ? 'production' : 'test'
]);

// 11. 根據支付類型處理
if ($ptt == 'CVS' || $ptt == 'BARCODE') {
    // 便利商店：直接 POST 到 FunPoint API，輸出結果
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $payment_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($CheckMacData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $curl_error = curl_error($ch);
        log_funpoint_error('CURL_ERROR', 'FunPoint API 連線錯誤', [
            'order_id' => $orderid,
            'curl_error' => $curl_error,
            'paytype' => $paytype
        ]);
        curl_close($ch);
        die("FunPoint API 連線錯誤: " . $curl_error);
    }

    curl_close($ch);

    // 記錄 CVS/BARCODE 回應
    log_funpoint_info('CVS/BARCODE API 回應', [
        'order_id' => $orderid,
        'response_length' => strlen($response)
    ]);

    echo $response;
} else {
    // ATM / Credit / WebATM：產生表單並自動提交
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>處理中...</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f5f5f5;
            }
            .loading {
                text-align: center;
            }
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="loading">
            <div class="spinner"></div>
            <p>處理中，請稍候...</p>
            <p>即將跳轉至支付頁面</p>
        </div>
        <form id="payment_form" method="post" action="<?= htmlspecialchars($payment_url) ?>">
            <?php foreach ($CheckMacData as $key => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
        </form>
        <script>
            // 自動提交表單
            document.getElementById('payment_form').submit();
        </script>
    </body>
    </html>
    <?php
}
?>
