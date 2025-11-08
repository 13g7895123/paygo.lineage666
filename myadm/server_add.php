<?php
include("include.php");
include("../phpclass/SimpleImage.php");
check_login();


$target_dir = "../assets/images/custombg/";

if(_r("st") == "readcustombg") {
	if(empty($an = _r("an"))) die("");
	$pdo = openpdo(); 	       	
	$chkq = $pdo->prepare("select custombg from servers where auton=:an");            
	$chkq->bindValue(':an', $an, PDO::PARAM_STR);	
	$chkq->execute();
	if($q = $chkq->fetch()) {
		echo $q["custombg"];
	}
	exit;
}

if(_r("st") == "clearcustombg") {
	if(empty($an = _r("an"))) die("");
	$pdo = openpdo(); 	       	
	$chkq = $pdo->prepare("select custombg from servers where auton=:an");            
	$chkq->bindValue(':an', $an, PDO::PARAM_STR);	
	$chkq->execute();
	if($q = $chkq->fetch()) {
		if(!empty($q["custombg"])) unlink($target_dir.$q["custombg"]);
		$chkq1 = $pdo->prepare("update servers set custombg = NULL where auton=:an");
		$chkq1->bindValue(':an', $an, PDO::PARAM_STR);
		$chkq1->execute();
	}
	echo '1';
	exit;
}
function photo_reset_reg($f = '', $ext = '') {
	if(empty($f) || empty($ext)) {
		return '圖片讀取錯誤。'.$f.'-'.$ext;
	}	
  if ($_FILES["file"]["size"] > 10000000) {
    return '檔案大小超過限制 - 10M。';        
  }
	
	$check = getimagesize($f);

  if($check !== false) { // 如果是照片    
    if($ext != "jpg" && $ext != "png" && $ext != "jpeg" && $ext != "gif" ) {        
      return '檔案只能是 jpg, png, jpeg, gif 。';        
    }

    $image = new \claviska\SimpleImage();
    $image->fromFile($f);
    $image->autoOrient();
    return true;
  } else { // 如果不是照片

    return '非允許的檔案類型。';
  	
  }
  return true;
}


if(_r("st") === "upload") {
	if(empty($an = _r("an"))) {
	echo "伺服器編號錯誤。";
	exit;
	}
		
	$target_file = basename($_FILES["file"]["name"]);
	$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));	
	$newfilename = date("ymdHis")."_".$an."_".rand(1001, 99999).".".$imageFileType;
	$target_file = $target_dir . $newfilename;

  $pcheck = photo_reset_reg($_FILES["file"]["tmp_name"], $imageFileType);
  if($pcheck !== true) {
  	echo $pcheck;
  	exit;
  }
           
          if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
			$pdo = openpdo();
			$chkq1 = $pdo->prepare("select custombg from servers where auton=:an");
			$chkq1->bindValue(':an', $an, PDO::PARAM_STR);            
            $chkq1->execute();
			if($cq1 = $chkq1->fetch()) {
				if(!empty($cq1["custombg"])) unlink($target_dir.$cq1["custombg"]);
			}

            $chkq = $pdo->prepare("update servers set custombg = :v where auton=:an");            
            $chkq->bindValue(':an', $an, PDO::PARAM_STR);
            $chkq->bindValue(':v', $newfilename, PDO::PARAM_STR);            
            $chkq->execute();
                       
          	echo 'uploadfix';
            exit;            
          } else {
            echo '不明錯誤';
            exit;
          }
	exit;
}

function save_gift_settings($pdo, $server_id) {
    // 獲取派獎設定資料
    $table_name = _r("table_name");
    $account_field = _r("account_field");
    $item_field = _r("item_field");
    $item_name_field = _r("item_name_field");
    $quantity_field = _r("quantity_field");
    $field_names = isset($_REQUEST["field_names"]) ? $_REQUEST["field_names"] : array();
    $field_values = isset($_REQUEST["field_values"]) ? $_REQUEST["field_values"] : array();
        
    // 如果有基本設定資料，處理派獎設定主表
    if(!empty($table_name) || !empty($account_field) || !empty($item_field) || !empty($item_name_field) || !empty($quantity_field)) {
        // 先檢查是否已存在
        $check_query = $pdo->prepare("SELECT id FROM send_gift_settings WHERE server_id = :server_id");
        $check_query->bindValue(':server_id', $server_id, PDO::PARAM_STR);
        $check_query->execute();
        
        if($existing = $check_query->fetch()) {
            // 更新現有記錄
            $update_query = $pdo->prepare("
                UPDATE send_gift_settings SET 
                    table_name = :table_name,
                    account_field = :account_field,
                    item_field = :item_field,
                    item_name_field = :item_name_field,
                    quantity_field = :quantity_field
                WHERE id = :id
            ");
            $update_query->bindValue(':id', $existing['id'], PDO::PARAM_INT);
            $update_query->bindValue(':table_name', $table_name, PDO::PARAM_STR);
            $update_query->bindValue(':account_field', $account_field, PDO::PARAM_STR);
            $update_query->bindValue(':item_field', $item_field, PDO::PARAM_STR);
            $update_query->bindValue(':item_name_field', $item_name_field, PDO::PARAM_STR);
            $update_query->bindValue(':quantity_field', $quantity_field, PDO::PARAM_STR);
            $update_query->execute();
        } else {
            // 插入新記錄
            $insert_query = $pdo->prepare("
                INSERT INTO send_gift_settings (server_id, table_name, account_field, item_field, item_name_field, quantity_field) 
                VALUES (:server_id, :table_name, :account_field, :item_field, :item_name_field, :quantity_field)
            ");
            $insert_query->bindValue(':server_id', $server_id, PDO::PARAM_STR);
            $insert_query->bindValue(':table_name', $table_name, PDO::PARAM_STR);
            $insert_query->bindValue(':account_field', $account_field, PDO::PARAM_STR);
            $insert_query->bindValue(':item_field', $item_field, PDO::PARAM_STR);
            $insert_query->bindValue(':item_name_field', $item_name_field, PDO::PARAM_STR);
            $insert_query->bindValue(':quantity_field', $quantity_field, PDO::PARAM_STR);
            $insert_query->execute();
        }
    }
    
    // 處理動態欄位 - 先刪除舊的，再插入新的
    $delete_fields_query = $pdo->prepare("DELETE FROM send_gift_fields WHERE server_id = :server_id");
    $delete_fields_query->bindValue(':server_id', $server_id, PDO::PARAM_STR);
    $delete_fields_query->execute();
    
    // 插入新的動態欄位
    if(!empty($field_names) && !empty($field_values)) {
        for($i = 0; $i < count($field_names); $i++) {
            $field_name = isset($field_names[$i]) ? trim($field_names[$i]) : '';
            $field_value = isset($field_values[$i]) ? trim($field_values[$i]) : '';
            
            // 只保存有內容的欄位
            if(!empty($field_name) && !empty($field_value)) {
                $insert_field_query = $pdo->prepare("
                    INSERT INTO send_gift_fields (server_id, field_name, field_value, sort_order) 
                    VALUES (:server_id, :field_name, :field_value, :sort_order)
                ");
                $insert_field_query->bindValue(':server_id', $server_id, PDO::PARAM_STR);
                $insert_field_query->bindValue(':field_name', $field_name, PDO::PARAM_STR);
                $insert_field_query->bindValue(':field_value', $field_value, PDO::PARAM_STR);
                $insert_field_query->bindValue(':sort_order', $i, PDO::PARAM_INT);
                $insert_field_query->execute();
            }
        }
    }
}

if(_r("st") == 'addsave') {
	$an = $_REQUEST["an"];
	$names = $_REQUEST["names"];
	$id = $_REQUEST["id"];
	$base_money = $_REQUEST["base_money"];
	$stats = $_REQUEST["stats"];
	$ip  = $_REQUEST["ip"];
	$port = $_REQUEST["port"];
	$dbname = $_REQUEST["dbname"];
	$user = $_REQUEST["user"];	
	$pass = $_REQUEST["pass"];
	$pid = $_REQUEST["pid"];
	$bonusid = $_REQUEST["bonusid"];
	$bonusrate = $_REQUEST["bonusrate"];
	if(empty($bonusrate)) $bonusrate = 0;
	$gp = $_REQUEST["gp"];
	$des = $_REQUEST["des"];
	$pay_cp = $_REQUEST["pay_cp"];
	$pay_cp2 = $_REQUEST["pay_cp2"];
	$custom_bank_enable = $_REQUEST["custom_bank_enable"];
	$custom_bank_code = $_REQUEST["custom_bank_code"];
	$custom_bank_account = $_REQUEST["custom_bank_account"];
	
	if($names == "") alert("請輸入伺服器名稱。", 0);
	if($id == "") alert("請輸入尾綴代號。", 0);
	if($base_money == "") alert("請輸入最低金額。", 0);
    if(!is_numeric($base_money)) alert("最低金額只能輸入數字。", 0);
	if($stats == "") alert("請輸入狀態。", 0);
	if($stats == "1") $stats = 1;
	else $stats = 0;
	
	if($gstats == "1") $gstats = 1;
	else $gstats = 0;
	if($gstats2 == "1") $gstats2 = 1;
	else $gstats2 = 0;
	if($custom_bank_enable == "1") $custom_bank_enable = 1;
	else $custom_bank_enable = 0;
	
	/*
	if($ip == "") alert("請輸入資料庫位置。", 0);
	if($port == "") alert("請輸入資料庫端口。", 0);
	if($dbname == "") alert("請輸入資料庫名稱。", 0);
	if($user == "") alert("請輸入資料庫帳號。", 0);
	if($pass == "") alert("請輸入資料庫密碼。", 0);
	*/
	
	if($an == "") {		
	  $pdo = openpdo(); 
	  $query = $pdo->query("SELECT * FROM servers where names='".$names."' or id='".$id."'");
    $query->execute();
    if($datalist = $query->fetch()) {
    	$pdo = null;
    	alert("資料庫中已經有重覆的伺服器名稱或尾綴代號。", 0);
    	die();
    }
	  
	$input = [
	    'des' => $des, 
		'pay_cp' => $pay_cp,
		'pay_cp2' => $pay_cp2,
		'id' => $id,
		'names' => $names,
		'stats' => $stats,
		'db_ip' => $ip,
		'db_port' => $port,
		'db_name' => $dbname,
		'db_user' => $user,
		'db_pass' => $pass,
		'db_pid'=> $pid,
		'db_bonusid'=> $bonusid,
		'db_bonusrate'=> $bonusrate,
		'base_money' => $base_money,
		'HashIV' => _r("HashIV"),
		'HashKey' => _r("HashKey"),
		'MerchantID' => _r("MerchantID"),
		'pchome_app_id' => _r("pchome_app_id"),
	    'pchome_secret_code' => _r("pchome_secret_code"),
		'gomypay_shop_id' => _r("gomypay_shop_id"),
	    'gomypay_key' => _r("gomypay_key"),
		'smilepay_shop_id' => _r("smilepay_shop_id"),
	    'smilepay_key' => _r("smilepay_key"),
		'gstats' => _r("gstats"),
		'HashIV2' => _r("HashIV2"),
		'HashKey2' => _r("HashKey2"),
		'MerchantID2' => _r("MerchantID2"),
		'pchome_app_id2' => _r("pchome_app_id2"),
	    'pchome_secret_code2' => _r("pchome_secret_code2"),
		'gomypay_shop_id2' => _r("gomypay_shop_id2"),
	    'gomypay_key2' => _r("gomypay_key2"),
		'smilepay_shop_id2' => _r("smilepay_shop_id2"),
	    'smilepay_key2' => _r("smilepay_key2"),
		'gstats2' => _r("gstats2"),
		'paytable' => _r("paytable"),
		'gp' => $gp,
		'custom_bank_enable' => $custom_bank_enable,
		'custom_bank_code' => $custom_bank_code,
		'custom_bank_account' => $custom_bank_account
	];
	$dbclassupdatesql = implode(",", array_keys($input));
	foreach($input as $k => $v ) {
	  $i2arr[] = ':'.$k;
	  $dbclassupdateprep[':'.$k] = $v;
	}
	$i2sql = implode(",", $i2arr);
    $query = $pdo->prepare('INSERT INTO servers ('.$dbclassupdatesql.') VALUES ('.$i2sql.')');    
    $query->execute($dbclassupdateprep);

	// 處理派獎設定
	save_gift_settings($pdo, $new_server_id);
    
    alert("伺服器新增完成。", "index.php");
    die();
		
	} else {

		$pdo = openpdo(); 
		$input = [
			'des' => $des,
			'pay_cp' => $pay_cp,
			'pay_cp2' => $pay_cp2,
			'id' => $id,
			'names' => $names,
			'stats' => $stats,
			'db_ip' => $ip,
			'db_port' => $port,
			'db_name' => $dbname,
			'db_user' => $user,
			'db_pass' => $pass,
			'db_pid'=> $pid,
			'db_bonusid'=> $bonusid,
			'db_bonusrate'=> $bonusrate,
			'base_money' => $base_money,
			'HashIV' => _r("HashIV"),
			'HashKey' => _r("HashKey"),
			'MerchantID' => _r("MerchantID"),
			'pchome_app_id' => _r("pchome_app_id"),
			'pchome_secret_code' => _r("pchome_secret_code"),
			'gomypay_shop_id' => _r("gomypay_shop_id"),
			'gomypay_key' => _r("gomypay_key"),
			'smilepay_shop_id' => _r("smilepay_shop_id"),
			'smilepay_key' => _r("smilepay_key"),
			'gstats' => _r("gstats"),
			'HashIV2' => _r("HashIV2"),
			'HashKey2' => _r("HashKey2"),
			'MerchantID2' => _r("MerchantID2"),
			'pchome_app_id2' => _r("pchome_app_id2"),
			'pchome_secret_code2' => _r("pchome_secret_code2"),
			'gomypay_shop_id2' => _r("gomypay_shop_id2"),
			'gomypay_key2' => _r("gomypay_key2"),
			'smilepay_shop_id2' => _r("smilepay_shop_id2"),
			'smilepay_key2' => _r("smilepay_key2"),
			'gstats2' => _r("gstats2"),
			'paytable' => _r("paytable"),
			'gp' => $gp,
			'custom_bank_enable' => $custom_bank_enable,
			'custom_bank_code' => $custom_bank_code,
			'custom_bank_account' => $custom_bank_account
		];
		$dbclassupdateprep = [];
		
		foreach($input as $k => $v ) {
			$dbclassupdatesql[] = $k.'=:'.$k;
			$dbclassupdateprep[':'.$k] = $v;
		}
		
		$dbclassupdateprep[":an"] = $an;
		$query = $pdo->prepare('UPDATE servers SET '.implode(",", $dbclassupdatesql).' where auton=:an');    
		$query->execute($dbclassupdateprep);

		// 處理派獎設定
    	save_gift_settings($pdo, $an);
		
		alert("伺服器修改完成。", "index.php");
		die();
	}
	
}

if(!empty($an = _r("an"))) {
	  $pdo = openpdo(); 
    $query    = $pdo->query("SELECT * FROM servers where auton='".$_REQUEST["an"]."'");
    $query->execute();
    $datalist = $query->fetch();

	// 載入派獎設定資料
    $gift_query = $pdo->prepare("SELECT * FROM send_gift_settings WHERE server_id = :server_id");
    $gift_query->bindValue(':server_id', $an, PDO::PARAM_STR);
    $gift_query->execute();
    $gift_settings = $gift_query->fetch(PDO::FETCH_ASSOC);
    
    if($gift_settings) {
        $datalist['table_name'] = $gift_settings['table_name'];
        $datalist['account_field'] = $gift_settings['account_field'];
        $datalist['item_field'] = $gift_settings['item_field'];
        $datalist['item_name_field'] = $gift_settings['item_name_field'];
        $datalist['quantity_field'] = $gift_settings['quantity_field'];
    }
    
    // 載入動態欄位資料
    $fields_query = $pdo->prepare("SELECT * FROM send_gift_fields WHERE server_id = :server_id ORDER BY sort_order");
    $fields_query->bindValue(':server_id', $an, PDO::PARAM_STR);
    $fields_query->execute();
    $dynamic_fields = $fields_query->fetchAll(PDO::FETCH_ASSOC);

    $tt = "修改";
    $tt2 = "?st=addsave";
    if(!$datalist['base_money']) $base_money = 0;
    else $base_money = $datalist['base_money'];
    $sts = $datalist['stats'];
    $gstats = $datalist['gstats'];
	$gstats2 = $datalist['gstats2'];
	$custom_bank_enable = $datalist['custom_bank_enable'];
  } else {
    $tt = "新增";
    $tt2 = "?st=addsave";
    $base_money = 0;
    $sts = 1;
    $gstats = 1;
	$gstats2 = 1;
	$custom_bank_enable = 0;
}
top_html();
?>
<link rel="stylesheet" href="assets/css/jquery.fileupload.css">
<link rel="stylesheet" href="assets/css/jquery.fileupload-ui.css">
<noscript><link rel="stylesheet" href="assets/css/jquery.fileupload-noscript.css"></noscript>
<noscript><link rel="stylesheet" href="assets/css/jquery.fileupload-ui-noscript.css"></noscript>
			<!-- 
				MIDDLE 
			-->
			<section id="middle">
				<div id="content" class="dashboard padding-20">

					<!-- 
						PANEL CLASSES:
							panel-default
							panel-danger
							panel-warning
							panel-info
							panel-success

						INFO: 	panel collapse - stored on user localStorage (handled by app.js _panels() function).
								All pannels should have an unique ID or the panel collapse status will not be stored!
					-->
					<div id="panel-1" class="panel panel-default">
						<div class="panel-heading">
							<span class="title elipsis">
								<strong><a href="index.php">伺服器管理</a></strong> <!-- panel title -->
                <small class="size-12 weight-300 text-mutted hidden-xs"><?=$tt?>伺服器</small>
							</span>

							<!-- right options -->
							<ul class="options pull-right list-inline">								
								<li><a href="#" class="opt panel_fullscreen hidden-xs" data-toggle="tooltip" title="Fullscreen" data-placement="bottom"><i class="fa fa-expand"></i></a></li>
							</ul>
							<!-- /right options -->

						</div>

						<!-- panel content -->
						<div class="panel-body">
							
							<a href="<?=$_SERVER['HTTP_REFERER']?>" class="btn btn-primary"><i class="glyphicon glyphicon-arrow-left"></i> 上一頁</a>
							<div class="table">
								<form name="form1" method="post" action="server_add.php<?=$tt2?>" onsubmit="return validate_form();">
	<table class="table table-bordered">
						  <tbody>
<tr><td style="background:#666;color:white;text-align:center;">伺服器設定</td></tr>
<tr><td>群組：<input name="gp" id="gp" type="text" value="<?=$datalist['gp']?>">&nbsp;&nbsp;<small>(如無留空)</small></td></tr>
<tr><td>排序：<input name="des" id="des" type="number" value="<?=$datalist['des']?>">&nbsp;&nbsp;<small>(群組排序只能數字，數字越大排序越高，用於1服2服3服使用)</small></td></tr>
<tr><td>伺服器名稱：<input name="names" id="names" type="text" value="<?=$datalist['names']?>" required></td></tr>
<tr><td>尾綴代號：<input name="id" id="id" type="text" value="<?=$datalist['id']?>" required>
	<br>
	<small><font color="red">請使用英數，不可使用中文。</font>尾綴代號是用來定義網址後綴名稱，如將本伺服器的尾綴代號設定為 line1<br>前台贊助網址就是 <?=$weburl?>line1<br>設定為 line2，前台贊助網址就是 <?=$weburl?>line2</small></td></tr>
<tr><td>最低金額：<input name="base_money" id="base_money" type="number" value="<?=$base_money?>" required>&nbsp;&nbsp;填入 0 為最少需贊助 100
<tr><td>狀態：<input type="radio" name="stats" value="1"<?if($sts == 1) echo " checked"?> required> 開啟 &nbsp;&nbsp;<input type="radio" name="stats" value="0"<?if($sts != 1) echo " checked"?>> 停用</td></tr>
<tr><td>
<?php
if(empty($an)) echo '修改時才能上傳底圖';
else {
echo '<div class="col-md-5 col-xs-12 margin-bottom-10">自訂開單頁面底圖 (建議X1024以上)：
<span class="btn btn-info btn-sm fileinput-button"><span>上傳圖片</span><input id="fileuploads" type="file" class="fileupload" name="file"></span>
<div id="progress" class="progress progress-striped" style="display:none"><div class="bar progress-bar progress-bar-lovepy"></div></div>
<a href="javascript:removecustombg();" class="btn btn-danger btn-sm">移除底圖</a>
</div>';
}
?>
<div id="custombgdiv" class="col-md-12 col-xs-12">

</div>

</td></tr>
<tr><td style="background:#666;color:white;text-align:center;">資料庫設定</td></tr>
<tr><td>資料庫位置(IP)：<input name="ip" id="ip" type="text" value="<?=$datalist['db_ip']?>">&nbsp;&nbsp;&nbsp;&nbsp;<a href="#t" onclick="test_connect();" class="btn btn-default btn-xs">連線測試</a></td></tr>
<tr><td>資料庫端口(PORT)：<input name="port" id="port" type="text" value="<?=$datalist['db_port']?>"></td></tr>
<tr><td>資料庫名稱(DBNAME)：<input name="dbname" id="dbname" type="text" value="<?=$datalist['db_name']?>"></td></tr>
<tr><td>資料庫帳號(USER)：<input name="user" id="user" type="text" value="<?=$datalist['db_user']?>"></td></tr>
<tr><td>資料庫密碼(PASS)：<input name="pass" id="pass" type="text" value="<?=$datalist['db_pass']?>"></td></tr>
<tr><td>資料表名稱(Table)：
    <input type="radio" name="paytable" value="shop_user"<?if($datalist['paytable'] == 'shop_user') echo " checked"?>> shop_user&nbsp;&nbsp;
	<input type="radio" name="paytable" value="ezpay"<?if($datalist['paytable'] == 'ezpay') echo " checked"?>> ezpay&nbsp;&nbsp;
</td></tr>
<tr><td>贊助幣代碼(P_ID)：<input name="pid" id="pid" type="text" value="<?=$datalist['db_pid']?>"></td></tr>
<!--<tr><td>紅利幣代碼(BONUS_ID)：<input name="bonusid" id="bonusid" type="text" value="<?=$datalist['db_bonusid']?>"> 倍率：<input name="bonusrate" id="bonusrate" type="number" value="<?=$datalist['db_bonusrate']?>" min="0" max="100"> <small>(0 ~ 100)</small></td></tr>
-->
<tr><td style="background:#6666ff;color:white;text-align:center;">金流設定</td></tr>
<tr><td>信用卡金流服務商選擇：
	<input type="radio" name="pay_cp" value="pchome"<?if($datalist['pay_cp'] == 'pchome') echo " checked"?>> 支付連&nbsp;&nbsp;
	<input type="radio" name="pay_cp" value="ecpay"<?if($datalist['pay_cp'] == 'ecpay') echo " checked"?>> 綠界&nbsp;&nbsp;
	<input type="radio" name="pay_cp" value="ebpay"<?if($datalist['pay_cp'] == 'ebpay') echo " checked"?>> 藍新&nbsp;&nbsp;
	<input type="radio" name="pay_cp" value="gomypay"<?if($datalist['pay_cp'] == 'gomypay') echo " checked"?>> 萬事達&nbsp;&nbsp;
	<input type="radio" name="pay_cp" value="smilepay"<?if($datalist['pay_cp'] == 'smilepay') echo " checked"?>> 速買配
</td></tr>
<tr><td class="normaldiv">
特店編號：<input name="MerchantID" id="MerchantID" type="text" value="<?=$datalist['MerchantID']?>">&nbsp;&nbsp;
介接 HashKey：<input name="HashKey" id="HashKey" type="text" value="<?=$datalist['HashKey']?>">&nbsp;&nbsp;
介接 HashIV：<input name="HashIV" id="HashIV" type="text" value="<?=$datalist['HashIV']?>">&nbsp;&nbsp;
</td></tr>
<tr><td class="pchomediv">
支付連 APP_ID：<input name="pchome_app_id" id="pchome_app_id" type="text" value="<?=$datalist['pchome_app_id']?>">&nbsp;&nbsp;
支付連 SECRET_CODE：<input name="pchome_secret_code" id="pchome_secret_code" type="text" value="<?=$datalist['pchome_secret_code']?>">
</td></tr>
<tr><td class="gomypaydiv">
	Gomypay 商店代號：<input name="gomypay_shop_id" id="gomypay_shop_id" type="text" value="<?=$datalist['gomypay_shop_id']?>">&nbsp;&nbsp;
    Gomypay 交易驗證碼：<input name="gomypay_key" id="gomypay_key" type="text" value="<?=$datalist['gomypay_key']?>">
</td></tr>
<tr><td class="smilepaydiv">
    速買配 商家代號：<input name="smilepay_shop_id" id="smilepay_shop_id" type="text" value="<?=$datalist['smilepay_shop_id']?>">&nbsp;&nbsp;
    速買配 檢查碼 Verify_key：<input name="smilepay_key" id="smilepay_key" type="text" value="<?=$datalist['smilepay_key']?>">
</td></tr>
<tr><td>
    信用卡環境：<input type="radio" name="gstats" value="1"<?if($gstats == 1) echo " checked"?>> 正式環境 &nbsp;&nbsp;
               <input type="radio" name="gstats" value="0"<?if($gstats != 1) echo " checked"?>> 模擬環境
</td></tr>

<tr><td>超商代碼與ATM轉帳金流服務商選擇：<input type="radio" name="pay_cp2" value="ecpay"<?if($datalist['pay_cp2'] == 'ecpay') echo " checked"?>> 綠界 &nbsp;&nbsp;
	<input type="radio" name="pay_cp2" value="ebpay"<?if($datalist['pay_cp2'] == 'ebpay') echo " checked"?>> 藍新&nbsp;&nbsp;
	<input type="radio" name="pay_cp2" value="gomypay"<?if($datalist['pay_cp2'] == 'gomypay') echo " checked"?>> 萬事達&nbsp;&nbsp;
	<input type="radio" name="pay_cp2" value="smilepay"<?if($datalist['pay_cp2'] == 'smilepay') echo " checked"?>> 速買配&nbsp;&nbsp;
	<input type="radio" name="pay_cp2" value="opay"<?if($datalist['pay_cp2'] == 'opay') echo " checked"?>> 歐付寶&nbsp;&nbsp;
</td></tr>
<tr><td class="normaldiv2">
特店編號：<input name="MerchantID2" id="MerchantID2" type="text" value="<?=$datalist['MerchantID2']?>">&nbsp;&nbsp;
介接 HashKey：<input name="HashKey2" id="HashKey2" type="text" value="<?=$datalist['HashKey2']?>">&nbsp;&nbsp;
介接 HashIV：<input name="HashIV2" id="HashIV2" type="text" value="<?=$datalist['HashIV2']?>">&nbsp;&nbsp;
</td></tr>
<tr><td class="pchomediv2">
支付連 APP_ID：<input name="pchome_app_id2 id="pchome_app_id2" type="text" value="<?=$datalist['pchome_app_id2']?>">&nbsp;&nbsp;
支付連 SECRET_CODE：<input name="pchome_secret_code2" id="pchome_secret_code2" type="text" value="<?=$datalist['pchome_secret_code2']?>">
</td></tr>
<tr><td class="gomypaydiv2">
	Gomypay 商店代號：<input name="gomypay_shop_id2" id="gomypay_shop_id2" type="text" value="<?=$datalist['gomypay_shop_id2']?>">&nbsp;&nbsp;
    Gomypay 交易驗證碼：<input name="gomypay_key2" id="gomypay_key2" type="text" value="<?=$datalist['gomypay_key2']?>">
</td></tr>
<tr><td class="smilepaydiv2">
    速買配 商家代號：<input name="smilepay_shop_id2" id="smilepay_shop_id2" type="text" value="<?=$datalist['smilepay_shop_id2']?>">&nbsp;&nbsp;
    速買配 檢查碼 Verify_key：<input name="smilepay_key2" id="smilepay_key2" type="text" value="<?=$datalist['smilepay_key2']?>">
</td></tr>
<tr><td>
    其他金流環境：<input type="radio" name="gstats2" value="1"<?if($gstats2 == 1) echo " checked"?>> 正式環境 &nbsp;&nbsp;
               <input type="radio" name="gstats2" value="0"<?if($gstats2 != 1) echo " checked"?>> 模擬環境
</td></tr>
<tr><td>
    <input type="checkbox" name="custom_bank_enable" id="custom_bank_enable" value="1"<?if($custom_bank_enable == 1) echo " checked"?>> 
    <label for="custom_bank_enable">啟用自定義匯款資訊</label>
    <span class="custom_bank_div" style="display:<?if($custom_bank_enable == 1) echo 'inline'; else echo 'none';?>;">
    &nbsp;&nbsp;銀行代碼：<input name="custom_bank_code" id="custom_bank_code" type="text" value="<?=$datalist['custom_bank_code']?>" style="width:100px;">&nbsp;&nbsp;
    帳號：<input name="custom_bank_account" id="custom_bank_account" type="text" value="<?=$datalist['custom_bank_account']?>" style="width:150px;">
    </span>
</td></tr>

<tr><td style="background:#6666ff;color:white;text-align:center;">派獎設定</td></tr>
<tr><td>
    資料表名稱：<input name="table_name" id="table_name" type="text" value="<?=$datalist['table_name']?>">&nbsp;&nbsp;
    帳號欄位：<input name="account_field" id="account_field" type="text" value="<?=$datalist['account_field']?>">
    道具編號：<input name="item_field" id="item_field" type="text" value="<?=$datalist['item_field']?>">&nbsp;&nbsp;
    道具名稱：<input name="item_name_field" id="item_name_field" type="text" value="<?=$datalist['item_name_field']?>">&nbsp;&nbsp;
    數量欄位：<input name="quantity_field" id="quantity_field" type="text" value="<?=$datalist['quantity_field']?>">
</td></tr>
<tr><td>
    <button type="button" onclick="addField()" style="background-color: #28a745; color: white; border: none; padding: 8px 15px; cursor: pointer; margin-bottom: 10px;">新增欄位</button>
    <div id="dynamic_fields_container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background-color: #fafafa; display: none;">
        <div id="dynamic_fields">
        </div>
    </div>
</td></tr>

  </tbody>
	</table>
 					  
		  <div align="center"> 
          <?if($_REQUEST["an"] != "") {?>
          <input type="submit" name="Submit" class="btn btn-info btn-sm" value="確定修改">
          <input type="hidden" id="an" name="an" value="<?=$_REQUEST["an"]?>">
		  <?} else {?>
		  <input type="submit" name="Submit" class="btn btn-info btn-sm" value="確定新增">
		  <?}?>
        </div>
</form>
	
</div>

						</div>
						<!-- /panel content -->


					</div>

				</div>
			</section>
			<!-- /MIDDLE -->

<?down_html()?>

<script type="text/javascript" src="assets/js/jquery.fileupload.js"></script>
<script type="text/javascript">
$(function() {  
	$("input[name=pay_cp]").on("change", function() {
		pay_check();
	});
	pay_check();

	$("input[name=pay_cp2]").on("change", function() {
		pay_check2();
	});
	pay_check2();

	$("#custom_bank_enable").on("change", function() {
		custom_bank_check();
	});
	custom_bank_check();

	loadcustombgdiv();
	$(".fileupload").each(function() {

var $this = $(this), $thisid = $this.attr("id"), $progress = $this.closest("div").find(".progress");    	

$ffileu = $this.fileupload({
url: "server_add.php?st=upload&an=<?=$an?>",
type: "POST",
dropZone: $this,
dataType: 'html',        
done: function (e, data) {
	switch(data.jqXHR.responseText) {
		case "uploadfix":
		$progress.find(".progress-bar").css("width", "0px").stop().parent().hide();
		loadcustombgdiv();
					
		break;

		default:
	 $progress.find(".progress-bar").css("width", "0px").stop().parent().hide();
	 alert(data.jqXHR.responseText);
		break;
	}
},       
progressall: function (e, data) {        	
	var progress = parseInt(data.loaded / data.total * 100, 10);            
	$progress.show().find(".progress-bar").css(
		'width',
		progress + '%'
	);
},
add: function(e, data) {        	

	  data.url = "server_add.php?st=upload&an=<?=$an?>";
	
	data.submit();
}

}).prop('disabled', !$.support.fileInput)
.parent().addClass($.support.fileInput ? undefined : 'disabled');

});   
});
function pay_check() {
  v = $("input[name=pay_cp]:checked").val();
  $(".normaldiv").hide();
  $(".gomypaydiv").hide();
  $(".pchomediv").hide();
  $(".smilepaydiv").hide();
  switch(v) {
	  case "pchome":
          $(".pchomediv").show();
	  break;
	  case "gomypay":
		  $(".gomypaydiv").show();
	  break;
	  case "smilepay":
		  $(".smilepaydiv").show();
	  break;
	  default:
	  $(".normaldiv").show();	  
	  break;
  } 
  
}
function pay_check2() {
  v = $("input[name=pay_cp2]:checked").val();
  $(".normaldiv2").hide();
  $(".gomypaydiv2").hide();
  $(".pchomediv2").hide();
  $(".smilepaydiv2").hide();
  switch(v) {
	  case "pchome":
          $(".pchomediv2").show();
	  break;
	  case "gomypay":
		  $(".gomypaydiv2").show();
	  break;
	  case "smilepay":
		  $(".smilepaydiv2").show();
	  break;
	  default:
	  $(".normaldiv2").show();	  
	  break;
  } 
  
}
function loadcustombgdiv() {
 $.ajax({
  method: "POST",
  url: "server_add.php",
  data: { st: "readcustombg", an: "<?=$an?>" }
}).done(function( msg ) {
  if(msg) {
	  $newimg = $("<img></img>");
	  $newimg.attr("src", "../assets/images/custombg/"+msg).attr("width", "auto").attr("height", 150);
	  $("#custombgdiv").html($newimg);
  }
});

}
function removecustombg() {
$.ajax({
  method: "POST",
  url: "server_add.php",
  data: { st: "clearcustombg", an: "<?=$an?>" }
}).done(function( msg ) {
  if(msg == 1) {
	  $("#custombgdiv").html("");
  }
});
}
function test_connect() {
	if(!$("#ip").val()) {
		alert("要進行連線測試必須填寫資料庫位置。");
		$("#ip").focus();
		return false;
	}
	if(!$("#port").val()) {
		alert("要進行連線測試必須填寫資料庫端口。");
		$("#port").focus();
		return false;
	}
	if(!$("#dbname").val()) {
		alert("要進行連線測試必須填寫資料庫名稱。");
		$("#dbname").focus();
		return false;
	}
	if(!$("#user").val()) {
		alert("要進行連線測試必須填寫資料庫帳號。");
		$("#user").focus();
		return false;
	}
	if(!$("#pass").val()) {
		alert("要進行連線測試必須填寫資料庫密碼。");
		$("#pass").focus();
		return false;
	}
	var $w = screen.width/4;
	var $h = screen.height/4;
	var $left = (screen.width/2)-($w/2);
    var $top = (screen.height/2)-($h/2);
    var $paytable = $("input[name=paytable]:checked").val();
	if(!$paytable) $paytable = "shop_user";
	var $testconnectstr = "ip="+$("#ip").val()+"&port="+$("#port").val()+"&dbname="+$("#dbname").val()+"&user="+$("#user").val()+"&pass="+$("#pass").val()+"&tb="+$paytable;

	window.open('server_test_connect.php?'+$testconnectstr,'test_connect','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width='+$w+', height='+$h+', top='+$top+', left='+$left);
	
	
}

function custom_bank_check() {
	if($("#custom_bank_enable").is(":checked")) {
		$(".custom_bank_div").css("display", "inline");
	} else {
		$(".custom_bank_div").css("display", "none");
	}
}

function validate_form() {
	if($("#custom_bank_enable").is(":checked")) {
		if($("#custom_bank_code").val() == "") {
			alert("啟用自定義匯款資訊時，請輸入銀行代碼。");
			$("#custom_bank_code").focus();
			return false;
		}
		if($("#custom_bank_account").val() == "") {
			alert("啟用自定義匯款資訊時，請輸入帳號。");
			$("#custom_bank_account").focus();
			return false;
		}
	}
	return true;
}
</script>

<script>
let fieldCounter = 0;

function addField() {
    fieldCounter++;
    const dynamicFields = document.getElementById('dynamic_fields');
    const newField = document.createElement('div');
    newField.className = 'field_pair';
    newField.id = 'field_pair_' + fieldCounter;
    newField.style.cssText = 'margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9;';
    newField.innerHTML = `
        <div style="display: inline-block; margin-right: 15px;">
            欄位名稱：<input name="field_names[]" type="text" value="" style="width: 150px;">
        </div>
        <div style="display: inline-block; margin-right: 15px;">
            欄位資料：<input name="field_values[]" type="text" value="" style="width: 200px;">
        </div>
        <button type="button" class="delete_field" onclick="removeField(${fieldCounter})" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;">刪除</button>
    `;
    dynamicFields.appendChild(newField);
    updateDeleteButtons();
    updateScrollContainer();

    // 顯示容器
    const container = document.getElementById('dynamic_fields_container');
    container.style.display = 'block';

    // 滾動到最新添加的欄位
    container.scrollTop = container.scrollHeight;
}

function removeField(id) {
    const fieldPairs = document.querySelectorAll('.field_pair');
    if (fieldPairs.length > 0) {
        document.getElementById('field_pair_' + id).remove();
        updateDeleteButtons();
        updateScrollContainer();

        // 如果沒有欄位了，隱藏容器
        const remainingFields = document.querySelectorAll('.field_pair');
        if (remainingFields.length === 0) {
            const container = document.getElementById('dynamic_fields_container');
            container.style.display = 'none';
        }
    }
}

function updateDeleteButtons() {
    const fieldPairs = document.querySelectorAll('.field_pair');
    const deleteButtons = document.querySelectorAll('.delete_field');

    deleteButtons.forEach(button => {
        button.disabled = false;
        button.style.backgroundColor = '#dc3545';
        button.style.cursor = 'pointer';
    });
}

function updateScrollContainer() {
    const fieldPairs = document.querySelectorAll('.field_pair');
    const container = document.getElementById('dynamic_fields_container');
    
    // 計算每個欄位組的大概高度 (約65px包含margin和padding)
    const estimatedHeight = fieldPairs.length * 65;
    const maxHeightFor5Items = 5 * 65; // 約325px
    
    if (fieldPairs.length > 5) {
        container.style.maxHeight = maxHeightFor5Items + 'px';
        container.style.overflowY = 'auto';
    } else {
        container.style.maxHeight = 'none';
        container.style.overflowY = 'visible';
    }
}

// 載入已存在的動態欄位
function loadExistingDynamicFields() {
    if (dynamicFieldsData && dynamicFieldsData.length > 0) {
        console.log('Loading existing dynamic fields:', dynamicFieldsData);
        
        // 清除預設的第一個欄位
        document.getElementById('dynamic_fields').innerHTML = '';
        fieldCounter = 0;
        
        // 載入每一個已存在的欄位
        dynamicFieldsData.forEach(function(field) {
            fieldCounter++;
            const dynamicFields = document.getElementById('dynamic_fields');
            const newField = document.createElement('div');
            newField.className = 'field_pair';
            newField.id = 'field_pair_' + fieldCounter;
            newField.style.cssText = 'margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9;';
            newField.innerHTML = `
                <div style="display: inline-block; margin-right: 15px;">
                    欄位名稱：<input name="field_names[]" type="text" value="${field.field_name}" style="width: 150px;">
                </div>
                <div style="display: inline-block; margin-right: 15px;">
                    欄位資料：<input name="field_values[]" type="text" value="${field.field_value}" style="width: 200px;">
                </div>
                <button type="button" class="delete_field" onclick="removeField(${fieldCounter})" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;">刪除</button>
            `;
            dynamicFields.appendChild(newField);
        });
        
        // 載入完成，不添加預設欄位

        updateDeleteButtons();
        updateScrollContainer();

        // 如果有載入欄位，顯示容器
        if (fieldCounter > 0) {
            const container = document.getElementById('dynamic_fields_container');
            container.style.display = 'block';
        }
    }
}

// 頁面載入時初始化
document.addEventListener('DOMContentLoaded', function() {
    updateScrollContainer();
    loadExistingDynamicFields();
});
</script>