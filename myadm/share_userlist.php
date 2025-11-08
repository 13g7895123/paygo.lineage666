<?include("include.php");

check_login();

if($_REQUEST["st"] == "open") {
	
	$pdo = openpdo();
    $query    = $pdo->prepare("update shareuser set stats = 0 where auton=:an");    
    $query->execute(array(':an' => $_REQUEST["an"]));
    alert("開啟成功。");
}
if($_REQUEST["st"] == "close") {
	
	$pdo = openpdo();
    $query    = $pdo->prepare("update shareuser set stats = -1 where auton=:an");    
    $query->execute(array(':an' => $_REQUEST["an"]));
    win_alert("關閉成功。");
}

top_html();
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
								<strong>直播主管理</strong> <!-- panel title -->								
							</span>

							<!-- right options -->
							<ul class="options pull-right list-inline">								
								<li><a href="#" class="opt panel_fullscreen hidden-xs" data-toggle="tooltip" title="Fullscreen" data-placement="bottom"><i class="fa fa-expand"></i></a></li>
							</ul>
							<!-- /right options -->

						</div>

						<!-- panel content -->
						<div class="panel-body">
							<div class="panel-btn"><a href="share_useradd.php" class="btn btn-info btn-sm">新增直播主</a></div>
							<div class="table-responsive">
	<table class="table table-bordered">
						  <thead>
  <tr>
    <th>姓名</th>    
    <th>帳號</th>
	<th>可分享</th>	
	<th>贊助成功</th>	
    <th>最後登入</th>
    <th>建立時間</th>
    <th>　</th>
  </tr>
  <?
    $pdo = openpdo();
 // 運行 SQL    
    $offset = isset($_REQUEST['offset']) ? $_REQUEST['offset']:0;
    $limit_row = 20;
    $query    = $pdo->query("SELECT count(auton) as t FROM shareuser");
    $numsrow = $query->fetch()["t"];
    
    $pagestr = pages($numsrow, $offset, $limit_row);     
    $query    = $pdo->query("SELECT *, (select count(auton) from shareuser_server where a.uid = uid) as total, (select sum(rmoney) from servers_log where a.uid = shareid and stats=1) as sponsor FROM shareuser as a order by auton desc limit ".$offset.", ".$limit_row."");
    $query->execute();
    $datalist = $query->fetchAll();    
    
    //第一次輸出
    foreach ($datalist as $datainfo)
    {
    ?>
  <tr> 
  <td align="center"><?=$datainfo['names']?></td>
  <td align="center"><?=$datainfo['uid']?></td>
  <td align="center"><?=$datainfo['total']?></td>
  <td align="center"><?=$datainfo['sponsor'] ? $datainfo['sponsor']:0?></td>
	<td align="center"><?=$datainfo['lasttime']?>(<?=$datainfo['lastip']?>)</td>
	<td align="center"><?=$datainfo['times']?></td>
    <td align="left">
	<?
	if($datainfo['total'] > 0) {
		echo '<a class="btn btn-default btn-xs" href="share_link.php?uid='.$datainfo['uid'].'"><i class="fa fa-link white"></i> 分享連結</a>';
	}
	?>
    	<a class="btn btn-default btn-xs" href="share_useradd.php?an=<?=$datainfo['auton']?>"><i class="fa fa-edit white"></i> 修改</a>    	
	<?
	if($datainfo["stats"] == -1) echo '<a class="btn btn-success btn-xs" href="share_userlist.php?st=open&an='.$datainfo['auton'].'"><i class="fa fa-check white"></i> 開啟</a>';
	else echo '<a class="btn btn-danger btn-xs" href="#del" onclick="Mars_popup2(\'share_userlist.php?st=close&an='.$datainfo['auton'].'\', \'udel\', \'width=350,height=250\')"><i class="fa fa-times white"></i> 關閉</a>';
	?>
   </td>
  </tr>
    <?
    }
  ?>
    </tbody>
	</table>
</div>
<?print $pagestr;?>

						</div>
						<!-- /panel content -->


					</div>

				</div>
			</section>
			<!-- /MIDDLE -->

<?down_html()?>