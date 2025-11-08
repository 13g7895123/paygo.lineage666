<?
include("include.php");
// include('./mockpay.php');

check_login();

if($_REQUEST["st"] == "svr_del") {
	if($_REQUEST["del_server_alln"] == "") alert("編號錯誤。", 0);	
	
	  $pdo = openpdo(); 	  
    $query    = $pdo->prepare("update servers_log set indel=1 where auton in (".$_REQUEST["del_server_alln"].")");    
    $query->execute($input);
    
    alert("刪除完成。", 0);
    die();
}
$isdel = $_REQUEST["isdel"];

if($isdel == "1") $tts = "刪除回收區";
  
top_html();
?>
			<section id="middle">
				<div id="content" class="dashboard padding-20">

					<div id="panel-1" class="panel panel-default">
						<div class="panel-heading">
							<span class="title elipsis">
								<strong><a href="list.php">贊助紀錄</a></strong> <!-- panel title -->								
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
							<form name="form1" method="get" action="list.php" class="form-inline nomargin noborder padding-bottom-10">	              	              	              	
						   <div class="form-group">
                <input type="text" name="d1" id="d1" class="form-control datepicker" value="<?=$d1?>" placeholder="開單日期"> 至　<input type="text" name="d2" id="d2" value="<?=$d2?>" class="form-control datepicker" placeholder="開單日期">
               </div>
               <div class="form-group">
               <input type="hidden" name="isdel" value="<?=$isdel?>">
               <input type="submit" class="btn btn-default" value="查詢">
               </div>
						  </form>	
              <form name="form2" method="post" action="list.php" class="form-inline nomargin noborder padding-bottom-10">	              	              	              	
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
               <input type="hidden" name="isdel" value="<?=$isdel?>">
               <input type="submit" class="btn btn-default" value="搜尋">
               </div>
               <div class="form-group">
               
               <?if($isdel == "1") {?>               
               <a href="list.php" class="btn btn-primary"><i class="glyphicon glyphicon-arrow-left"></i> 上一頁</a>
               <?} else {?>
               <a href="#r" onclick="ch_seln('2')" class="btn btn-danger"><i class="fa fa-remove"></i> 刪除</a>
               <a href="list.php?isdel=1" class="btn btn-info"><i class="glyphicon glyphicon-th-list"></i> 刪除回收區</a>           
               <?}?>
               </div>
						  </form>		
						  			
							<div class="table-responsive">
	              <table class="table table-hover">
						      <thead>
						  	  <tr>
						  	  <th><input type="checkbox" onclick="allseln($(this))"></th>
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
									<th>分享來源</th>	
									<th>目前狀態</th>
                  </tr>
              </thead>
  <tbody>
  <?
  $rstat = $_REQUEST["rstat"];
  $kword = $_REQUEST["keyword"];
  if($isdel == "1") $sql = "indel=1";
  else $sql = "indel=0";
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
    	echo '<td><input type="checkbox" name="seln" value="'.$datainfo["auton"].'"></td>';
		echo '<td>'.$datainfo["forname"].'['.$datainfo["serverid"].']</td>';
		echo '<td>'.pay_cp_name($datainfo["pay_cp"]).'</td>';
		echo '<td>'.pay_paytype_name($datainfo["paytype"]).'</td>';
	
    	echo "<td><a href='list_v.php?an=".$datainfo["auton"]."'>".$datainfo["gameid"]."</a></td>";
    	echo "<td>".$datainfo["bmoney"]."</td>";
    	echo "<td>".$datainfo["money"]."</td>";
    	echo "<td>".$datainfo["hmoney"]."</td>";    	
    	echo "<td>".$datainfo["rmoney"]."</td>";
    	echo "<td>".$datainfo["times"]."</td>";
    	if($datainfo["paytimes"] == "0000-00-00 00:00:00") $paytimes = "";
    	else $paytimes = $datainfo["paytimes"];
      echo "<td>".$paytimes."</td>";
	  	$handPay = '';
	    switch($datainfo["stats"]) {
	    	case 0:
				$stats = '<span class="label label-primary">等待付款</span>';
				$mockPay = '<a href="#" class="btn btn-warning btn-xs mock_pay" data-id="'.$datainfo['auton'].'" data-type="'.$datainfo['paytype'].'">模擬付款</a>';

				// 手動付款
				$handPay = ($datainfo['paytype'] == 999) ? '<a href="#" class="btn btn-info btn-xs hand_pay" data-id="'.$datainfo['auton'].'">手動付款</a>' : '';
				break;

	    	case 1:
				$stats = '<span class="label label-success">付款完成</span>';
				if ($datainfo["RtnMsg"] == "模擬付款成功" || $datainfo["RtnMsg"] == "模擬付款完成") $stats = '<span class="label label-info">模擬付款完成</span>';
				$mockPay = "";
				break;
	    	
	    	case 2:
				$stats = '<span class="label label-danger">付款失敗</span>';
				$mockPay = '<a href="#" class="btn btn-warning btn-xs">模擬付款</a>';
				break;

			case 3:
				if ($datainfo["RtnMsg"] == "模擬付款成功" || $datainfo["RtnMsg"] == "模擬付款完成") $stats = '<span class="label label-info">模擬付款完成</span>';
				$mockPay = "";
				break;
			default:
				$stats = "不明";
				break;
	    }
	    echo "<td>".$datainfo["shareid"]."</td>";
    	echo "<td>".$stats.$mockPay.$handPay."</td>";   
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
<div id="del_server_modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content">
			<!-- Modal Body -->
			<div class="modal-body">
				
				<p>是否確定要將贊助資料刪除？<br><br><b>刪除後可在刪除回收區查看資料。</b></p>
			</div>
      <form method="post" action="list.php" class="nomargin noborder">
			<!-- Modal Footer -->
			<div class="modal-footer">
				<input type="hidden" id="del_server_alln" name="del_server_alln" value="">
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
	$(".hand_pay").click(async function() {
		var $this = $(this);
		var $id = $this.data("id");
		
		// 禁用按鈕避免重複點擊
		$this.prop('disabled', true).text('處理中...');
		
		try {
			console.log('開始手動付款處理，訂單ID:', $id);
			
			// 方式1: 只調用 list.php 的手動付款 API
			let response = await fetch("api/list.php?action=hand_pay", {
				method: "POST",
				headers: {
					"Content-Type": "application/json"
				},
				body: JSON.stringify({ id: $id })
			});

			console.log('API 回應狀態:', response.status);
			
			// 檢查回應是否成功
			if (!response.ok) {
				throw new Error(`HTTP ${response.status}: ${response.statusText}`);
			}

			// 取得回傳內容
			const data = await response.json();
			console.log('API 回應資料:', data);

			// 根據回應狀態顯示訊息
			if (data.status === 'success') {
				alert((data.msg || '手動付款成功'));
				location.reload(); // 重新載入頁面
			} else {
				// 顯示詳細錯誤資訊
				let errorMsg = (data.msg || '手動付款失敗');
				if (data.error_step) {
					errorMsg += '\n錯誤步驟: ' + data.error_step;
				}
				if (data.debug && typeof data.debug === 'object') {
					errorMsg += '\n詳細資訊: ' + JSON.stringify(data.debug, null, 2);
				}
				alert(errorMsg);
			}
		} catch (error) {
			console.error('手動付款錯誤:', error);
			alert("發生錯誤：" + error.message + "\n\n請檢查網路連線或聯繫技術支援");
		} finally {
			// 恢復按鈕狀態
			$this.prop('disabled', false).text('手動付款');
		}
	});

	$(".mock_pay").click(async function() {
		var $this = $(this);
		var $id = $this.data("id");
		var $type = $this.data("type");

		// 禁用按鈕避免重複點擊
		$this.prop('disabled', true).text('處理中...');

		try {
			if($type == 999) {
				console.log('開始模擬付款處理，訂單ID:', $id);
				
				// 只調用 list.php 的模擬付款 API
				let response = await fetch("api/list.php?action=hand_pay", {
					method: "POST",
					headers: {
						"Content-Type": "application/json"
					},
					body: JSON.stringify({ 
						id: $id,
						is_mock: 1
					})
				});

				console.log('模擬付款 API 回應狀態:', response.status);
				
				// 檢查回應是否成功
				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				// 取得回傳內容
				const data = await response.json();
				console.log('模擬付款 API 回應資料:', data);

				// 根據回應狀態顯示訊息
				if (data.status === 'success') {
					alert((data.msg || '模擬付款成功'));
					location.reload(); // 重新載入頁面
				} else {
					// 顯示詳細錯誤資訊
					let errorMsg = (data.msg || '模擬付款失敗');
					if (data.error_step) {
						errorMsg += '\n錯誤步驟: ' + data.error_step;
					}
					if (data.debug && typeof data.debug === 'object') {
						errorMsg += '\n詳細資訊: ' + JSON.stringify(data.debug, null, 2);
					}
					alert(errorMsg);
				}
			} else {
			// 其他類型的模擬付款，使用新的 API
			console.log('開始 API 模擬付款，訂單ID:', $id, '類型:', $type);
			
			try {
				// 調用新的 mockpay API
				let response = await fetch("api/mockpay.php", {
					method: "POST",
					headers: {
						"Content-Type": "application/json"
					},
					body: JSON.stringify({ 
						an: $id,
						type: $type
					})
				});

				console.log('MockPay API 回應狀態:', response.status);
				
				// 檢查回應是否成功
				if (!response.ok) {
					throw new Error(`HTTP ${response.status}: ${response.statusText}`);
				}

				// 取得回傳內容
				const data = await response.json();
				console.log('MockPay API 回應資料:', data);

				// 根據回應狀態顯示訊息
				if (data.status === 'success') {
					let successMsg = data.msg || '模擬付款成功';
					if (data.data) {
						successMsg += '\n訂單編號: ' + (data.data.orderid || '');
						successMsg += '\n金額: ' + (data.data.amount || '');
						successMsg += '\n金流: ' + (data.data.pay_cp || '');
					}
					alert(successMsg);
					location.reload(); // 重新載入頁面
				} else {
					// 顯示詳細錯誤資訊
					let errorMsg = data.msg || '模擬付款失敗';
					if (data.error_step) {
						errorMsg += '\n錯誤步驟: ' + data.error_step;
					}
					if (data.debug && typeof data.debug === 'object') {
						errorMsg += '\n詳細資訊: ' + JSON.stringify(data.debug, null, 2);
					}
					alert(errorMsg);
				}
								
			} catch (error) {
				console.error('MockPay API 錯誤:', error);
				alert('模擬付款發生錯誤：' + error.message + '\n\n請檢查網路連線或聯繫技術支援');
			}
		}
		} catch (error) {
			console.error('模擬付款錯誤:', error);
			alert("發生錯誤：" + error.message + "\n\n請檢查網路連線或聯繫技術支援");
		} finally {
			// 恢復按鈕狀態
			$this.prop('disabled', false).text('模擬付款');
		}
	});
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
  	case "2":
  	$("#del_server_alln").val($alln);
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