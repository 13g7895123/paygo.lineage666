<?php
/**
 * 資料庫結構檢查 API
 * 檢查 Funpoint 金流所需的資料表欄位是否存在
 */

header('Content-Type: application/json; charset=utf-8');

include("../include.php");

// 檢查登入（API 使用 session）
if (empty($_SESSION["adminid"])) {
    echo json_encode([
        'success' => false,
        'error' => '未登入'
    ]);
    exit;
}

$pdo = openpdo();

// 定義所需的資料表結構
$schema = [
    'servers' => [
        'display_name' => 'servers（伺服器設定表）',
        'fields' => [
            // 信用卡支付設定
            ['name' => 'MerchantID', 'type' => 'VARCHAR(50)', 'description' => '商店代號（信用卡）'],
            ['name' => 'HashKey', 'type' => 'VARCHAR(100)', 'description' => '金鑰（信用卡）'],
            ['name' => 'HashIV', 'type' => 'VARCHAR(100)', 'description' => '向量（信用卡）'],
            ['name' => 'gstats', 'type' => 'INT(11)', 'description' => '環境設定（信用卡，0=測試 1=正式）'],

            // 其他支付方式設定
            ['name' => 'MerchantID2', 'type' => 'VARCHAR(50)', 'description' => '商店代號（其他支付）'],
            ['name' => 'HashKey2', 'type' => 'VARCHAR(100)', 'description' => '金鑰（其他支付）'],
            ['name' => 'HashIV2', 'type' => 'VARCHAR(100)', 'description' => '向量（其他支付）'],
            ['name' => 'gstats2', 'type' => 'INT(11)', 'description' => '環境設定（其他支付，0=測試 1=正式）'],

            // 銀行轉帳設定
            ['name' => 'gstats_bank', 'type' => 'INT(11)', 'description' => '環境設定（銀行轉帳，0=測試 1=正式）'],

            // 遊戲資料庫連線設定
            ['name' => 'db_ip', 'type' => 'VARCHAR(50)', 'description' => '遊戲資料庫 IP'],
            ['name' => 'db_port', 'type' => 'INT(11)', 'description' => '遊戲資料庫 Port'],
            ['name' => 'db_name', 'type' => 'VARCHAR(50)', 'description' => '遊戲資料庫名稱'],
            ['name' => 'db_user', 'type' => 'VARCHAR(50)', 'description' => '遊戲資料庫帳號'],
            ['name' => 'db_pass', 'type' => 'VARCHAR(100)', 'description' => '遊戲資料庫密碼'],
            ['name' => 'db_pid', 'type' => 'VARCHAR(50)', 'description' => '贊助幣物品 ID'],
            ['name' => 'db_bonusid', 'type' => 'VARCHAR(50)', 'description' => '紅利幣物品 ID'],
            ['name' => 'db_bonusrate', 'type' => 'DECIMAL(10,2)', 'description' => '紅利幣比例'],

            // 其他設定
            ['name' => 'paytable', 'type' => 'VARCHAR(50)', 'description' => '支付記錄表名稱'],
            ['name' => 'paytable_custom', 'type' => 'VARCHAR(50)', 'description' => '自訂表名稱'],
            ['name' => 'products', 'type' => 'TEXT', 'description' => '商品名稱清單'],
            ['name' => 'custombg', 'type' => 'VARCHAR(100)', 'description' => '自訂背景圖片'],
        ]
    ],

    'servers_log' => [
        'display_name' => 'servers_log（訂單記錄表）',
        'fields' => [
            ['name' => 'auton', 'type' => 'INT(11)', 'description' => '訂單記錄 ID（主鍵）'],
            ['name' => 'orderid', 'type' => 'VARCHAR(50)', 'description' => '訂單編號（MerchantTradeNo）'],
            ['name' => 'foran', 'type' => 'INT(11)', 'description' => '伺服器 ID'],
            ['name' => 'gameid', 'type' => 'VARCHAR(50)', 'description' => '遊戲帳號'],
            ['name' => 'money', 'type' => 'INT(11)', 'description' => '訂單金額'],
            ['name' => 'bmoney', 'type' => 'INT(11)', 'description' => '實際發放金額'],
            ['name' => 'paytype', 'type' => 'INT(11)', 'description' => '支付類型'],
            ['name' => 'stats', 'type' => 'INT(11)', 'description' => '訂單狀態（0=未處理 1=成功 2=失敗 3=測試成功）'],
            ['name' => 'CheckMacValue', 'type' => 'VARCHAR(255)', 'description' => '請求驗證碼'],
            ['name' => 'rCheckMacValue', 'type' => 'VARCHAR(255)', 'description' => '回傳驗證碼'],
            ['name' => 'RtnCode', 'type' => 'INT(11)', 'description' => '回傳碼'],
            ['name' => 'RtnMsg', 'type' => 'VARCHAR(255)', 'description' => '回傳訊息'],
            ['name' => 'hmoney', 'type' => 'DECIMAL(10,2)', 'description' => '手續費'],
            ['name' => 'rmoney', 'type' => 'INT(11)', 'description' => '實收金額'],
            ['name' => 'paytimes', 'type' => 'DATETIME', 'description' => '支付時間'],
            ['name' => 'PaymentNo', 'type' => 'VARCHAR(100)', 'description' => '繳費代碼/帳號'],
            ['name' => 'ExpireDate', 'type' => 'VARCHAR(50)', 'description' => '繳費期限'],
            ['name' => 'forname', 'type' => 'VARCHAR(100)', 'description' => '伺服器名稱'],
            ['name' => 'errmsg', 'type' => 'TEXT', 'description' => '錯誤訊息'],
            ['name' => 'createtime', 'type' => 'DATETIME', 'description' => '建立時間'],
            ['name' => 'token', 'type' => 'VARCHAR(255)', 'description' => 'Token（安全驗證用）'],
        ]
    ],

    'servers_gift' => [
        'display_name' => 'servers_gift（活動獎勵設定表）',
        'fields' => [
            ['name' => 'foran', 'type' => 'INT(11)', 'description' => '伺服器 ID'],
            ['name' => 'types', 'type' => 'INT(11)', 'description' => '獎勵類型（1=滿額 2=首購 3=累積 4=活動首購）'],
            ['name' => 'pid', 'type' => 'VARCHAR(50)', 'description' => '物品 ID 或設定標識（stat=開關）'],
            ['name' => 'm1', 'type' => 'INT(11)', 'description' => '金額下限（或累積儲值門檻）'],
            ['name' => 'm2', 'type' => 'INT(11)', 'description' => '金額上限'],
            ['name' => 'sizes', 'type' => 'INT(11)', 'description' => '數量（或開關狀態）'],
            ['name' => 'dd', 'type' => 'DATETIME', 'description' => '時間設定（活動首購用）'],
        ]
    ]
];

// 檢查每個資料表的欄位
$results = [
    'success' => true,
    'tables' => [],
    'summary' => [
        'total_tables' => 0,
        'total_fields' => 0,
        'existing_fields' => 0,
        'missing_fields' => 0
    ]
];

try {
    foreach ($schema as $tableName => $tableInfo) {
        $results['summary']['total_tables']++;

        // 檢查資料表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        $tableExists = $stmt->rowCount() > 0;

        if (!$tableExists) {
            $results['tables'][$tableName] = [
                'display_name' => $tableInfo['display_name'],
                'table_exists' => false,
                'fields' => []
            ];
            continue;
        }

        // 取得資料表的所有欄位
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $existingFields = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingFields[$row['Field']] = $row['Type'];
        }

        // 檢查每個必要欄位
        $tableResult = [
            'display_name' => $tableInfo['display_name'],
            'table_exists' => true,
            'fields' => []
        ];

        foreach ($tableInfo['fields'] as $fieldInfo) {
            $results['summary']['total_fields']++;

            $fieldExists = isset($existingFields[$fieldInfo['name']]);
            $currentType = $fieldExists ? $existingFields[$fieldInfo['name']] : null;

            $tableResult['fields'][] = [
                'name' => $fieldInfo['name'],
                'type' => $fieldInfo['type'],
                'description' => $fieldInfo['description'],
                'exists' => $fieldExists,
                'current_type' => $currentType
            ];

            if ($fieldExists) {
                $results['summary']['existing_fields']++;
            } else {
                $results['summary']['missing_fields']++;
            }
        }

        $results['tables'][$tableName] = $tableResult;
    }

} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
