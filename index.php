<?php
include("myadm/include.php");
if($_REQUEST["st"] == "readbi" && $_REQUEST["v"] != "") {	
	if($_SESSION["foran"] == "") die("伺服器資料錯誤-8000201。");
	if($_SESSION["serverid"] == "") die("伺服器資料錯誤-8000202。");
	  $v = $_REQUEST["v"];
	  $pdo = openpdo(); 	
    $query    = $pdo->prepare("SELECT * FROM servers_bi where stats=1 and foran=? order by money2 asc");
    $query->execute(array($_SESSION["foran"]));
    if(!$datalist = $query->fetchAll()) die($v);
    $bb = 0;
    foreach ($datalist as $datainfo) {
    	$m1 = $datainfo["money1"];
    	$m2 = $datainfo["money2"];
    	$bi = $datainfo["bi"];
    	if($v >= $m1 && $v <= $m2) $bb = $bi;
    }
    if($bb==0) $bb = 1;
    $bb = $bb * $v;
    echo $bb;
    die();
}


if($_REQUEST["st"] == "send") {
	if($_SESSION["foran"] == "") alert("伺服器資料錯誤-8000201。", 0);
	if($_SESSION["serverid"] == "") alert("伺服器資料錯誤-8000202。", 0);
	$gid = $_REQUEST["gid"];
	//$cid = $_REQUEST["cid"];
	$money = $_REQUEST["money"];
	$pt = $_REQUEST["pt"];
	$psn = $_REQUEST["psn"];
	
	if($gid == "") alert("請輸入遊戲帳號。", 0);
	//if($cid == "") alert("請輸入角色名稱。", 0);
	if($money == "") alert("請輸入繳款金額。", 0);
	$money = intval($money);	
	if(!is_numeric($money)) alert("繳款金額只能輸入數字。", 0);
	
	if($pt == "") alert("請選擇繳款方式。", 0);
	if($psn == "") alert("請輸入驗證碼。", 0);
	
	if($psn != $_SESSION['excellence_fun_code']) alert("驗證碼錯誤。", 0);
	
	// check database config
	$pdo = openpdo(); 
	$dbqq = $pdo->prepare("SELECT * FROM servers where auton=?");
	$dbqq->execute(array($_SESSION["foran"]));
	if(!$datalist = $dbqq->fetch()) alert("伺服器尚未就緒。", 0);
	else {
		if(!$datalist["db_ip"] || !$datalist["db_port"] || !$datalist["db_name"] || !$datalist["db_user"] || !$datalist["db_pass"]) alert("伺服器尚未就緒 - 資料庫未設定完成。", 0);		
	}
	$pay_cp = $datalist["pay_cp"];
	$pay_cp2 = $datalist["pay_cp2"];
	
	// check game id
	$test_mode = false; // 測試模式：true=跳過帳號驗證, false=正常驗證

	if(!$test_mode) {
		$gamepdo = opengamepdo($datalist["db_ip"], $datalist["db_port"], $datalist["db_name"], $datalist["db_user"], $datalist["db_pass"]);
		$gameq   = $gamepdo->prepare("select * from accounts where LOWER(login)=?");
		$gameq->execute(array(strtolower($gid)));
		if(!$gameq->fetch()) alert("遊戲內無此帳號，請確認您的遊戲帳號。", 0);
	}
	
	// get ip
	  $user_IP = get_real_ip();    
    
  // make order id
    $orderid = date("ymdHis");
    $orderid .= strtoupper(substr(uniqid(rand()),0,3));
	//算比值
	  $bb = 0;    
	  $qq = $pdo->prepare("SELECT * FROM servers_bi where stats=1 and foran=? order by money2 asc");
    $qq->execute(array($_SESSION["foran"]));
    if($datalist = $qq->fetchAll()) {
    foreach ($datalist as $datainfo) {
    	$m1 = $datainfo["money1"];
    	$m2 = $datainfo["money2"];
		$bi = $datainfo["bi"];		
    	if($money >= $m1 && $money <= $m2) $bb = $bi;
    }
    }
    
	if($pt == 5) { // 信用卡金流
        $pay_cp_check = $pay_cp;
    } else {
		$pay_cp_check = $pay_cp2;
	}

	if($pt == 999) {
		$pay_cp_check = "custom_bank";
	}
	
    if($bb==0) $bb = 1;
    $bmoney = $bb * $money;
  
	$input = array(':foran' => $_SESSION["foran"],':serverid' => $_SESSION["serverid"],':gameid' => $gid,':money' => $money,':bmoney' => $bmoney,':paytype' => $pt,':bi' => $bb, ':userip' => $user_IP, ':orderid' => $orderid, 'pay_cp' => $pay_cp_check);
    $query = $pdo->prepare("INSERT INTO servers_log (foran, serverid, gameid, money, bmoney, paytype, bi, userip, orderid, pay_cp) VALUES(:foran,:serverid,:gameid,:money,:bmoney,:paytype,:bi,:userip,:orderid, :pay_cp)");
    $query->execute($input);
    $result = $pdo->lastInsertId();
    $_SESSION["lastan"] = $result;

	if(!empty($shareid = _s("shareid"))) {
		$shq = $pdo->prepare("UPDATE servers_log SET shareid=? where auton=?");
		$shq->execute(array($shareid, $result));
	}
	
		switch($pay_cp_check) {
			case "pchome":
			header('Location: pchome_next.php');
			break;
			case "ebpay":
			header('Location: ebpay_next.php');
			break;
			case "gomypay":
			header('Location: gomypay_next.php');
			break;	
			case "smilepay":
			header('Location: smilepay_next.php');
			break;	
			case "funpoint":
			header('Location: funpoint_next.php');
			break;
			case "custom_bank":
			header('Location: custom_next.php');
			break;
			default:
			header('Location: next.php');
			break;    	
		}		

	die();
}

$id = $_REQUEST["id"];

if($id == "") die("404 Not Found");

	$pdo = openpdo(); 	
    $query    = $pdo->prepare("SELECT * FROM servers where id=?");
    $query->execute(array($id));
    if(!$datalist = $query->fetch()) die("404 Not Found");
    
    if($datalist["stats"] == 0) die("server stop.");
    $_SESSION["foran"] = $datalist["auton"];
    $_SESSION["serverid"] = $datalist["id"];
    
    if(!$datalist["db_ip"] || !$datalist["db_port"] || !$datalist["db_name"] || !$datalist["db_user"] || !$datalist["db_pass"]) $dbstat = "<small style='color:#999'>資料庫尚未就緒</small>";
    else $dbstat = "";

    $base_money = $datalist["base_money"];
    if(!$base_money) $base_money = 100;

    $user_ip = get_real_ip();    
    
	if(!empty($ss = _r("s"))) {
		$shq = $pdo->prepare("SELECT * FROM shareuser where uid=? limit 1");
		$shq->execute(array($ss));
		if($shqi = $shq->fetch()) $_SESSION["shareid"] = $shqi["uid"];
	}
	
    $custombg = $datalist["custombg"];
	if(empty($custombg)) $custombg = "assets/images/particles_bg.jpg";
	else $custombg = "assets/images/custombg/".$custombg;
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title>自動贊助系統</title>
		<meta name="description" content="自動贊助系統" />
		<meta name="Author" content="<?=$weburl?>" />

		<!-- mobile settings -->
		<meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, user-scalable=0" />
		<!--[if IE]><meta http-equiv='X-UA-Compatible' content='IE=edge,chrome=1'><![endif]-->

		<!-- CORE CSS -->
		<link href="/assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />

		<!-- THEME CSS -->
		<link href="/assets/css/essentials.css" rel="stylesheet" type="text/css" />
		<link href="/assets/css/layout.css?v=1.1" rel="stylesheet" type="text/css" />
		<link rel="icon" sizes="16x16" href="https://i.imgur.com/AAKKGSu.png">

		<!-- PAGE LEVEL SCRIPTS -->		
		<link href="/assets/css/color_scheme/green.css" rel="stylesheet" type="text/css" id="color_scheme" />
	</head>

	<body>

		<!-- wrapper -->
		<div id="wrapper">

			<!-- SLIDER -->
			<section id="slider" class="fullheight" style="background:url('<?=$custombg?>')">
				<span class="overlay dark-2"><!-- dark overlay [0 to 9 opacity] --></span>

				<canvas id="canvas-particle" data-rgb="156,217,249"><!-- CANVAS PARTICLES --></canvas>

				<div class="display-table">
					<div class="display-table-cell vertical-align-middle">
						
						<div class="container text-center">
							<h2>自動贊助系統</h2>
							<h1 class="nomargin wow fadeInUp" data-wow-delay="0.4s">
								
								<!--
									TEXT ROTATOR
									data-animation="fade|flip|flipCube|flipUp|spin"
								-->
								<span class="rotate" data-animation="fade" data-speed="1500">
									自助繳款中心, 快速金流繳費, 保障安全隱私
								</span>
							</h1>
							<hr>
							<div class="main-form">
							<form method="post" action="index.php">
								<div class="col-md-12 col-xs-12 main-title padding-bottom-20 bold">遊戲伺服器：【<?=$datalist["names"]?>】<?=$dbstat?></div>
								<?php
								if($gp = $datalist["gp"]) {
									$query2 = $pdo->prepare("SELECT * FROM servers where gp=? order by des desc");
                                    $query2->execute(array($gp));
                                     if($gsarr = $query2->fetchALL()) {    
									  
										echo '<div class="col-md-12 col-xs-12 main-title padding-bottom-20">';
										echo '<select class="form-control" onchange="this.options[this.selectedIndex].value && (window.location = this.options[this.selectedIndex].value);">';										
									       foreach($gsarr as $gs) {
											   if($gs["id"] == $id) $gssel = " selected";
											   else $gssel = "";
											   echo '<option value="/'.$gs["id"].'"'.$gssel.'>'.$gs["names"].'</option>';
										   }
								        echo '</select>';
								        echo '</div>';
									 }
								}
								?>
								<div class="col-md-12 col-xs-12 padding-bottom-20">								
								<input type="text" class="form-control" name="gid" id="gid" placeholder="請輸入遊戲帳號" required>
								</div>
								
								<!--<div class="col-md-12 col-xs-12 padding-bottom-20">
								<input type="text" class="form-control" name="cid" id="cid" placeholder="請輸入角色名稱" required>
								</div>-->
								
								<div class="col-md-12 col-xs-12 padding-bottom-20">
								<input type="number" class="form-control" name="money" id="money" min="<?=$base_money?>" placeholder="請輸入繳款金額" required>
								</div>
								
								<div class="col-md-12 col-xs-12 padding-bottom-20">
									<div class="col-md-10 col-xs-9 pl-0"><input type="text" class="form-control" name="money2" id="money2" placeholder="比值換算" readonly></div>
									<div class="col-md-2 col-xs-3 pl-0"><button id="read_bi_btn" type="button" class="btn btn-primary btn-md" style="color:#fff !important;">點我換算</button></div>
								</div>
								
								<div class="col-md-12 col-xs-12 padding-bottom-20">
								 <select name="pt" id="pt" class="form-control" required>
							     <?php
							     if($datalist["pay_cp2"] == "gomypay") {
							     // 萬事達 start
							     echo '<option value="30">全家超商代碼繳費(最高繳款金額20000)-請選擇此繳費方式</option>
								 <option value="31">OK超商代碼繳費(最高繳款金額20000)-請選擇此繳費方式</option>
								 <option value="32">萊爾富超商代碼繳費(最高繳款金額限20000)-請選擇此繳費方式</option>
								 <option value="33">7-11超商代碼繳費(最高繳款金額20000)-請選擇此繳費方式</option>';
								 // 萬事達 end
							     } if($datalist["pay_cp2"] == "smilepay") {
							     // 速買配 start
							     echo '<option value="31">7-11 ibon 代碼(最高繳款金額20000)-請選擇此繳費方式</option>';
							     echo '<option value="30">全家 FamiPort 代碼(最高繳款金額20000)-請選擇此繳費方式</option>';
							     //echo '<option value="41">LifeET 代碼(最高繳款金額20000)-請選擇此繳費方式</option>';
							     // 速買配 end
							     } else {
							     echo '<option value="3">超商代碼繳費(最高繳款金額20000)-請選擇此繳費方式</option>';
							     }

								 
							     ?>
                            <!--藍新-->
                            <option value="2">ATM轉帳繳費(最高繳款金額20000)-請選擇此繳費方式</option>
                            <!--<option value="5">線上信用卡繳費(最高繳款金額20000)-請選擇此繳費方式</option>-->
                            <!--<option value="6">網路ATM(僅支援IE瀏覽器)</option>-->
							<?php 
								if($datalist["custom_bank_enable"] == 1) {
									echo '<option value="999">銀行匯款(最高繳款金額20000)-請選擇此繳費方式</option>';
								}
							?>
                            </select>
								</div>
								
								<div class="col-md-12 col-xs-12 padding-bottom-20">
									<div class="col-md-6 col-xs-6 pl-0">
								   <input type="text" class="form-control" name="psn" id="psn" placeholder="驗證碼" autocomplete="off" required>
								  </div>
									<div class="col-md-6 col-xs-6">點圖更換驗證碼　
                                      <a href="#r" onclick="reload_psn($(this))"><img id="index_psn_img" src="psn.php"></a>
								    </div>
								</div>
								
								<div class="col-md-12 col-xs-12 padding-bottom-20">
								<input type="hidden" name="st" value="send">
								<input type="submit" class="btn btn-default" value="確定贊助">
								</div>
								
							</form>
			
								<div class="col-md-12 col-xs-12 pb-20" style="color:white;">所有繳費資料包含IP電磁紀錄皆已留存，如有惡意人士利用此繳費平台進行第三方詐騙，請受害者立即與我們客服聯繫提供資料報警處理，請注意您的贊助皆為個人自願性，繳費後將無法做退費的動作，我們會將該筆費用維持伺服器運行與開發研究，如贊助金流系統故障請聯絡客服人員！</div>
								<p><a href="#" target="_blank">『自動贊助系統』</a></p>
							  <div class="col-md-12 col-xs-12 padding-bottom-20" style="color:white">您的 IP 位置：<?=$user_ip?></div>
							</div>
						</div>

					</div>
				</div>

			</section>
			<!-- /SLIDER -->

			<!-- FOOTER -->
			<footer id="footer">

					<div class="row">
					  <div class="col-md-3"></div>
						<div class="col-md-6 text-center">
							&copy 自動贊助系統
						</div>
						<div class="col-md-3"></div>

					</div>


			</footer>
			<!-- /FOOTER -->

		</div>
		<!-- /wrapper -->


		<!-- SCROLL TO TOP -->
		<a href="#" id="toTop"></a>


		<!-- PRELOADER -->
		<div id="preloader">
			<div class="inner">
				<span class="loader"></span>
			</div>
		</div><!-- /PRELOADER -->


		<!-- JAVASCRIPT FILES -->
		<script type="text/javascript">var plugin_path = 'assets/plugins/';</script>
		<script type="text/javascript" src="/assets/plugins/jquery/jquery-2.2.3.min.js"></script>

		<script type="text/javascript" src="/assets/js/scripts.js"></script>
		<!-- PARTICLE EFFECT -->
		<script type="text/javascript" src="/assets/plugins/canvas.particles.js"></script>
	</body>
</html>

<script type="text/javascript">
$(function() {

 $("#read_bi_btn").on("click", function() {
 var $oi = $("#money2");
 
 if(!$("#money").val()) {
 $oi.val("請先輸入繳款金額。");
 return false;	
 }
 
 if(!$.isNumeric($("#money").val())) {
 $oi.val("繳款金額只能是數字。");
 return false;	 	
 }
 
 if($("#money").val() < <?=$base_money?>) {
 $oi.val("繳款金額必須大於 <?=$base_money?>。");
 return false;	 	
 }
 
$.ajax({
  url: "index.php",
  data: { st: "readbi", v:$("#money").val() },
  dataType: "html"
}).done(function(msg) {
  $oi.val(msg);
});

});

});
function reload_psn($th) {
	var $d = new Date();
	var $img = $th.find("img");	
	$img.attr("src", $img.attr("src")+"?"+$d.getTime());
}
</script>