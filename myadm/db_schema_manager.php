<?php
/**
 * 資料庫結構管理頁面
 * 檢查並更新 Funpoint 金流所需的資料表欄位
 */

include("include.php");
check_login();

$pdo = openpdo();

top_html();
?>

<style>
    .schema-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .table-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #4CAF50;
    }

    .table-name {
        font-size: 20px;
        font-weight: bold;
        color: #333;
    }

    .status-badge {
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
    }

    .status-complete {
        background: #4CAF50;
        color: white;
    }

    .status-incomplete {
        background: #ff9800;
        color: white;
    }

    .field-list {
        margin: 15px 0;
    }

    .field-item {
        display: flex;
        align-items: center;
        padding: 10px;
        margin: 5px 0;
        border-radius: 4px;
        background: #f5f5f5;
    }

    .field-item.missing {
        background: #ffebee;
        border-left: 4px solid #f44336;
    }

    .field-item.exists {
        background: #e8f5e9;
        border-left: 4px solid #4CAF50;
    }

    .field-icon {
        width: 24px;
        height: 24px;
        margin-right: 10px;
        font-size: 18px;
    }

    .field-info {
        flex: 1;
    }

    .field-name {
        font-weight: bold;
        color: #333;
    }

    .field-type {
        color: #666;
        font-size: 13px;
        margin-left: 10px;
    }

    .field-desc {
        color: #999;
        font-size: 12px;
        margin-top: 3px;
    }

    .action-buttons {
        margin-top: 20px;
        text-align: center;
    }

    .btn-check {
        background: #2196F3;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        margin: 5px;
    }

    .btn-check:hover {
        background: #1976D2;
    }

    .btn-update {
        background: #4CAF50;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        margin: 5px;
    }

    .btn-update:hover {
        background: #45a049;
    }

    .btn-update:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .log-section {
        background: #f9f9f9;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        max-height: 400px;
        overflow-y: auto;
    }

    .log-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
    }

    .log-item {
        padding: 8px 12px;
        margin: 5px 0;
        border-radius: 4px;
        font-family: monospace;
        font-size: 13px;
    }

    .log-success {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .log-error {
        background: #ffebee;
        color: #c62828;
    }

    .log-info {
        background: #e3f2fd;
        color: #1565c0;
    }

    .summary-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .summary-title {
        font-weight: bold;
        color: #856404;
        margin-bottom: 10px;
    }

    .summary-stats {
        display: flex;
        gap: 20px;
    }

    .stat-item {
        flex: 1;
        text-align: center;
        padding: 10px;
        background: white;
        border-radius: 4px;
    }

    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }

    .stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .loading {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="schema-container">
    <h1>資料庫結構管理 - Funpoint 金流</h1>

    <div class="summary-box" id="summary" style="display: none;">
        <div class="summary-title">檢查摘要</div>
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-number" id="total-tables">0</div>
                <div class="stat-label">資料表</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="total-fields">0</div>
                <div class="stat-label">總欄位</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="missing-fields">0</div>
                <div class="stat-label">缺少欄位</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="existing-fields">0</div>
                <div class="stat-label">已存在欄位</div>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <button class="btn-check" onclick="checkSchema()">
            <i class="fa fa-search"></i> 檢查資料庫結構
        </button>
        <button class="btn-update" id="btnUpdate" onclick="updateSchema()" disabled>
            <i class="fa fa-database"></i> 執行更新（新增缺少的欄位）
        </button>
        <button class="btn-check" onclick="verifySchema()" style="background: #9C27B0;">
            <i class="fa fa-check-circle"></i> 驗證完整性
        </button>
        <button class="btn-check" onclick="exportReport()" style="background: #FF9800;">
            <i class="fa fa-file-text"></i> 匯出報告
        </button>
    </div>

    <div id="results"></div>

    <div class="log-section" id="logSection" style="display: none;">
        <div class="log-title">執行日誌</div>
        <div id="logs"></div>
    </div>
</div>

<script>
let checkResults = null;

function checkSchema() {
    const resultsDiv = document.getElementById('results');
    const summaryDiv = document.getElementById('summary');
    const btnUpdate = document.getElementById('btnUpdate');

    resultsDiv.innerHTML = '<div class="loading"><div class="spinner"></div><p>檢查中...</p></div>';
    summaryDiv.style.display = 'none';
    btnUpdate.disabled = true;

    fetch('api/db_schema_check.php')
        .then(response => response.json())
        .then(data => {
            checkResults = data;
            displayResults(data);
            updateSummary(data);

            // 如果有缺少的欄位，啟用更新按鈕
            if (data.summary.missing_fields > 0) {
                btnUpdate.disabled = false;
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = '<div class="log-item log-error">錯誤: ' + error.message + '</div>';
        });
}

function displayResults(data) {
    const resultsDiv = document.getElementById('results');
    let html = '';

    for (const tableName in data.tables) {
        const table = data.tables[tableName];
        const missingCount = table.fields.filter(f => !f.exists).length;
        const statusClass = missingCount > 0 ? 'status-incomplete' : 'status-complete';
        const statusText = missingCount > 0 ? `缺少 ${missingCount} 個欄位` : '完整';

        html += `
            <div class="table-section">
                <div class="table-header">
                    <span class="table-name">${table.display_name}</span>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                <div class="field-list">
        `;

        table.fields.forEach(field => {
            const icon = field.exists ? '✓' : '✗';
            const itemClass = field.exists ? 'exists' : 'missing';
            const currentType = field.exists ? `<span class="field-type">當前: ${field.current_type}</span>` : '';

            html += `
                <div class="field-item ${itemClass}">
                    <span class="field-icon">${icon}</span>
                    <div class="field-info">
                        <div>
                            <span class="field-name">${field.name}</span>
                            <span class="field-type">需要: ${field.type}</span>
                            ${currentType}
                        </div>
                        <div class="field-desc">${field.description}</div>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;
    }

    resultsDiv.innerHTML = html;
}

function updateSummary(data) {
    const summaryDiv = document.getElementById('summary');
    summaryDiv.style.display = 'block';

    document.getElementById('total-tables').textContent = data.summary.total_tables;
    document.getElementById('total-fields').textContent = data.summary.total_fields;
    document.getElementById('missing-fields').textContent = data.summary.missing_fields;
    document.getElementById('existing-fields').textContent = data.summary.existing_fields;
}

function updateSchema() {
    if (!confirm('確定要執行資料庫更新嗎？\n\n此操作將新增所有缺少的欄位到資料表中。')) {
        return;
    }

    const btnUpdate = document.getElementById('btnUpdate');
    const logSection = document.getElementById('logSection');
    const logsDiv = document.getElementById('logs');

    btnUpdate.disabled = true;
    btnUpdate.textContent = '更新中...';

    logSection.style.display = 'block';
    logsDiv.innerHTML = '<div class="log-item log-info">開始執行資料庫更新...</div>';

    fetch('api/db_schema_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(checkResults)
    })
        .then(response => response.json())
        .then(data => {
            displayUpdateLogs(data);

            // 更新完成後重新檢查
            setTimeout(() => {
                checkSchema();
            }, 1000);
        })
        .catch(error => {
            logsDiv.innerHTML += '<div class="log-item log-error">錯誤: ' + error.message + '</div>';
            btnUpdate.disabled = false;
            btnUpdate.innerHTML = '<i class="fa fa-database"></i> 執行更新（新增缺少的欄位）';
        });
}

function displayUpdateLogs(data) {
    const logsDiv = document.getElementById('logs');
    let html = '';

    html += `<div class="log-item log-info">開始時間: ${data.start_time}</div>`;

    data.updates.forEach(update => {
        const logClass = update.success ? 'log-success' : 'log-error';
        const icon = update.success ? '✓' : '✗';
        html += `<div class="log-item ${logClass}">${icon} ${update.message}</div>`;
        if (update.sql) {
            html += `<div class="log-item log-info" style="margin-left: 30px;">SQL: ${update.sql}</div>`;
        }
        if (update.error) {
            html += `<div class="log-item log-error" style="margin-left: 30px;">錯誤: ${update.error}</div>`;
        }
    });

    html += `<div class="log-item log-info">結束時間: ${data.end_time}</div>`;
    html += `<div class="log-item ${data.success ? 'log-success' : 'log-error'}">
        <strong>總結: ${data.summary}</strong>
    </div>`;

    logsDiv.innerHTML = html;

    const btnUpdate = document.getElementById('btnUpdate');
    btnUpdate.innerHTML = '<i class="fa fa-database"></i> 執行更新（新增缺少的欄位）';
}

function verifySchema() {
    const logSection = document.getElementById('logSection');
    const logsDiv = document.getElementById('logs');

    logSection.style.display = 'block';
    logsDiv.innerHTML = '<div class="log-item log-info">正在驗證資料庫完整性...</div>';

    fetch('api/db_schema_verify.php')
        .then(response => response.json())
        .then(data => {
            let html = '<div class="log-item log-info">驗證時間: ' + data.verify_time + '</div>';

            if (data.all_complete) {
                html += '<div class="log-item log-success"><strong>✓ 所有必要欄位都已存在！資料庫結構完整。</strong></div>';
            } else {
                html += '<div class="log-item log-error"><strong>✗ 仍有缺少的欄位</strong></div>';
            }

            for (const tableName in data.tables) {
                const table = data.tables[tableName];
                const icon = table.complete ? '✓' : '✗';
                const logClass = table.complete ? 'log-success' : 'log-error';

                html += `<div class="log-item ${logClass}">
                    ${icon} ${tableName}: ${table.existing}/${table.total_required} 個欄位
                </div>`;

                if (table.missing_fields.length > 0) {
                    html += `<div class="log-item log-error" style="margin-left: 30px;">
                        缺少: ${table.missing_fields.join(', ')}
                    </div>`;
                }
            }

            logsDiv.innerHTML = html;
        })
        .catch(error => {
            logsDiv.innerHTML += '<div class="log-item log-error">驗證錯誤: ' + error.message + '</div>';
        });
}

function exportReport() {
    if (!checkResults) {
        alert('請先執行「檢查資料庫結構」');
        return;
    }

    // 生成報告內容
    let report = '='.repeat(80) + '\n';
    report += 'Funpoint 金流資料庫結構檢查報告\n';
    report += '='.repeat(80) + '\n';
    report += '檢查時間: ' + new Date().toLocaleString('zh-TW') + '\n\n';

    report += '摘要:\n';
    report += '-'.repeat(80) + '\n';
    report += `總資料表數: ${checkResults.summary.total_tables}\n`;
    report += `總欄位數: ${checkResults.summary.total_fields}\n`;
    report += `已存在欄位: ${checkResults.summary.existing_fields}\n`;
    report += `缺少欄位: ${checkResults.summary.missing_fields}\n\n`;

    for (const tableName in checkResults.tables) {
        const table = checkResults.tables[tableName];
        report += '='.repeat(80) + '\n';
        report += `資料表: ${table.display_name}\n`;
        report += '='.repeat(80) + '\n\n';

        const missingFields = table.fields.filter(f => !f.exists);
        const existingFields = table.fields.filter(f => f.exists);

        if (missingFields.length > 0) {
            report += '【缺少的欄位】\n';
            missingFields.forEach(field => {
                report += `  ✗ ${field.name} (${field.type})\n`;
                report += `    說明: ${field.description}\n\n`;
            });
        }

        if (existingFields.length > 0) {
            report += '【已存在的欄位】\n';
            existingFields.forEach(field => {
                report += `  ✓ ${field.name} (${field.current_type})\n`;
            });
            report += '\n';
        }
    }

    report += '='.repeat(80) + '\n';
    report += '報告結束\n';
    report += '='.repeat(80) + '\n';

    // 下載報告
    const blob = new Blob([report], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'funpoint_db_schema_report_' + new Date().getTime() + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    alert('報告已匯出！');
}

// 頁面載入時自動檢查
window.addEventListener('load', function() {
    checkSchema();
});
</script>

<?php
down_html();
?>
