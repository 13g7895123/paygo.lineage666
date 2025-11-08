<?php
/**
 * 資料庫結構更新 API
 * 執行 ALTER TABLE 新增缺少的欄位
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

// 接收 POST 資料
$input = file_get_contents('php://input');
$checkResults = json_decode($input, true);

if (!$checkResults) {
    echo json_encode([
        'success' => false,
        'error' => '無效的檢查結果'
    ]);
    exit;
}

$pdo = openpdo();

$result = [
    'success' => true,
    'start_time' => date('Y-m-d H:i:s'),
    'updates' => [],
    'summary' => ''
];

$successCount = 0;
$errorCount = 0;

try {
    $pdo->beginTransaction();

    foreach ($checkResults['tables'] as $tableName => $tableData) {
        if (!$tableData['table_exists']) {
            // 資料表不存在，需要先建立資料表（這裡先跳過，專注於欄位新增）
            $result['updates'][] = [
                'success' => false,
                'message' => "資料表 `{$tableName}` 不存在，請手動建立",
                'table' => $tableName
            ];
            $errorCount++;
            continue;
        }

        foreach ($tableData['fields'] as $field) {
            if (!$field['exists']) {
                // 欄位不存在，執行 ALTER TABLE
                $fieldName = $field['name'];
                $fieldType = $field['type'];
                $fieldDesc = $field['description'];

                // 根據欄位類型設定預設值
                $defaultValue = '';
                if (strpos($fieldType, 'INT') !== false) {
                    $defaultValue = "DEFAULT 0";
                } elseif (strpos($fieldType, 'DECIMAL') !== false) {
                    $defaultValue = "DEFAULT 0.00";
                } elseif (strpos($fieldType, 'VARCHAR') !== false || strpos($fieldType, 'TEXT') !== false) {
                    $defaultValue = "DEFAULT NULL";
                } elseif (strpos($fieldType, 'DATETIME') !== false) {
                    $defaultValue = "DEFAULT NULL";
                }

                $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$fieldName}` {$fieldType} {$defaultValue} COMMENT '{$fieldDesc}'";

                try {
                    $pdo->exec($sql);

                    $result['updates'][] = [
                        'success' => true,
                        'message' => "成功新增欄位: {$tableName}.{$fieldName}",
                        'table' => $tableName,
                        'field' => $fieldName,
                        'sql' => $sql
                    ];

                    $successCount++;

                } catch (PDOException $e) {
                    $result['updates'][] = [
                        'success' => false,
                        'message' => "新增欄位失敗: {$tableName}.{$fieldName}",
                        'table' => $tableName,
                        'field' => $fieldName,
                        'sql' => $sql,
                        'error' => $e->getMessage()
                    ];

                    $errorCount++;
                }
            }
        }
    }

    // 如果有錯誤，回滾事務
    if ($errorCount > 0) {
        $pdo->rollBack();
        $result['success'] = false;
        $result['summary'] = "更新失敗，已回滾所有變更。成功: {$successCount}, 失敗: {$errorCount}";
    } else {
        $pdo->commit();
        $result['summary'] = "更新完成！成功新增 {$successCount} 個欄位。";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $result['success'] = false;
    $result['error'] = $e->getMessage();
    $result['summary'] = "發生錯誤，已回滾所有變更。";
}

$result['end_time'] = date('Y-m-d H:i:s');
$result['total_updates'] = $successCount;
$result['total_errors'] = $errorCount;

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
