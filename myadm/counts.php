<?include("include.php");

check_login();

top_html();
?>
			<section id="middle">
				<div id="content" class="dashboard padding-20">

					<div id="panel-1" class="panel panel-default">
						<div class="panel-heading">
							<span class="title elipsis">
								<strong><a href="list.php">贊助統計</a></strong> <!-- panel title -->								
								<?if($tts) echo "<small>".$tts."</small>"?>
								<?if($_REQUEST["keyword"] != "") echo "<small>搜尋：".$_REQUEST["keyword"]."</small>"?>
							</span>

							<!-- right options -->
							<ul class="options pull-right list-inline">								
								<li><a href="#" class="opt panel_fullscreen hidden-xs" data-toggle="tooltip" title="Fullscreen" data-placement="bottom"><i class="fa fa-expand"></i></a></li>
							</ul>
							<!-- /right options -->

						</div>
						<?php
 
  if(isset($_REQUEST["serv"]) && !empty($serv = $_REQUEST["serv"])) {
	$ssql = " and serverid='".$serv."'";
  }

						$d1 = $_REQUEST["d1"];
						$d2 = $_REQUEST["d2"];						
						if($d1 == "") $d1 = date('Y-m-d', strtotime("-7 days"));
						if($d2 == "") $d2 = date("Y-m-d");
						$diff = (strtotime($d2) - strtotime($d1)) / (60 * 60 * 24);
						if($diff < 0) alert("時間設定錯誤。");
						if($diff > 62) alert("只能搜尋 60 天內的紀錄。");						
						?>
						<!-- panel content -->
						<div class="panel-body">
							<form name="form1" method="get" action="counts.php" class="form-inline nomargin noborder padding-bottom-10">	              	              	              	
						   <div class="form-group">
                <input type="text" name="d1" id="d1" class="form-control datepicker" value="<?=$d1?>" placeholder="開單日期"> 至　<input type="text" name="d2" id="d2" value="<?=$d2?>" class="form-control datepicker" placeholder="開單日期">
               </div>
               <div class="form-group">
               <select name="serv" id="serv" class="form-control">
               		<option value="">所有伺服器</option>
					   <?php
					   $pdo = openpdo();
					   $servlist = $pdo->query("SELECT * FROM servers order by gp desc, des desc");					   
					   $servlistq = $servlist->fetchAll();					   
					   if($servlistq) {						   
						   foreach($servlistq as $ser) {
							   if($serv == $ser["id"]) echo '<option value="'.$ser["id"].'" selected>'.$ser["names"].'['.$ser["id"].']</option>';
							   else echo '<option value="'.$ser["id"].'">'.$ser["names"].'['.$ser["id"].']</option>';
						   }						
					   }
					   ?>
               	</select>
               <input type="submit" class="btn btn-default" value="查詢">
               </div>
						  </form>	            
<p></p>
<div id="flot-sin" class="flot-chart"><!-- FLOT CONTAINER --></div>
<p></p>
<div class="table-responsive">
	<table class="table table-hover">
	  <thead>
	    <tr>
		  <?php
		  echo '<th>'.date("Y").' 年</th>';
		  echo '<th>一月</th>';
		  echo '<th>二月</th>';
		  echo '<th>三月</th>';
		  echo '<th>四月</th>';
		  echo '<th>五月</th>';
		  echo '<th>六月</th>';
		  echo '<th>七月</th>';
		  echo '<th>八月</th>';
		  echo '<th>九月</th>';
		  echo '<th>十月</th>';
		  echo '<th>十一月</th>';
		  echo '<th>十二月</th>';
		  ?>
        </tr>
      </thead>
      <tbody>
  <?
  

  echo '<td>收入</td>';

  $year = date("Y");
  for ($i=1; $i <= 12 ; $i++) { 
	echo '<td>';	
	$qq = $pdo->query("SELECT sum(rmoney) as t FROM servers_log where stats=1 and rmoney > 0 and indel=0 and YEAR(times) = ".$year." and MONTH(times) = ".$i."".$ssql."");
	$q = $qq->fetch();
	if($q) ${"r_".$i} = $q["t"];
	
	if(${"r_".$i} === 0 || empty(${"r_".$i})) ${"r_".$i} = 0;
	echo ${"r_".$i};
	echo '</td>';	
  }
  unset($qq);
  unset($q);
  $year = date("Y", strtotime("-1 year"));
  for ($i=1; $i <= 12 ; $i++) { 
	$qq = $pdo->query("SELECT sum(rmoney) as t FROM servers_log where stats=1 and indel=0 and rmoney > 0 and YEAR(times) = ".$year." and MONTH(times) = ".$i."".$ssql."");
	$q = $qq->fetch();
	if($q) ${"r2_".$i} = $q["t"];
	
	if(${"r2_".$i} === 0 || empty(${"r2_".$i})) ${"r2_".$i} = 0;	
  }
  unset($qq);
  unset($q);
?>
       </tbody>
	</table>	
</div>
<hr>
<div class="table-responsive">
	<table class="table table-hover">
	  <tbody>
	  <?php
  if($d1 != "" && $d2 != "") {
	$sql .= " and (times between '$d1 00:00' and '$d2 23:59')".$ssql;
}
	  $chkm = [];
	  $allchkrmoney = 0;	  
	  $qq = $pdo->query("SELECT DATE(times) as date, SUM(rmoney) as r FROM servers_log where stats=1 and indel=0 and (times between '$d1 00:00' and '$d2 23:59')".$sql." GROUP BY DATE(times)");
	  if($ql = $qq->fetchAll()) {
		foreach($ql as $q) {
		  $chkm[date("Ymd", strtotime($q["date"]))] = $q["r"];
		}
	  }

	  $diff++;
	  for ($i=0; $i < $diff; $i++) { 
		echo '<tr>';
		$showdate = date('Y-m-d', strtotime($d2 .' - '.$i.' days'));
		$chkdate = date('Ymd', strtotime($d2 .' - '.$i.' days'));
		echo '<td width=160>'.$showdate.'</td>';
		$chkrmoney = 0;
		if(isset($chkm[$chkdate])) {
			$chkrmoney = $chkm[$chkdate];						
		}
		echo '<td>'.$chkrmoney.'</td>';		
		echo '</tr>';
		$allchkrmoney = (int)$chkrmoney+$allchkrmoney;
	  }
	  echo '<tr><td>'.$diff.' 天 合計</td><td>'.$allchkrmoney.'</td></tr>';
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

<script type="text/javascript">
		var $color_border_color = "#eaeaea";		/* light gray 	*/
			$color_grid_color 	= "#dddddd"			/* silver	 	*/
			$color_main 		= "#E24913";		/* red       	*/
			$color_second 		= "#6595b4";		/* blue      	*/
			$color_third 		= "#FF9F01";		/* orange   	*/
			$color_fourth 		= "#7e9d3a";		/* green     	*/
			$color_fifth 		= "#BD362F";		/* dark red  	*/
			$color_mono 		= "#000000";		/* black 	 	*/
			
	  var months = ["一月", "二月", "三月", "四月", "五月", "六月", "七月", "八月", "九月", "十月", "十一月", "十二月"];

		var d = [
		<?php		
		$r1show = [];
		for ($i=1; $i <= 12 ; $i++) { 			
			$r1show[] .= '['.$i.', '.${"r_".$i}.']';
		}		
		if(sizeof($r1show)) echo implode(",", $r1show);
		
		?>
		];
		var d2 = [
		<?php
		$r2show = [];
		for ($i=1; $i <= 12 ; $i++) { 
			$r2show[] .= '['.$i.', '.${"r2_".$i}.']';
		}
		if(sizeof($r2show)) echo implode(",", $r2show);
		?>
		];		
var dataSet = [
	{ label: "今年收入", data: d, color: "#FF55A8" },
	{ label: "去年收入", data: d2, color: "#999999" }
];

			loadScript(plugin_path + "chart.flot/jquery.flot.min.js", function(){
				loadScript(plugin_path + "chart.flot/jquery.flot.resize.min.js", function(){
					loadScript(plugin_path + "chart.flot/jquery.flot.time.min.js", function(){
						loadScript(plugin_path + "chart.flot/jquery.flot.fillbetween.min.js", function(){
							loadScript(plugin_path + "chart.flot/jquery.flot.orderBars.min.js", function(){
								loadScript(plugin_path + "chart.flot/jquery.flot.pie.min.js", function(){
									loadScript(plugin_path + "chart.flot/jquery.flot.tooltip.min.js", function(){

		if (jQuery("#flot-sin").length > 0) {
			var plot = jQuery.plot(jQuery("#flot-sin"), dataSet, {
				series : {
					lines : {
						show : true
					},
					points : {
						show : true
					}
				},
				grid: {
        hoverable: true,
        clickable: false,
        borderWidth: 1,
        borderColor: "#633200",
        backgroundColor: { colors: ["#ffffff", "#EDF5FF"] }
      },
				tooltip : true,
				tooltipOpts : {
					content : "(%s) %x 月<br/><strong>%y</strong>",
					defaultTheme : false
				},
				colors : [$color_second, $color_fourth],
				yaxes: {
      	axisLabelPadding: 3,
      	tickFormatter: function (v, axis) {
          return $.formatNumber(v, { format: "#,###", locale: "nt" });
        }
      },
				xaxis: {
				  ticks: [
                    [1, "一月"], [2, "二月"], [3, "三月"], [4, "四月"], [5, "五月"], [6, "六月"],
                    [7, "七月"], [8, "八月"], [9, "九月"], [10, "十月"], [11, "十一月"], [12, "十二月"]
                 ]
			}
			});
	
		}
									});
								});
							});
						});
					});
				});	
		  });
</script>