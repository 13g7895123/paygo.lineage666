<?include("include.php");

if($_REQUEST["st"] == "login") {
	if($_REQUEST["uid"] == "" || $_REQUEST["upd"] == "") alert("請輸入帳號和密碼。", 0);
	$uid = trim($_REQUEST["uid"]);
	$psn = $_REQUEST["psn"];

	if($psn != $_SESSION['excellence_fun_code']) alert("驗證碼錯誤。", 0);
	
	$pdo = openpdo(); 
	//先在 shareuser裡找
    $sq = $pdo->prepare("SELECT * FROM shareuser where uid=? limit 1");
	$sq->execute(array($uid));
	if($sinfo = $sq->fetch()) { //找到了
		//比對pd
		if($sinfo["upd"] != $_REQUEST["upd"]) return alert("帳號或密碼錯誤，請重新輸入。", 0);
		if($sinfo["stats"] < 0) return alert("帳號或密碼錯誤，請重新輸入。", 0);
		$_SESSION["sharean"] = $sinfo["auton"];
		$_SESSION["shareid"] = $sinfo["uid"];
		$_SESSION["sharenames"] = $sinfo["names"];
		$user_IP = get_real_ip();    
		$sqi = array(':lastip' => $user_IP,':lasttime' => date("Y-m-d H:i:s"),':an' => $sinfo["auton"]);
		$squ    = $pdo->prepare("update shareuser set lastip=:lastip, lasttime=:lasttime where auton=:an");    
		$squ->execute($sqi);
		return alert("登入成功。", "share_link.php");
		exit;
	}
    $query    = $pdo->query("SELECT * FROM manager where uid='".$uid."' limit 1");
    $query->execute();
    if(!$datalist = $query->fetch()) {
    	$pdo = null;
    	alert("帳號或密碼錯誤，請重新輸入。", 0);
    }    
    
    if($datalist["upd"] != $_REQUEST["upd"]) alert("帳號或密碼錯誤，請重新輸入。", 0);
    
    $_SESSION["adminan"] = $datalist["auton"];
    $_SESSION["adminid"] = $datalist["uid"];
	  $_SESSION["names"] = $datalist["names"];
	  
	  $user_IP = get_real_ip();
    
	  $input = array(':lastip' => $user_IP,':lasttime' => date("Y-m-d H:i:s"),':an' => $datalist["auton"]);
    $query    = $pdo->prepare("update manager set lastip=:lastip, lasttime=:lasttime where auton=:an");    
    $query->execute($input);
    
    if($_REQUEST["return"]) echo "<meta http-equiv=refresh content=0;url=".$_REQUEST["return"].">";
    else echo "<meta http-equiv=refresh content=0;url=index.php>";
    exit();
}

if($_REQUEST["st"] == "logout") {
	session_destroy();
	echo "<meta http-equiv=refresh content=0;url=login.php>";
  exit();
}
?>
<!doctype html>
<html lang="en-US">
	<head>
		<meta charset="utf-8" />
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		<title>金流串接管理系統</title>
		<meta name="description" content="" />
		<meta name="Author" content="" />

		<!-- mobile settings -->
		<meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, user-scalable=0" />

		<!-- WEB FONTS -->
		<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,700,800&amp;subset=latin,latin-ext,cyrillic,cyrillic-ext" rel="stylesheet" type="text/css" />

		<!-- CORE CSS -->
		<link href="assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		
		<!-- THEME CSS -->
		<link href="assets/css/essentials.css" rel="stylesheet" type="text/css" />
		<link href="assets/css/layout.css" rel="stylesheet" type="text/css" />
		<link href="assets/css/color_scheme/green.css" rel="stylesheet" type="text/css" id="color_scheme" />
		<link rel="icon" sizes="16x16" href="https://i.imgur.com/AAKKGSu.png">

	</head>
	<!--
		.boxed = boxed version
	-->
	<body>


		<div class="padding-15">

			<div class="login-box">
				
				<div class="padding-20 text-center">金流串接管理系統</div>
				<!-- login form -->
				<form action="login.php?st=login" method="post" class="sky-form boxed">
					<header><i class="fa fa-users"></i> 管理員登入</header>


					<fieldset>	
					
						<section>
							<label class="label">帳號</label>
							<label class="input">
								<i class="icon-append fa fa-user"></i>
								<input type="text" name="uid" id="uid" required>
								<span class="tooltip tooltip-top-right">輸入帳號</span>
							</label>
						</section>
						
						<section>
							<label class="label">密碼</label>
							<label class="input">
								<i class="icon-append fa fa-lock"></i>
								<input type="password" name="upd" id="upd" required>
								<b class="tooltip tooltip-top-right">輸入密碼</b>
							</label>							
						</section>

						<section>
							<label class="label">驗證碼</label>
							  <div class="col-md-6 col-xs-6">
							    <a href="#r" onclick="reload_psn($(this))"><img id="index_psn_img" src="../psn.php"></a>								
							  </div>
							  <div class="col-md-6 col-xs-6">
								<input type="text" name="psn" id="psn" class="form-control" required>
							  </div>
								<b class="tooltip tooltip-top-right">輸入驗證碼</b>
							
						</section>

					</fieldset>

					<footer>
						<input type="hidden" name="return" value="<?=$_REQUEST["return"]?>">
						<button type="submit" class="btn btn-primary pull-right">登入</button>
					</footer>
				</form>
				<!-- /login form -->

			</div>

<?down_html()?>
<script type="text/javascript">
$(function() {
	$("#uid").focus();
});
function reload_psn($th) {
	var $d = new Date();
	var $img = $th.find("img");	
	$img.attr("src", $img.attr("src")+"?"+$d.getTime());
}
</script>