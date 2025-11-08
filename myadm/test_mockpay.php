<?php
/**
 * mockpay.php 測試頁面
 * 幫助除錯 mockpay.php 的 404 問題
 */

include("include.php");

// 基本資訊顯示
echo "<h1>mockpay.php 除錯頁面</h1>";
echo "<hr>";

echo "<h2>1. 檔案檢查</h2>";
if (file_exists("mockpay.php")) {
    echo "✅ mockpay.php 檔案存在<br>";
} else {
    echo "❌ mockpay.php 檔案不存在<br>";
}

echo "<h2>2. 參數測試</h2>";
$testOrderId = 123; // 假設的訂單 ID

echo "測試 URL: <a href='mockpay.php?an={$testOrderId}&type=1' target='_blank'>mockpay.php?an={$testOrderId}&type=1</a><br>";

echo "<h2>3. 資料庫測試</h2>";
try {
    $pdo = openpdo();
    echo "✅ 資料庫連線成功<br>";
    
    // 查詢一些訂單資料作為測試
    $stmt = $pdo->query("SELECT auton, orderid, stats, pay_cp, paytype FROM servers_log LIMIT 5");
    $orders = $stmt->fetchAll();
    
    if ($orders) {
        echo "<h3>可用的測試訂單：</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>auton</th><th>orderid</th><th>狀態</th><th>金流</th><th>付款類型</th><th>測試連結</th></tr>";
        
        foreach ($orders as $order) {
            $testUrl = "mockpay.php?an={$order['auton']}&type={$order['paytype']}";
            echo "<tr>";
            echo "<td>{$order['auton']}</td>";
            echo "<td>{$order['orderid']}</td>";
            echo "<td>{$order['stats']}</td>";
            echo "<td>{$order['pay_cp']}</td>";
            echo "<td>{$order['paytype']}</td>";
            echo "<td><a href='{$testUrl}' target='_blank'>測試</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ 沒有找到訂單資料<br>";
    }
    
} catch (Exception $e) {
    echo "❌ 資料庫連線失敗: " . $e->getMessage() . "<br>";
}

echo "<h2>4. JavaScript 測試</h2>";
echo "<button onclick=\"testMockPay()\">測試開啟 mockpay.php</button>";

echo "<script>
function testMockPay() {
    var testId = prompt('請輸入測試用的 auton (訂單編號):', '{$testOrderId}');
    if (testId) {
        var url = 'mockpay.php?an=' + testId + '&type=1';
        console.log('開啟 URL:', url);
        window.open(url, '_blank', 'width=600,height=400');
    }
}
</script>";

echo "<h2>5. 環境資訊</h2>";
echo "目前路徑: " . __FILE__ . "<br>";
echo "工作目錄: " . getcwd() . "<br>";
echo "文件根目錄: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

if (isset($_SERVER['HTTP_HOST'])) {
    $baseUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    echo "基礎 URL: {$baseUrl}<br>";
    echo "mockpay.php 完整 URL: {$baseUrl}/mockpay.php<br>";
}
?>
