<?php
header("Content-Type: text/html; charset=windows-1250");
use Phppot\DataSource;
include "help-functions.php";

require_once 'DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();
set_time_limit(0);

function setWebShopUpdated (object $db, int $productId) {	
	$sql = "UPDATE `ita_products` SET webShopUpdated = now() WHERE product_id = $productId"; 
		
	$res = $db->execute($sql);
}

$sqlSelect = "SELECT p.amountInStock as AmountOld, (ip.availability * 1) AS AmountNew, p.price as PriceOld, ip.webprice as webPriceNew, ip.sellprice as sellPriceNew, p.id as Id, p.productId, p.productnumber 
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
    join price_evaluation as pe on p.productnumber = pe.productnumber
where ip.itaInfoStatus_id = 3 	  
	and ip.webShopUpdated < '2022-02-15 00:00:00'
	
    /*and ip.webprice > 0*/
    /*and (ip.webShopUpdated < p.dt or ip.webShopUpdated is null) */
	/*and pe.category_id = 134*/	
	
	limit 1000
	";
	
if (isset($_POST["db_eshop"])) {


	$result = $db->select($sqlSelect);
    if (! empty($result)) {
		$all = createCurlConnection();
		$token = $all[0];
		$jsonResponse = $all[1];
		$apiServer = $all[2];
		$apiKey = $all[3];
		
		$i = 1;
		foreach ($result as $row) {
			echo "$i   :   ".$row['productnumber']."   :   ";
			updateSingleProduct($token, $jsonResponse, $apiServer, $apiKey, $row['productId'], $row['AmountNew'], $row['webPriceNew'], $row['sellPriceNew'], $row['productnumber'], false ); /* hidden product*/
			setWebShopUpdated($db, $row['Id']);
			echo "</br>";
			$i++;
			//echo $row['productnumber']." ".$row['productId']." ".$row['AmountNew']." ".$row['PriceNew']." </br>"; 
		}
	}	
} else if (isset($_POST["ita_db_xml"])) {
	$sqlSelect = "SELECT (ip.availability * 1) AS amount, ROUND(ip.webprice, 2) as webprice, ROUND(ip.sellprice, 2) as sellprice, p.id, p.productId, p.productnumber, p.name, p.picture, p.description
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
where ip.itaInfoStatus_id = 3 
/* and ip.webprice > 0 */
";
	$result = $db->select($sqlSelect);
    if (! empty($result)) {
		/*
		$xml = '<root_product pictureUrlStarts="https://www.stopkovefrezy.cz/fotky101456/fotos/">';
		*/
		$xml = '<root_product>';
		
		foreach ($result as $row) {
			/*
			$xml .= '<product productnumber="'.$row['productnumber'].'" amount="'.$row['amount'].'" webPrice="'.$row['webprice'].
			'" sellPrice="'.$row['sellprice'].'" pic="'.$row['picture'].'" productId="'.$row['productId'].'">'.
			'<name>'.$row['name'].'</name>'.
			'<description><![CDATA['.$row['description'].']]></description>'.
			'</product>';
			*/
			// amount only
			$xml .= '<product productnumber="'.$row['productnumber'].'" amount="'.$row['amount'].'" >'.
			'</product>';
		}
			
		$xml .= "</root_product>";
		//setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
		$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

		//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
		$sxe = new SimpleXMLElement($xml);
		$dom = new DOMDocument('1,0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($sxe->asXML());			
		
		$dom->save('OutputXML/products.xml');				
	}	
}

	
?>
<!DOCTYPE html>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=windows-1250" />
<script src="jquery-3.2.1.min.js"></script>

<style>
body {
    font-family: Arial;
    width: 750px;
}

.outer-scontainer {
    background: #F0F0F0;
    border: #e0dfdf 1px solid;
    padding: 20px;
    border-radius: 2px;
}

.input-row {
    margin-top: 0px;
    margin-bottom: 20px;
}

.btn-submit {
    background: #333;
    border: #1d1d1d 1px solid;
    color: #f0f0f0;
    font-size: 0.9em;
    width: 100px;
    border-radius: 2px;
    cursor: pointer;
}

.outer-scontainer table {
    border-collapse: collapse;
    width: 100%;
}

.outer-scontainer th {
    border: 1px solid #dddddd;
    padding: 8px;
    text-align: left;
}

.outer-scontainer td {
    border: 1px solid #dddddd;
    padding: 8px;
    text-align: left;
}

#response {
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 2px;
    display: none;
}

.success {
    background: #c7efd9;
    border: #bbe2cd 1px solid;
}

.error {
    background: #fbcfcf;
    border: #f3c6c7 1px solid;
}

div#response.display-block {
    display: block;
}
</style>

</head>

<body>
    <h2>Set price/amount from db to EShop</h2>

    <div id="response"
        class="<?php if(!empty($type)) { echo $type . " display-block"; } ?>">
        <?php if(!empty($message)) { echo $message; } ?>
        </div>
    <div class="outer-scontainer">
        <div class="row">

            <form class="form-horizontal" action="" method="post"
                name="frmCSVImport" id="frmCSVImport"
                enctype="multipart/form-data">
                <div class="input-row">
					<button type="submit" id="submit" name="db_eshop" class="btn-submit">ITA products: DB->EShop</button>
					<button type="submit" id="submit" name="ita_db_xml" class="btn-submit">ITA products: DB->XML</button>
                    <br />

                </div>

            </form>

        </div>
		
        <?php
		/*
            $sqlSelect = "SELECT p.amountInStock as AmountOld, (ip.availability * 1) AS AmountNew, p.price as PriceOld, ip.webprice as PriceNew, p.id, p.productId, p.productnumber 
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
where ip.itaInfoStatus_id = 3 
	and (p.amountInStock = (ip.availability * 1) or ABS(ip.webprice - p.price) < 1)
    and ip.webprice > 0
    and (ip.webShopUpdated < p.dt or ip.webShopUpdated is null)
ORDER BY ip.dt desc";
*/
            $result = $db->select($sqlSelect);
            if (! empty($result)) {
                ?>
			<span>Products to update</span>
			<table id='userTable'>
				<thead>
					<tr>
						<th>Product Number</th>
						<th>AmountOld</th>
						<th>AmountNew</th>
						<th>PriceOld</th>
						<th>webPriceNew</th>
						<th>sellPriceNew</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($result as $row) {?>
					<tr>
						<td><?php  echo $row['productnumber']; ?></td>
						<td><?php  echo $row['AmountOld']; ?></td>
						<td><?php  echo $row['AmountNew']; ?></td>
						<td><?php  echo $row['PriceOld']; ?></td>
						<td><?php  echo $row['webPriceNew']; ?></td>
						<td><?php  echo $row['sellPriceNew']; ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		<?php } else { ?>
			EShop is up to date.
		<?php } ?>
    </div>

</body>

</html>