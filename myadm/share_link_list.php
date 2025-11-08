<?include("include.php");

check_login_share();

top_html();

$uid = _r("uid");
if(empty($uid)) $uid = _s("shareid");
//如果是直播主要檢查是不是他的資料
if(!empty(_s("shareid"))) $uid = _s("shareid");
if(empty($uid)) alert("讀取資料錯誤。");


$serverid = _r("serverid");
if(empty($serverid)) alert("讀取資料錯誤。-serverid");


?>
			<section id="middle">
				<div id="content" class="dashboard padding-20">

					<div id="panel-1" class="panel panel-default">
						<div class="panel-heading">
							<span class="title elipsis">
								<strong><a href="list.php"><?=$uid?> - <?=$serverid?> 分享詳情 - 贊助紀錄</a></strong> <!-- panel title -->								
								<?if($tts) echo "<small>".$tts."</small>"?>
								<?if($_REQUEST["keyword"] != "") echo "<small>搜尋：".$_REQUEST["keyword"]."</small>"?>
							</span>

							<!-- right options -->
							<ul class="options pull-right list-inline">								
								<li><a href="#" class="opt panel_fullscreen hidden-xs" data-toggle="tooltip" title="Fullscreen" data-placement="bottom"><i class="fa fa-expand"></i></a></li>
							</ul>
							<!-- /right options -->

						</div>
						<?
						$d1 = $_REQUEST["d1"];
						$d2 = $_REQUEST["d2"];						
						if($d1 == "") $d1 = date('Y-m-d', strtotime("-7 days"));;
						if($d2 == "") $d2 = date("Y-m-d");
						
						?>
						<!-- panel content -->
						<div class="panel-body">
							<form name="form1" method="get" action="share_link_list.php" class="form-inline nomargin noborder padding-bottom-10">	              	              	              	
						   <div class="form-group">
                <input type="text" name="d1" id="d1" class="form-control datepicker" value="<?=$d1?>" placeholder="開單日期"> 至　<input type="text" name="d2" id="d2" value="<?=$d2?>" class="form-control datepicker" placeholder="開單日期">
               </div>
               <div class="form-group">
               <input type="hidden" name="uid" value="<?=$uid?>">
			   <input type="hidden" name="serverid" value="<?=$serverid?>">
               <input type="submit" class="btn btn-default" value="查詢">
               </div>
						  </form>	
              <form name="form2" method="post" action="share_link_list.php" class="form-inline nomargin noborder padding-bottom-10">	              	              	              	
				<div class="form-group">
                <input type="text" name="keyword" id="keyword" class="form-control" placeholder="遊戲帳號/角色名稱/伺服器名稱/尾綴代號/訂單編號" value="<?=$_REQUEST["keyword"]?>">                
               </div>
               <div class="form-group">
               	<select name="rstat" id="rstat" class="form-control">
               		<option value="">所有狀態</option>
               		<option value="0">等待付款</option>
               		<option value="1">付款完成</option>
               		<option value="2">付款失敗</option>
               	</select>
               </div>
               <div class="form-group">
			   <input type="hidden" name="uid" value="<?=$uid?>">
			   <input type="hidden" name="serverid" value="<?=$serverid?>">
               <input type="submit" class="btn btn-default" value="搜尋">
               </div>
               <div class="form-group">
               
               <a href="javascript:history.go(-1);" class="btn btn-primary"><i class="glyphicon glyphicon-arrow-left"></i> 上一頁</a>
               </div>
						  </form>		                
							<div class="table-responsive">
	              <table class="table table-hover">
						      <thead>
						  	  <tr>						  	  
						  	  <th>伺服器名稱</th>
								<th>金流</th>
						  	  <th>繳費方式</th>
									<th>遊戲帳號</th>
									<th>換算金額</th>
									<th>應繳金額</th>
									<th>手續費</th>
									<th>金流回傳</th>
									<th>開單日期</th>
									<th>付款日期</th>
									<th>目前狀態</th>
                  </tr>
              </thead>
  <tbody>
  <?
  $rstat = $_REQUEST["rstat"];
  $kword = $_REQUEST["keyword"];

  $sql = "shareid='".$uid."' and serverid='".$serverid."'";
  if($kword != "") {
  	$sql .= " and (orderid like '%$kword%' or forname like '%$kword%' or serverid like '%$kword%' or gameid like '%$kword%' or charid like '%$kword%')";
  }
  if($d1 != "" && $d2 != "") {
  	$sql .= " and (times between '$d1 00:00' and '$d2 23:59')";
  }
  if($rstat != "") {
  	$sql .= " and (stats = '$rstat')";
  }
  
    $pdo = openpdo();
 // 運行 SQL    
    $offset = isset($_REQUEST['offset']) ? $_REQUEST['offset']:0;
    $limit_row = 20;
    $query    = $pdo->query("SELECT count(auton) as t FROM servers_log where ".$sql."");
    $numsrow = $query->fetch()["t"];
    
    $pagestr = pages($numsrow, $offset, $limit_row);
    $query = $pdo->query("SELECT * FROM servers_log where ".$sql." order by auton desc limit ".$offset.", ".$limit_row."");
    $query->execute();
    if(!$datalist = $query->fetchAll()) {
    	echo "<tr><td colspan=7>暫無資料</td></tr>";
    } else {
    	foreach ($datalist as $datainfo) {
    		    	
    	echo "<tr>";    	
		echo '<td>'.$datainfo["forname"].'['.$datainfo["serverid"].']</td>';
		echo '<td>'.pay_cp_name($datainfo["pay_cp"]).'</td>';
		echo '<td>'.pay_paytype_name($datainfo["paytype"]).'</td>';
		$gameid = $datainfo["gameid"];
		$gameidlen = strlen($gameid);
		if($gameidlen >= 2) $gameid = substr($gameid, 0, 2);
		else $gameid = substr($gameid, 0, 1);
		$gameid = str_pad($gameid, $gameidlen,"*",STR_PAD_RIGHT);

    	echo "<td>".$gameid."</td>";    	
    	echo "<td>".$datainfo["bmoney"]."</td>";
    	echo "<td>".$datainfo["money"]."</td>";
    	echo "<td>".$datainfo["hmoney"]."</td>";    	
    	echo "<td>".$datainfo["rmoney"]."</td>";
    	echo "<td>".$datainfo["times"]."</td>";
    	if($datainfo["paytimes"] == "0000-00-00 00:00:00") $paytimes = "";
    	else $paytimes = $datainfo["paytimes"];
      echo "<td>".$paytimes."</td>";
	    switch($datainfo["stats"]) {
	    	case 0:
	    	$stats = '<span class="label label-primary">等待付款</span>';
	    	break;

	    	case 1:
	    	$stats = '<span class="label label-success">付款完成</span>';
	    	break;
	    	
	    	case 2:
	    	$stats = '<span class="label label-danger">付款失敗</span>';
	    	break;

	  	default:
	  	$stats = "不明";
	  	break;
	    }
	    
    	echo "<td>".$stats."</td>";    	
    	echo "</tr>";
      }
    }
?>
						  </tbody>
	</table>
</div><?=$pagestr;?>

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

</script>