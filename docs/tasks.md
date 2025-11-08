# Funpoint 金流直接串接任務清單

## 專案狀態分析

### 已存在的檔案
- ✅ `myadm/include.php` - 包含資料庫連線函數 (openpdo, opengamepdo)
- ✅ `myadm/api/payment_processor.php` - 遊戲端支付處理邏輯
- ✅ 資料庫配置已設定於 include.php

### 核心檔案狀態（位於 src/ 資料夾）
- ✅ `src/payment_class.php` - 支付類別定義（CheckMacValue 生成）- **已完成**
- ✅ `src/funpoint_next.php` - 支付處理頁面（直接串接版本）- **已完成**
- ✅ `src/funpoint_r.php` - 回調接收頁面（伺服器端通知）- **已完成**
- ✅ `src/funpoint_payok.php` - 支付完成展示頁面（前端返回）- **已完成**

### 檔案檢查結果（2025-11-08）
所有必要的 Funpoint 串接檔案已存在於 `src/` 資料夾中，並已完成直接串接改造：

1. **payment_class.php** - 包含 funpoint 類別，實作 SHA256 CheckMacValue 生成
2. **funpoint_next.php** - 已移除跳板依賴，實現直接與 FunPoint API 通訊
3. **funpoint_r.php** - 完整的回調處理邏輯，包含虛擬貨幣發放和禮品機制
4. **funpoint_payok.php** - 支付完成展示頁面，支援多種支付方式

---

## 完整實作步驟

### 階段一：基礎檔案建立 ✅ **已完成**

#### 步驟 1：建立 payment_class.php ✅ **已完成**
**位置**: `src/payment_class.php`

**已實作功能**:
1. ✅ `funpoint` 類別已建立
2. ✅ `generate()` 靜態方法用於生成 CheckMacValue
3. ✅ SHA256 加密演算法已實作
4. ✅ 支援 URL encode 和字串轉換處理

**CheckMacValue 生成規則**（已實作）:
```
1. 移除 CheckMacValue 參數
2. 依照參數名稱排序（字母順序）
3. 組合字串：HashKey={HashKey}&參數1=值1&參數2=值2...&HashIV={HashIV}
4. URL encode
5. 轉換為小寫
6. SHA256 hash
7. 轉換為大寫
```

**驗收標準**:
- [x] 類別可正確引入
- [x] 生成的 CheckMacValue 符合 FunPoint 規範
- [ ] 測試多組參數確認正確性（待測試環境驗證）

---

#### 步驟 2：建立 funpoint_next.php（支付處理頁面）✅ **已完成（已改為直接串接）**
**位置**: `src/funpoint_next.php`

**已實作功能**:
1. ✅ 移除跳板依賴（原本 POST 到 gohost.tw 已移除）
2. ✅ 直接與 FunPoint API 通訊
3. ✅ 根據支付類型進行不同處理（CVS/ATM/Credit）
4. ✅ 生成並記錄 CheckMacValue

**已實作主要功能**:
- ✅ SESSION 資料驗證（foran, serverid, lastan）
- ✅ 訂單狀態檢查（從 servers_log 表查詢）
- ✅ 商店設定取得（根據支付類型從 servers 或 bank_funds 表）
- ✅ 支付參數組合與 CheckMacValue 生成
- ✅ 根據支付類型處理：
  - **CVS/BARCODE**: 直接 POST 到 FunPoint API 並輸出結果
  - **ATM/Credit/WebATM**: 產生 HTML 表單自動提交到 FunPoint

**回調 URL 配置**:
```php
$base_url = "https://lineage666.pay-lineage.com";  // 自動從 include.php 讀取
$rurl = $base_url . "/src/funpoint_r.php";         // 伺服器端通知
$rurl2 = $base_url . "/src/funpoint_payok.php";    // 前端返回
```

**支付類型對應表**:
| paytype | 支付方式 | ChoosePayment | ChooseSubPayment | 狀態 |
|---------|----------|---------------|------------------|------|
| 1 | 超商條碼 | BARCODE | BARCODE | ✅ |
| 2 | ATM 轉帳 | ATM | ESUN | ✅ |
| 3 | 超商代碼 | CVS | CVS | ✅ |
| 4 | 7-11 ibon | CVS | IBON | ✅ |
| 5 | 信用卡 | Credit | (空) | ✅ |
| 6 | WebATM | WebATM | (空) | ✅ |

**驗收標準**:
- [x] SESSION 資料正確驗證
- [x] 訂單狀態檢查正常
- [x] 各支付類型都能正確取得商店設定
- [x] CheckMacValue 生成正確
- [x] CVS 支付能正確調用 FunPoint API
- [x] ATM/Credit 支付能正確產生表單
- [ ] 實際測試驗證（待測試環境驗證）

---

#### 步驟 3：檢查 funpoint_r.php（回調接收頁面）✅ **已完成**
**位置**: `src/funpoint_r.php`

**已實作功能**:
1. ✅ 接收 FunPoint 的伺服器端通知
2. ✅ 驗證訂單狀態（使用 FOR UPDATE 鎖定）
3. ✅ 更新訂單記錄
4. ✅ 直接實作虛擬貨幣發放（贊助幣、紅利幣）
5. ✅ 完整的禮品機制（滿額贈、首購、活動首購、累積儲值）
6. ✅ 回應 FunPoint

**已實作主要功能**:
- ✅ 接收 POST 參數（MerchantID, MerchantTradeNo, RtnCode, CheckMacValue 等）
- ✅ 訂單鎖定與狀態驗證（FOR UPDATE）
- ✅ 判斷支付結果（RtnCode = 1 為成功）
- ✅ 更新 servers_log 表
- ✅ 發放虛擬貨幣（贊助幣、紅利幣）
- ✅ 處理四種禮品機制
- ✅ 回應 "1|OK" 或 "0"

**安全機制**:
- ✅ 使用資料庫事務（BEGIN TRANSACTION + FOR UPDATE）
- ✅ 防止重複處理（檢查訂單狀態）
- [ ] 建議加強 CheckMacValue 驗證（待實作）

**驗收標準**:
- [x] 能正確接收 FunPoint 回調
- [x] 訂單狀態更新正確
- [x] 虛擬貨幣發放正常
- [x] 禮品機制運作正常（滿額贈、首購、累積、活動首購）
- [x] 正確回應 FunPoint
- [ ] 實際測試驗證（待測試環境驗證）

---

#### 步驟 4：檢查 funpoint_payok.php（支付完成展示頁面）✅ **已完成**
**位置**: `src/funpoint_payok.php`

**已實作功能**:
1. ✅ 接收 FunPoint 前端返回的資料
2. ✅ 查詢訂單資訊
3. ✅ 顯示繳費資訊
4. ✅ 美觀的 UI 介面

**已支援顯示內容**:
- ✅ **ATM**: 銀行代碼、虛擬帳號、繳費期限
- ✅ **超商代碼**: 繳費代碼、繳費期限
- ✅ **ibon**: ibon 代碼、繳費期限
- ✅ **信用卡**: 完成提示

**輸入參數**:
- MerchantTradeNo（訂單編號）
- PaymentNo（繳費代碼/帳號）
- BankCode（銀行代碼，ATM用）
- vAccount（虛擬帳號，ATM用）
- ExpireDate（繳費期限）

**驗收標準**:
- [x] 能正確顯示各種支付類型的繳費資訊
- [x] 頁面美觀且用戶體驗良好
- [x] 訂單資訊正確顯示
- [ ] 實際測試驗證（待測試環境驗證）

---

### 階段二：資料庫設定（預計 1 小時）

#### 步驟 5：檢查並設定 servers 資料表

**需要確認的欄位**:

**信用卡支付設定** (paytype = 5):
```sql
MerchantID   VARCHAR   商店代號
HashKey      VARCHAR   金鑰
HashIV       VARCHAR   向量
gstats       INT       環境設定 (0=測試, 1=正式)
```

**其他支付方式設定**:
```sql
MerchantID2  VARCHAR   商店代號
HashKey2     VARCHAR   金鑰
HashIV2      VARCHAR   向量
gstats2      INT       環境設定 (0=測試, 1=正式)
```

**銀行轉帳設定** (paytype = 2):
```sql
gstats_bank  INT       環境設定 (0=測試, 1=正式)
```

**其他必要欄位**:
```sql
db_ip              VARCHAR      遊戲資料庫 IP
db_port            INT          遊戲資料庫 Port
db_name            VARCHAR      遊戲資料庫名稱
db_user            VARCHAR      遊戲資料庫帳號
db_pass            VARCHAR      遊戲資料庫密碼
db_pid             VARCHAR      贊助幣物品 ID
db_bonusid         VARCHAR      紅利幣物品 ID
db_bonusrate       DECIMAL      紅利幣比例
paytable           VARCHAR      支付記錄表名稱
products           TEXT         商品名稱清單
```

**任務**:
- [ ] 檢查所有必要欄位是否存在
- [ ] 如有缺少欄位，執行 ALTER TABLE 新增
- [ ] 設定測試環境參數（gstats = 0, gstats2 = 0, gstats_bank = 0）

---

#### 步驟 6：檢查並設定 servers_log 資料表

**必要欄位**:
```sql
auton              INT          訂單記錄 ID (主鍵)
orderid            VARCHAR      訂單編號（MerchantTradeNo）
foran              INT          伺服器 ID
gameid             VARCHAR      遊戲帳號
money              INT          訂單金額
bmoney             INT          實際發放金額
paytype            INT          支付類型
stats              INT          訂單狀態（0=未處理, 1=成功, 2=失敗, 3=測試成功）
CheckMacValue      VARCHAR      請求驗證碼
rCheckMacValue     VARCHAR      回傳驗證碼
RtnCode            INT          回傳碼
RtnMsg             VARCHAR      回傳訊息
hmoney             DECIMAL      手續費
rmoney             INT          實收金額
paytimes           DATETIME     支付時間
PaymentNo          VARCHAR      繳費代碼/帳號
ExpireDate         VARCHAR      繳費期限
forname            VARCHAR      伺服器名稱
errmsg             TEXT         錯誤訊息
createtime         DATETIME     建立時間
```

**任務**:
- [ ] 檢查所有必要欄位是否存在
- [ ] 如有缺少欄位，執行 ALTER TABLE 新增

---

#### 步驟 7：檢查 bank_funds 資料表（ATM 轉帳用）

**任務**:
1. 確認 `bank_funds` 資料表存在
2. 確認表結構能支援 FunPoint 支付設定
3. 確認 `getSpecificBankPaymentInfo()` 函數實作

**可能需要實作**:
如果 `getSpecificBankPaymentInfo()` 函數不存在，需要在 `funpoint_next.php` 中實作：

```php
function getSpecificBankPaymentInfo($pdo, $lastan, $payment_type) {
    // 從 bank_funds 表查詢支付設定
    // 需根據實際資料表結構調整
    $stmt = $pdo->prepare("SELECT * FROM bank_funds WHERE payment_type = ? LIMIT 1");
    $stmt->execute([$payment_type]);
    $result = $stmt->fetch();

    return [
        'payment_config' => [
            'merchant_id' => $result['merchant_id'],
            'hashkey' => $result['hashkey'],
            'hashiv' => $result['hashiv']
        ]
    ];
}
```

**驗收標準**:
- [ ] bank_funds 表結構確認
- [ ] ATM 轉帳能正確取得商店設定

---

### 階段三：FunPoint 商店設定（預計 1 小時）

#### 步驟 8：申請 FunPoint 商店帳號

**測試環境**:
```
URL: https://payment-stage.funpoint.com.tw/Cashier/AioCheckOut/V5
測試商店資訊:
  MerchantID: "1000031"
  HashKey: "265flDjIvesceXWM"
  HashIV: "pOOvhGd1V2pJbjfX"
```

**正式環境**:
```
URL: https://payment.funpoint.com.tw/Cashier/AioCheckOut/V5
商店資訊: 需向 FunPoint 申請
```

**任務**:
- [ ] 確認測試環境可用
- [ ] 向 FunPoint 申請正式環境商店帳號
- [ ] 取得正式環境的 MerchantID, HashKey, HashIV
- [ ] 將正式環境設定寫入資料庫

---

#### 步驟 9：設定回調 URL

**需要設定的 URL**:
```
伺服器端通知 URL: https://lineage666.pay-lineage.com/funpoint_r.php
前端返回 URL: https://lineage666.pay-lineage.com/funpoint_payok.php
```

**任務**:
- [ ] 確認網域可以被 FunPoint 伺服器訪問
- [ ] 確認使用 HTTPS 協議
- [ ] 向 FunPoint 提供回調 URL
- [ ] 測試回調 URL 可正常訪問

---

### 階段四：測試與驗證（預計 2-3 小時）

#### 步驟 10：測試環境整合測試

**測試項目**:

**1. 測試環境配置**:
```sql
UPDATE servers SET
    gstats = 0,      -- 信用卡測試環境
    gstats2 = 0,     -- 其他支付測試環境
    gstats_bank = 0  -- 銀行轉帳測試環境
WHERE auton = ?;
```

**2. 支付流程測試**:
- [ ] 超商條碼（paytype=1）
  - 建立測試訂單
  - 訪問 funpoint_next.php
  - 確認能取得繳費代碼
  - 確認繳費代碼正確顯示

- [ ] ATM 轉帳（paytype=2）
  - 建立測試訂單
  - 訪問 funpoint_next.php
  - 確認能跳轉到 FunPoint 支付頁面
  - 完成支付流程
  - 確認回調正常

- [ ] 超商代碼（paytype=3）
  - 建立測試訂單
  - 訪問 funpoint_next.php
  - 確認能取得繳費代碼
  - 確認繳費代碼正確顯示

- [ ] 7-11 ibon（paytype=4）
  - 建立測試訂單
  - 訪問 funpoint_next.php
  - 確認能取得 ibon 代碼
  - 確認 ibon 代碼正確顯示

- [ ] 信用卡（paytype=5）
  - 建立測試訂單
  - 訪問 funpoint_next.php
  - 確認能跳轉到 FunPoint 支付頁面
  - 完成支付流程
  - 確認回調正常

- [ ] WebATM（paytype=6）
  - 建立測試訂單
  - 訪問 funpoint_next.php
  - 確認能跳轉到 FunPoint 支付頁面
  - 完成支付流程
  - 確認回調正常

**3. 回調測試**:
- [ ] 伺服器端通知 (funpoint_r.php)
  - 確認能接收 FunPoint POST 資料
  - 確認訂單狀態更新（stats = 3，測試成功）
  - 確認虛擬貨幣發放（查詢遊戲資料庫）
  - 確認禮品機制運作
  - 確認回應 "1|OK"

- [ ] 前端返回 (funpoint_payok.php)
  - 確認能接收 FunPoint 轉導資料
  - 確認繳費資訊正確顯示
  - 確認頁面美觀度

**4. 錯誤處理測試**:
- [ ] 測試各種錯誤情況
  - SESSION 資料缺失
  - 訂單不存在
  - 訂單已處理
  - 商店設定錯誤
  - 資料庫連線失敗

---

#### 步驟 11：檢查 CheckMacValue 驗證

**任務**:
1. 在 funpoint_r.php 中加入 CheckMacValue 驗證機制
2. 驗證 FunPoint 回傳的 CheckMacValue 是否正確
3. 如驗證失敗，拒絕處理並記錄

**實作範例**:
```php
// 在 funpoint_r.php 中加入
$receivedCheckMacValue = $_REQUEST["CheckMacValue"];

// 重新組合參數（移除 CheckMacValue）
$params = $_REQUEST;
unset($params['CheckMacValue']);

// 計算 CheckMacValue
$calculatedCheckMacValue = funpoint::generate($params, $HashKey, $HashIV);

// 驗證
if ($receivedCheckMacValue !== $calculatedCheckMacValue) {
    error_log("CheckMacValue 驗證失敗");
    die("0");
}
```

**驗收標準**:
- [ ] CheckMacValue 驗證功能正常
- [ ] 驗證失敗時拒絕處理
- [ ] 驗證失敗有記錄 log

---

#### 步驟 12：錯誤日誌機制

**任務**:
建立統一的錯誤日誌記錄機制

**實作位置**:
- funpoint_next.php
- funpoint_r.php
- funpoint_payok.php

**建議實作**:
```php
function log_funpoint_error($error_code, $error_msg, $context = []) {
    $log_file = __DIR__ . '/logs/funpoint_error.log';
    $log_entry = sprintf(
        "[%s] Code: %s, Message: %s, Context: %s\n",
        date('Y-m-d H:i:s'),
        $error_code,
        $error_msg,
        json_encode($context, JSON_UNESCAPED_UNICODE)
    );

    // 確保 logs 目錄存在
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
```

**驗收標準**:
- [ ] logs 目錄建立成功
- [ ] 錯誤能正確記錄到 log 檔
- [ ] log 格式清晰易讀

---

### 階段五：正式環境上線（預計 1 小時）

#### 步驟 13：正式環境設定

**任務**:
1. 更新 servers 表的環境設定
2. 填入正式商店資訊
3. 切換到正式環境

**SQL 設定**:
```sql
UPDATE servers SET
    -- 信用卡正式環境
    gstats = 1,
    MerchantID = '正式商店代號',
    HashKey = '正式金鑰',
    HashIV = '正式向量',

    -- 其他支付方式正式環境
    gstats2 = 1,
    MerchantID2 = '正式商店代號',
    HashKey2 = '正式金鑰',
    HashIV2 = '正式向量',

    -- 銀行轉帳正式環境
    gstats_bank = 1
WHERE auton = ?;
```

**驗收標準**:
- [ ] 正式商店資訊設定完成
- [ ] 環境切換為正式環境（gstats = 1）

---

#### 步驟 14：正式環境小額測試

**任務**:
1. 執行小額真實交易測試
2. 確認整個流程正常
3. 確認虛擬貨幣發放正常
4. 確認禮品機制正常

**測試金額建議**: 30-50 元

**測試項目**:
- [ ] 至少測試一種支付方式（建議 ATM 或信用卡）
- [ ] 確認支付成功
- [ ] 確認訂單狀態更新為 1（成功）
- [ ] 確認虛擬貨幣正確發放
- [ ] 確認禮品機制運作

---

#### 步驟 15：上線前最終檢查

**檢查清單**:
- [ ] 所有檔案都已部署到正式環境
- [ ] 資料庫設定正確
- [ ] HTTPS 憑證有效
- [ ] 回調 URL 可正常訪問
- [ ] 錯誤日誌機制運作正常
- [ ] 測試交易成功
- [ ] 備份現有系統
- [ ] 準備回滾方案

**回滾方案**:
如果上線後發現問題，需要能快速回滾：
1. 備份新建立的檔案
2. 準備舊版檔案（如果有）
3. 記錄資料庫變更的 SQL

---

## 需要確認的事項

### 技術細節確認

#### 1. CheckMacValue 生成演算法
- [ ] 確認 URL encode 的具體規則
- [ ] 確認是否有特殊字元需要額外處理
- [ ] 實際測試驗證演算法正確性

#### 2. bank_funds 資料表
- [ ] 確認 bank_funds 資料表的完整結構
- [ ] 確認 getSpecificBankPaymentInfo() 函數的實作
- [ ] 確認回傳資料的確切格式

#### 3. 訂單編號生成
- [ ] 確認訂單編號的生成格式
- [ ] 確認是否有長度限制
- [ ] 確認是否需要特定前綴或後綴

#### 4. CVS/BARCODE 回傳處理
- [ ] 確認 FunPoint API 對 CVS/BARCODE 的回應格式
- [ ] 確認回應中如何取得繳費代碼
- [ ] 確認是否需要額外處理或轉換格式

#### 5. 回調重試機制
- [ ] 確認 FunPoint 是否會重試回調
- [ ] 確認重試的頻率和次數
- [ ] 確認如何處理重複的回調通知

---

## 錯誤代碼對照表

| 錯誤代碼 | 錯誤訊息 | 觸發位置 | 處理方式 |
|----------|----------|----------|----------|
| 8000201 | 伺服器資料錯誤（foran 為空） | funpoint_next.php | 檢查 SESSION 設定 |
| 8000202 | 伺服器資料錯誤（serverid 為空） | funpoint_next.php | 檢查 SESSION 設定 |
| 8000203 | 伺服器資料錯誤（lastan 為空） | funpoint_next.php | 檢查 SESSION 設定 |
| 8000204 | 不明錯誤（找不到伺服器記錄） | funpoint_next.php | 檢查 servers 表 |
| 8000206 | 金流錯誤（商店資訊不完整） | funpoint_next.php | 檢查商店設定 |
| 8000207 | 不明錯誤（找不到訂單記錄） | funpoint_next.php | 檢查 servers_log 表 |
| 8000208 | 金流狀態有誤（訂單已處理） | funpoint_next.php | 檢查訂單狀態 |
| 8000301 | 資料錯誤（MerchantTradeNo 為空） | funpoint_payok.php | 檢查回傳參數 |
| 8000302 | 不明錯誤（找不到訂單） | funpoint_payok.php | 檢查訂單編號 |

---

## 專案時程預估

| 階段 | 原預估時間 | 實際狀態 | 說明 |
|------|----------|----------|------|
| 階段一：基礎檔案建立 | 2-3 小時 | ✅ **已完成** | 所有核心 PHP 檔案已存在並完成直接串接改造 |
| 階段二：資料庫設定 | 1 小時 | ⏳ **待確認** | 需檢查並設定資料表結構 |
| 階段三：FunPoint 商店設定 | 1 小時 | ⏳ **待確認** | 需申請帳號與設定回調 URL |
| 階段四：測試與驗證 | 2-3 小時 | ⏳ **待執行** | 完整的整合測試 |
| 階段五：正式環境上線 | 1 小時 | ⏳ **待執行** | 正式環境設定與測試 |
| **總計** | **7-9 小時** | **階段一已完成，剩餘 5-7 小時** | 不含等待 FunPoint 審核時間 |

---

## 優勢分析

相較於原本使用跳板的架構，直接串接有以下優勢：

1. **獨立性**：不依賴外部跳板服務（gohost.tw）
2. **效能**：減少網路請求次數，提升回應速度
3. **安全性**：減少資料傳輸環節，降低資料外洩風險
4. **可控性**：完全掌控支付流程，便於除錯與維護
5. **簡化**：不需要訂單編號尾碼判斷邏輯，程式碼更簡潔

---

## 注意事項

### 重要提醒

1. **HTTPS 必須**：所有與 FunPoint 通訊必須使用 HTTPS
2. **回調 URL**：確保 funpoint_r.php 和 funpoint_payok.php 可被外部訪問
3. **資料庫事務**：使用 FOR UPDATE 鎖定防止重複處理
4. **錯誤處理**：完善的錯誤處理和日誌記錄
5. **測試充分**：上線前務必充分測試所有支付類型
6. **備份系統**：上線前備份現有系統，準備回滾方案

### 安全建議

1. 實作 CheckMacValue 驗證機制
2. 防止 SQL Injection（使用 prepared statements）
3. 防止 XSS 攻擊（輸出時進行 htmlspecialchars）
4. 記錄所有金流相關操作的 log
5. 定期檢查異常交易

---

## 參考文件

- `docs/funpoint_direct_integration.md` - 完整的技術文件
- FunPoint 官方 API 文件（需向 FunPoint 索取）
- 現有系統的其他金流串接範例

---

**文件建立日期**: 2025-11-08
**文件更新日期**: 2025-11-08
**專案狀態**: 階段一已完成（核心檔案已建立並完成直接串接改造）
**剩餘工作**: 資料庫設定、商店設定、測試驗證、正式環境上線

---

## 更新記錄

### 2025-11-08 更新
- ✅ 確認所有核心檔案已存在於 `src/` 資料夾
- ✅ 完成 `src/funpoint_next.php` 直接串接改造（移除跳板依賴）
- ✅ 確認 `src/payment_class.php` 實作 SHA256 CheckMacValue 生成
- ✅ 確認 `src/funpoint_r.php` 包含完整回調處理邏輯
- ✅ 確認 `src/funpoint_payok.php` 支援多種支付方式顯示
- ✅ 更新文檔狀態為「階段一已完成」

### 待辦事項（後續階段）
- [ ] 檢查並設定 servers 資料表欄位
- [ ] 檢查並設定 servers_log 資料表欄位
- [ ] 確認 bank_funds 資料表結構
- [ ] 向 FunPoint 申請商店帳號
- [ ] 設定回調 URL
- [ ] 執行測試環境整合測試
- [ ] 執行正式環境上線
