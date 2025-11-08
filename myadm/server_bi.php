<?include("include.php");

check_login();

if($_REQUEST["st"] == "svr_st") {
	if($_REQUEST["v"] == "") alert("類型錯誤。", 0);
	if($_REQUEST["ans"] == "") alert("伺服器編號錯誤。", 0);	
	
	  $pdo = openpdo(); 
	  $input = array(':stats' => $_REQUEST["v"]);
    $query    = $pdo->prepare("update servers_bi set stats=:stats where auton in (".$_REQUEST["ans"].")");    
    $query->execute($input);
    
    alert("設定完成。", 0);
    die();
}

if($_REQUEST["st"] == "svr_del") {
	if($_REQUEST["del_alln"] == "") alert("伺服器編號錯誤。", 0);	
	
	  $pdo = openpdo(); 	  
    $query    = $pdo->prepare("delete from servers_bi where auton in (".$_REQUEST["del_alln"].")");    
    $query->execute($input);
    
    alert("刪除完成。", 0);
    die();
}

top_html();

$pdo = openpdo();
$qq    = $pdo->query("SELECT names FROM servers where auton=".$_REQUEST["an"]."");
$names = $qq->fetch()["names"];
?>
			<section id="middle">
				<div id="content" class="dashboard padding-20">

					<div id="panel-1" class="panel panel-default">
						<div class="panel-heading">
							<span class="title elipsis">
								<strong><a href="index.php">伺服器管理</a></strong> <!-- panel title -->								
								<small>幣值管理</small> / <small><?=$names?></small>
							</span>

							<!-- right options -->
							<ul class="options pull-right list-inline">								
								<li><a href="#" class="opt panel_fullscreen hidden-xs" data-toggle="tooltip" title="Fullscreen" data-placement="bottom"><i class="fa fa-expand"></i></a></li>
							</ul>
							<!-- /right options -->

						</div>

						<!-- panel content -->
						<div class="panel-body">

               <div class="form-group">
               <a href="<?=$_SERVER['HTTP_REFERER']?>" class="btn btn-primary"><i class="glyphicon glyphicon-arrow-left"></i> 上一頁</a>
               <a href="server_bi_add.php?an=<?=$_REQUEST["an"]?>" class="btn btn-info"><i class="fa fa-plus"></i> 新增</a>
               <a href="#r" onclick="ch_seln('2')" class="btn btn-danger"><i class="fa fa-remove"></i> 刪除</a>
               <a href="#r" onclick="ch_seln('1')" class="btn btn-success"><i class="glyphicon glyphicon-ok-sign"></i> 開啟</a>	
               <a href="#r" onclick="ch_seln('0')" class="btn btn-warning"><i class="glyphicon glyphicon-minus-sign"></i> 停用</a>	
               </div>
						  			
							<div class="table-responsive">
	              <table class="table table-hover">
						      <thead>
						  	  <tr>
						  	  <th><input type="checkbox" onclick="allseln($(this))"></th>						  	  
									<th>金額範圍</th>
									<th>比值</th>
									<th>使用狀態</th>									
									<th></th>
                  </tr>
              </thead>
  <tbody>
  <? 
    $query = $pdo->query("SELECT * FROM servers_bi where foran=".$_REQUEST["an"]." order by auton desc");
    $query->execute();
    if(!$datalist = $query->fetchAll()) {
    	echo "<tr><td colspan=7>暫無資料</td></tr>";
    } else {
    	foreach ($datalist as $datainfo) {
    		
    		if($datainfo["stats"] == 1) $stats = '<span class="label label-success">開啟</span>';
    		else $stats = '<span class="label label-warning">停用</span>';
    		
    	echo "<tr>";
    	echo '<td><input type="checkbox" name="seln" value="'.$datainfo["auton"].'"></td>';
    	echo "<td>".$datainfo["money1"]." - ".$datainfo["money2"]."</td>";
    	echo "<td>".$datainfo["bi"]."</td>";
    	echo "<td>".$stats."</td>";
    	echo '<td><a href="server_bi_add.php?an='.$datainfo["foran"].'&van='.$datainfo["auton"].'" class="btn btn-default btn-xs">修改</a></td>';
    	echo "</tr>";
      }
    }
?>
						  </tbody>
	</table>
</div>

						</div>
						<!-- /panel content -->


					</div>

				</div>
			</section>
			<!-- /MIDDLE -->

<?down_html()?>
<div id="del_server_modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<!-- Modal Body -->
			<div class="modal-body">
				
				<p>是否確定要將這些幣值刪除？<br><br><b>請注意刪除後將無法回復紀錄。</b></p>
			</div>
      <form method="post" action="server_bi.php" class="nomargin noborder">
			<!-- Modal Footer -->
			<div class="modal-footer">
				<input type="hidden" id="del_alln" name="del_alln" value="">
				<input type="hidden" name="st" value="svr_del">
				<button type="button" class="btn btn-default" data-dismiss="modal">取消刪除</button>
				<button type="submit" class="btn btn-danger">確定刪除</button>
			</div>
		  </form>

		</div>
	</div>
</div>
<script type="text/javascript">
$(function() {

});

function ch_seln($sts) {
	var $alln = [];
	$("input[name='seln']:checked").each(function() {
    $alln.push($.trim($(this).val()));
  });
  
  if(!$alln.length) {
  	alert("請選擇要動作的伺服器。");
  	return true;
  }
  switch($sts) {
  	case "0":
  	case "1":
  	location.href="server_bi.php?st=svr_st&v="+$sts+"&ans="+$alln;
  	break;
  	case "2":
  	$("#del_alln").val($alln);
  	$("#del_server_modal").modal("show");
  	break;
  	default:
  	alert("類型出錯。");
  	break;
  }  
}
function allseln($this) {
	$("input[name='seln']:checkbox").not($this).prop("checked", $this.prop("checked"));
}
</script>