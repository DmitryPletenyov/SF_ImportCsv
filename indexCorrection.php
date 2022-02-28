<?php
header("Content-Type: text/html; charset=windows-1250");
use Phppot\DataSource;

require_once 'DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

function InitLastRunDate(array $ini) {
	if (array_key_exists('last_run_ita_update', $ini)) {
		return DateTime::createFromFormat('Y-m-d',  $ini['last_run_ita_update']);
	}

	//if no date in config - let it be 3 days before today (return Fri if today is Mon)
	$today = new DateTime();
	return $today->sub(new DateInterval('P3D'));
} 

function getRowsCountToProcess (object $db, int $status, string $date) {
	$sql = "SELECT Count(ip.product_id) AS Cnt
	FROM ita_products ip    
    WHERE ip.itaInfoStatus_id = $status
    and ip.status_id = 3
    and ip.dt > '$date 00:00:00';";

	$result = $db->select($sql);
	if (! empty($result)) {
		return $result[0]['Cnt'];
	}
	
	return 0;
}

function updateForCorrection(object $dbb, string $date){
    $sql = "update ita_products 
    set itaInfoStatus_id = 0 
    where product_id in (  
        SELECT product_id
        FROM ita_products ip
        
        WHERE ip.itaInfoStatus_id =1
        and ip.status_id = 3
        and ip.dt > '$date 00:00:00'
        and TIMESTAMPDIFF(MINUTE, dt, NOW()) > 3
    );";

    $resCorrection = $dbb->execute($sql);
}

$cookie = '';
$updatePrices = true;
$today = new DateTime();
$lastRunDate = $today->sub(new DateInterval('P3D'));
$refreshThisPage = 1;

$db = new DataSource();
$conn = $db->getConnection();
set_time_limit(0);

if (isset($_GET['c'])) {
    $cookie = $_GET['c'];
} else {    echo "No cookies in query string<br>";}

if (isset($_GET['u'])) {
    if ($_GET['u'] == "false") {$updatePrices = false;}
} else {    echo "No updatePrices in query string<br>";}

if (isset($_GET['d'])) {
    $lastRunDate = DateTime::createFromFormat('Y-m-d', $_GET['d']);
	//echo $lastRunDate->format('Y-m-d');
} else {    echo "No Last run date in query string<br>";}

// 2 task: if there is any permanent in process row - set status to 0 and process them
$toProcess = getRowsCountToProcess($db, 0, $lastRunDate->format('Y-m-d'));
$inProcess = getRowsCountToProcess($db, 1, $lastRunDate->format('Y-m-d'));

$tryCorrection = $inProcess > 0;
if ($tryCorrection)
{
    echo "Try Correction </br>";
    updateForCorrection($db, $lastRunDate->format('Y-m-d'));
}

?>
<!DOCTYPE html>
<html>
<head><meta http-equiv="content-type" content="text/html; charset=windows-1250" />
<script src="jquery-3.2.1.min.js"></script>
<link href="css/style.css" rel="stylesheet">
</head>

<body>
    <h2>Correction page</h2>
    <p>Change status for permanent in-process rows to status ready to update. So working tab will process it. 
        Permanent in-process is row in status in process for the last 3 min. </p>
    <div class="outer-scontainer">
    <p><span class="info"><?php echo getRowsCountToProcess($db, 0, $lastRunDate->format('Y-m-d'))?> </span> rows to update</p>
        <p><span><?php echo getRowsCountToProcess($db, 3, $lastRunDate->format('Y-m-d'))?> </span> rows updated</p>
        <p><span class="error"><?php echo getRowsCountToProcess($db, 2, $lastRunDate->format('Y-m-d'))?> </span> rows finished with error</p>
        <p><span class="warning"><?php echo getRowsCountToProcess($db, 1, $lastRunDate->format('Y-m-d'))?> </span> rows in progress</p>

        <div class="row">
            <form class="form-horizontal" action="" method="post"
                name="frmMain" id="frmMain"
                enctype="multipart/form-data">

                <div class="input-row">
                <label for="updatePrices">To skip <b>Autorefresh</b> page unchek it:</label>
                    <input type="checkbox" id="refreshPageBox" name="refreshPageBox" checked = 'checked'>
				</div>

            </form>
        </div>
		
	<script type="text/javascript">
        // reload this page.
        if (<?php echo $refreshThisPage ? 'true' : 'false' ?> && document.getElementById('refreshPageBox').checked) {
            setTimeout(function(){
                if (document.getElementById('refreshPageBox').checked) {
                    
                    window.location.reload(1);
                } else {
                    console.log("Autorefresh this page is canceled.");
                }
            }, 120000);	
        } else {
            document.getElementById("refreshPageBox").checked = false;
            console.log("Autorefresh this page is canceled.");
        }
    </script>

    </div>
</body>
</html>
