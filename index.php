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

function write_php_ini($array, $file)
{
    $res = array();
    foreach($array as $key => $val)
    {
        if(is_array($val))
        {
            $res[] = "[$key]";
            foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
        }
        else $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
    }
    safefilerewrite($file, implode("\r\n", $res));
}

function safefilerewrite($fileName, $dataToSave)
{    if ($fp = fopen($fileName, 'w'))
    {
        $startTime = microtime(TRUE);
        do
        {            $canWrite = flock($fp, LOCK_EX);
           // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
           if(!$canWrite) usleep(round(rand(0, 100)*1000));
        } while ((!$canWrite)and((microtime(TRUE)-$startTime) < 5));

        //file was locked so now we can store information
        if ($canWrite)
        {            fwrite($fp, $dataToSave);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

}

function updateItaStatus(object $db, int $statusOld, string $date){
    $sql = "update ita_products 
    set itaInfoStatus_id = 0 
    where product_id in (
        SELECT ip.product_id
        FROM ita_products ip
        WHERE ip.itaInfoStatus_id =$statusOld
        and ip.status_id = 3
        and ip.dt > '$date 00:00:00'
    );";

    $res = $db->execute($sql);
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

function getLastUpdatedDate (object $db) {
	$sql = "SELECT max(ip.dt) as LastDate 
    FROM ita_products as ip 
    where ip.itaInfoStatus_id = 3 
        and (ip.webShopUpdated is null or (ip.webShopUpdated < ip.dt)); ";

	$result = $db->select($sql);
	if (! empty($result)) {
		return $result[0]['LastDate'];
	}
	
	return "2022-01-01 00:00:00"; // stub
}

// Init form values
$ini = parse_ini_file('config.ini');
$updatePrices = $ini['ita_update_prices'];
$cookieString = $ini['ita_cookie'];

$openNewTabs = 0;
$lastRunDate = InitLastRunDate($ini);

// ITA -> DB
// main task
if (isset($_POST["updateItaToDb"]) ) {
	$updatePrices = ($_POST["updatePrices"] == "on") ? "true" : "false";
    $cookieString = $_POST["cookieString"];

    // prepare db before run
    updateItaStatus($db, 3, $lastRunDate->format('Y-m-d'));

    $openNewTabs = 1;
} 

// if main task is finished skip refresh
$refreshThisPage = (getRowsCountToProcess($db, 0, $lastRunDate->format('Y-m-d')) > 0) 
                || (getRowsCountToProcess($db, 1, $lastRunDate->format('Y-m-d')) > 0);

// call SetPriceToEshop when all rows are updated
if ($refreshThisPage == false){
    $lastRunDateFromDB =  DateTime::createFromFormat('Y-m-d H:i:s', getLastUpdatedDate($db));
    $today = new DateTime();
    $todayMinus3Min = new DateTime(); 
    $todayMinus3Min->modify('-3 minutes');

    // if the last row update date occured 3 min in the past - write to ini, update eshop 
    if ($lastRunDateFromDB > $todayMinus3Min && $lastRunDateFromDB < $today) {
        // write new last run date to ini file
        $data = array(
            'ita_update_prices' => $updatePrices ? "true" : "false",
            'ita_cookie' => $cookieString,
            'last_run_ita_update' => $today->format('Y-m-d')
        );
        write_php_ini($data, 'config.ini');

        echo "<script type='text/javascript'>window.open('indexSetPrices.php', 'setPrices');</script>";
    }
}

?>
<!DOCTYPE html>
<html>
<head><meta http-equiv="content-type" content="text/html; charset=windows-1250" />
<script src="jquery-3.2.1.min.js"></script>
<link href="css/style.css" rel="stylesheet">
</head>

<body>
    <h2>Main page</h2>
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
					<label for="cookieString">ITA Cookie:</label>
					<textarea id="cookieString" name="cookieString" rows="6" cols="100"><?php echo $cookieString ?></textarea> 
				</div>
				<div class="input-row">
					<label for="updatePrices">Update prices:</label>
					<input type="hidden" name="updatePrices" value="false" />
					<input type="checkbox" id="updatePrices" name="updatePrices" <?php echo $updatePrices == "true" ? "checked = 'checked'" : "" ?>>
				</div>
                <div class="input-row">
                <label for="updatePrices">To skip <b>Autorefresh</b> page unchek it:</label>
                    <input type="checkbox" id="refreshPageBox" name="refreshPageBox" checked = 'checked'>
				</div>
				<div class="input-row">
					<p>Last run date: <?php echo $lastRunDate->format('Y-m-d'); ?></p>
                    <button type="submit" id="submit" name="updateItaToDb" class="btn-submit">Update ITA->DB</button>
                    <br />
                </div>
            </form>
        </div>
		
	<script type="text/javascript">
        if (<?php echo $openNewTabs; ?>) {
            setTimeout(
                function () {
                    var cookie = "<?php echo $cookieString ?>";
                    var update = <?php echo $updatePrices; ?>;
                    var date = "<?php echo $lastRunDate->format('Y-m-d'); ?>";
                    var url = `indexSingleProduct.php?c=${cookie}&u=${update}&d=${date}`;
                    var urlCorrection = `indexCorrection.php?c=${cookie}&u=${update}&d=${date}`;

                    // enable pop-up windows for current site
                    // FireFox: about:preferences#privacy -> add exception
                    window.open(url, 'tab1');
                    window.open(url, 'tab2');
                    window.open(urlCorrection, 'tab3');
                },
                1000);
        }

        // reload this page. check in process rows. open SetPriceToEShop page.
        if (<?php echo $refreshThisPage ? 'true' : 'false' ?> && document.getElementById('refreshPageBox').checked) {
            setTimeout(function(){
                if (document.getElementById('refreshPageBox').checked) {
                    
                    window.location.reload(1);
                } else {
                    console.log("Autorefresh this page is canceled.");
                }
            }, 60000);	
        } else {
            document.getElementById("refreshPageBox").checked = false;
            console.log("Autorefresh this page is canceled.");
        }
    </script>

    </div>
</body>
</html>

