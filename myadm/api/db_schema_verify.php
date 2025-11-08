<?php
/**
 * 資料庫結構驗證 API
 * 更新後驗證所有欄位是否都正確
 */

header('Content-Type: application/json; charset=utf-8');

include("../include.php");

// 檢查登入
if (empty($_SESSION["adminid"])) {
    echo json_encode([
        'success' => false,
        'error' => '未登入'
    ]);
    exit;
}

$pdo = openpdo();

// 定義所需的資料表結構（與 check 相同）
$requiredFields = [
    'servers' => [
        'MerchantID', 'HashKey', 'HashIV', 'gstats',
        'MerchantID2', 'HashKey2', 'HashIV2', 'gstats2',
        'gstats_bank',
        'db_ip', 'db_port', 'db_name', 'db_user', 'db_pass',
        'db_pid', 'db_bonusid', 'db_bonusrate',
        'paytable', 'paytable_custom', 'products', 'custombg'
    ],
    'servers_log' => [
        'auton', 'orderid', 'foran', 'gameid',
        'money', 'bmoney', 'paytype', 'stats',
        'CheckMacValue', 'rCheckMacValue',
        'RtnCode', 'RtnMsg',
        'hmoney', 'rmoney', 'paytimes',
        'PaymentNo', 'ExpireDate',
        'forname', 'errmsg', 'createtime', 'token'
    ],
    'servers_gift' => [
        'foran', 'types', 'pid', 'm1', 'm2', 'sizes', 'dd'
    ]
];

$result = [
    'success' => true,
    'verify_time' => date('Y-m-d H:i:s'),
    'tables' => [],
    'all_complete' => true
];

try {
    foreach ($requiredFields as $tableName => $fields) {
        // 取得資料表的所有欄位
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $existingFields = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingFields[] = $row['Field'];
        }

        $missing = [];
        $exists = [];

        foreach ($fields as $fieldName) {
            if (in_array($fieldName, $existingFields)) {
                $exists[] = $fieldName;
            } else {
                $missing[] = $fieldName;
                $result['all_complete'] = false;
            }
        }

        $result['tables'][$tableName] = [
            'total_required' => count($fields),
            'existing' => count($exists),
            'missing' => count($missing),
            'missing_fields' => $missing,
            'complete' => count($missing) === 0
        ];
    }

    $result['success'] = true;

} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
