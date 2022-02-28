<?php
header("Content-Type: text/html; charset=windows-1250");
use Phppot\DataSource;
include "help-functions.php";

require_once 'DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();
set_time_limit(0);

// to filter already updated prices use ip.webShopUpdated date.
// we understand it as "not updated" if date < current date 00:00
//                     "updated"     if date > current date 00:00
function getRowsCountToProcess (object $db, string $date) {
	$sql = "SELECT Count(p.id) as Cnt
    FROM `products` as p 
        join ita_products as ip on ip.product_id = p.id
        join price_evaluation as pe on p.productnumber = pe.productnumber
    where ip.itaInfoStatus_id = 3 and ip.webShopUpdated < '$date 00:00:00';";

	$result = $db->select($sql);
	if (! empty($result)) {
		return $result[0]['Cnt'];
	}	
	return 0;
}

function getRowsCountProcessed (object $db, string $date) {
	$sql = "SELECT Count(p.id) as Cnt
    FROM `products` as p 
        join ita_products as ip on ip.product_id = p.id
        join price_evaluation as pe on p.productnumber = pe.productnumber
    where ip.itaInfoStatus_id = 3 and ip.webShopUpdated > '$date 00:00:00';";

	$result = $db->select($sql);
	if (! empty($result)) {
		return $result[0]['Cnt'];
	}	
	return 0;
}

function setWebShopUpdated (object $db, int $productId) {	
	$sql = "UPDATE `ita_products` SET webShopUpdated = now() WHERE product_id = $productId"; 		
	$res = $db->execute($sql);
}

$todayDate = new DateTime();
$todayDateString = $todayDate->format('Y-m-d');
$sqlSelect = "SELECT p.amountInStock as AmountOld, (ip.availability * 1) AS AmountNew, p.price as PriceOld, ip.webprice as webPriceNew, ip.sellprice as sellPriceNew, p.id as Id, p.productId, p.productnumber 
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
    join price_evaluation as pe on p.productnumber = pe.productnumber
where ip.itaInfoStatus_id = 3 	  
	and ip.webShopUpdated < '$todayDateString 00:00:00'	
	limit 200";

$refreshThisPage = getRowsCountToProcess($db, $todayDateString) > 0;

$result = $db->select($sqlSelect);
if (! empty($result)) {
    $all = createCurlConnection();
    $token = $all[0];
    $jsonResponse = $all[1];
    $apiServer = $all[2];
    $apiKey = $all[3];
    
    //echo "work";
    $i = 1;
    foreach ($result as $row) {
        echo "$i   :   ".$row['productnumber']."   :   ";
        updateSingleProduct($token, $jsonResponse, $apiServer, $apiKey, $row['productId'], $row['AmountNew'], $row['webPriceNew'], $row['sellPriceNew'], $row['productnumber'], false ); /* hidden product*/
        setWebShopUpdated($db, $row['Id']);
        echo "</br>";
        $i++;
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
    <h2>Set prices to EShop page</h2>
    <p>Set prices to eshop using API.</p>
    <div class="outer-scontainer">
        <p><span class="info"><?php echo getRowsCountToProcess($db, $todayDateString)?> </span> rows to process</p>
        <p><span><?php echo getRowsCountProcessed($db, $todayDateString)?> </span> rows processed</p>

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
            }, 5000);	
        } else {
            document.getElementById("refreshPageBox").checked = false;
            console.log("Autorefresh this page is canceled.");
        }
    </script>

    </div>
</body>
</html>