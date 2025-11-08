<?include("include.php");

check_login();

if($_REQUEST['st'] == 'addsave') {
	$an = $_REQUEST["an"];
	$uid = $_REQUEST["uid"];
	$upd = $_REQUEST["upd"];
	$names = $_REQUEST["names"];
	
	if($upd == "") alert("請輸入密碼。", 0);
	if($names == "") alert("請輸入姓名。", 0);
	
	if($an == "") {
	  if($uid == "") alert("請輸入帳號。", 0);
	  $pdo = openpdo(); 

	  $input = array(':uid' => $_REQUEST["uid"]);
      $query = $pdo->prepare("select * from shareuser where uid=:uid");    
	  $query->execute($input);
	  if($query->fetch()) return alert("帳號重復。", 0);
	  

	  $input = array(':uid' => $_REQUEST["uid"],':upd' => $_REQUEST["upd"],':names' => $_REQUEST["names"]);
      $query = $pdo->prepare("INSERT INTO shareuser (names, uid, upd) VALUES(:names,:uid,:upd)");    
	  $query->execute($input);
	  
	  if(!empty($servers = _r("servers"))) {		  
		$sth = $pdo->prepare("delete from shareuser_server where uid=:uid");    
		$sth->execute(array(':uid' => $_REQUEST["uid"]));

		foreach($servers as $ss) {
		  $input2 = array(':uid' => $_REQUEST["uid"],':serverid' => $ss,':times' => date("Y-m-d H:i:s"));
		  $sth = $pdo->prepare("INSERT INTO shareuser_server (uid, serverid, times) VALUES(:uid,:serverid, :times)");    
		  $sth->execute($input2);
		}
	}

      alert("新增完成。", "share_userlist.php");
      die();
		
	} else {
		
	  $pdo = openpdo(); 
	  $input = array(':upd' => $_REQUEST["upd"],':names' => $_REQUEST["names"],':an' => $an);
      $query = $pdo->prepare("update shareuser set upd=:upd, names=:names where auton=:an");    
      $query->execute($input);

	  if(!empty($servers = _r("servers"))) {		  
		  $sth = $pdo->prepare("delete from shareuser_server where uid=:uid");    
		  $sth->execute(array(':uid' => $_REQUEST["uid"]));

		  foreach($servers as $ss) {
			$input2 = array(':uid' => $_REQUEST["uid"],':serverid' => $ss,':times' => date("Y-m-d H:i:s"));
			$sth = $pdo->prepare("INSERT INTO shareuser_server (uid, serverid, times) VALUES(:uid,:serverid, :times)");    
			$sth->execute($input2);
		  }
	  }

      alert("修改完成。", "share_userlist.php");
    die();
	}
	
}


top_html();

$pdo = openpdo(); 

if($_REQUEST["an"] != '') {	  
    $query    = $pdo->query("SELECT * FROM shareuser where auton='".$_REQUEST["an"]."'");
    $query->execute();
    $datalist = $query->fetch();
    $tt = "修改";
	$tt2 = "?st=addsave";
	$readonly = " readonly";
	$readonltt = " 無法修改";

	$servers = [];
	$query2    = $pdo->query("SELECT serverid FROM shareuser_server where uid='".$datalist["uid"]."'");
    $query2->execute();
	if($info2 = $query2->fetchAll()) {
	foreach($info2 as $ii) $servers[] = $ii["serverid"];	
	}
  } else {
    $tt = "新增";
	$tt2 = "?st=addsave";
	$readonly = "";
	$readonltt = "";
	$servers = [];
}
?>
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
								<strong>管理者設定</strong> <!-- panel title -->
                <small class="size-12 weight-300 text-mutted hidden-xs"><?=$tt?>使用者</small>
							</span>

							<!-- right options -->
							<ul class="options pull-right list-inline">								
								<li><a href="#" class="opt panel_fullscreen hidden-xs" data-toggle="tooltip" title="Fullscreen" data-placement="bottom"><i class="fa fa-expand"></i></a></li>
							</ul>
							<!-- /right options -->

						</div>

						<!-- panel content -->
						<div class="panel-body">

							<div class="table">
								<form name="form1" method="post" action="share_useradd.php<?=$tt2?>" onsubmit="return chk_form()">
	<table class="table table-bordered">
						  <tbody>
<tr><td>姓名：<input name="names" id="names" type="text" value="<?=$datalist['names']?>"></td></tr>
<tr><td>帳號：<input name="uid" id="uid" type="text" value="<?=$datalist['uid']?>"<?=$readonly?>><?=$readonltt?></td></tr>
<tr><td>密碼：<input name="upd" id="upd" type="text" value="<?=$datalist['upd']?>"></td></tr>
<tr><td><div class="margin-bottom-20">可分享伺服器：</div>
<div class="margin-bottom-20">
<?php
$query = $pdo->query("SELECT * FROM servers order by gp desc, des desc");
$query->execute();
if($datalist = $query->fetchAll()) {
  $ix = 0;
  foreach($datalist as $info) { 
	  if(in_array($info["id"], $servers)) $cc = " checked";
	  else $cc = "";
	echo '<label class="alert alert-info col-md-3"><input type="checkbox" name="servers[]" value="'.$info["id"].'"'.$cc.'> '.$info["names"].'['.$info["id"].']</label>';		
  }
}
?>
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

<script type="text/javascript">
$(function() {

});
function chk_form() {

if(!$("#names").val()) {
alert("請輸入姓名。");
$("#names").focus();
return false;
}
if(!$("#uid").val()) {
alert("請輸入帳號。");
$("#uid").focus();
return false;
}
if(!$("#upd").val()) {
alert("請輸入密碼。");
$("#upd").focus();
return false;
}

return true;
}
</script>