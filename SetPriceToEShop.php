<?php
header("Content-Type: text/html; charset=windows-1250");
use Phppot\DataSource;
include "help-functions.php";

require_once 'DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

function setWebShopUpdated (object $db, int $productId) {	
	$sql = "UPDATE `ita_products` SET webShopUpdated = now() WHERE product_id = $productId"; 
		
	$res = $db->execute($sql);
}

if (isset($_POST["db_eshop"])) {
	$sqlSelect = "SELECT p.amountInStock as AmountOld, (ip.availability * 1) AS AmountNew, p.price as PriceOld, ip.webprice as PriceNew, p.id, p.productId, p.productnumber 
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
where ip.itaInfoStatus_id = 3 
	and (p.amountInStock = (ip.availability * 1) or ABS(ip.webprice - p.price) < 1)
    and ip.webprice > 0
    and (ip.webShopUpdated < p.dt or ip.webShopUpdated is null) 
	LIMIT 2";
	$result = $db->select($sqlSelect);
    if (! empty($result)) {
		$all = createCurlConnection();
		$token = $all[0];
		$jsonResponse = $all[1];
		$apiServer = $all[2];
		$apiKey = $all[3];
		$curl = curl_init();
		
		foreach ($result as $row) {
			//echo $row['productnumber']." ".$row['productId']." ".$row['AmountNew']." ".$row['PriceNew']." </br>"; 
			
			$productId = $row['productId'];
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://$apiServer/product/$productId",
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_HEADER => FALSE,
				CURLOPT_CUSTOMREQUEST => "PUT",
				CURLOPT_POSTFIELDS => "{\"amountInStock\": ".$row['AmountNew'].", \"price\": ".$row['PriceNew']."}",
				CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer $jsonResponse->token",
					"X-Wa-api-token: $apiKey"
				),				
			));
			
			$response = curl_exec($curl);
			$err = curl_error($curl);
			
			$dump = json_decode($response);
			//var_dump($dump);
			if ($dump->message == "Product was updated") {
				setWebShopUpdated($db, $row['id']); // use our db id !
				echo $row['productnumber']." ok</br>";
			} else {
				echo "Error: ".$row['productnumber']." ($productId) was not updated";
			}
		}
		
		curl_close($curl);
	}	
} else if (isset($_POST["ita_db_xml"])) {
	$sqlSelect = "SELECT (ip.availability * 1) AS amount, ROUND(ip.webprice, 2) as price, p.id, p.productId, p.productnumber
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
where ip.itaInfoStatus_id = 3 and ip.webprice > 0";
	$result = $db->select($sqlSelect);
    if (! empty($result)) {
		$xml = "<root_product>";
		
		foreach ($result as $row) {
			$xml .= '<product productnumber="'.$row['productnumber'].'" amountInStock="'.$row['amount'].'" price="'.$row['price'].'"></product>';
		}
			
		$xml .= "</root_product>";
		$xml = iconv('WINDOWS-1250', 'UTF-8', $xml);
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
            $sqlSelect = "SELECT p.amountInStock as AmountOld, (ip.availability * 1) AS AmountNew, p.price as PriceOld, ip.webprice as PriceNew, p.id, p.productId, p.productnumber 
FROM `products` as p 
	join ita_products as ip on ip.product_id = p.id
where ip.itaInfoStatus_id = 3 
	and (p.amountInStock = (ip.availability * 1) or ABS(ip.webprice - p.price) < 1)
    and ip.webprice > 0
    and (ip.webShopUpdated < p.dt or ip.webShopUpdated is null)";
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
						<th>PriceNew</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($result as $row) {?>
					<tr>
						<td><?php  echo $row['productnumber']; ?></td>
						<td><?php  echo $row['AmountOld']; ?></td>
						<td><?php  echo $row['AmountNew']; ?></td>
						<td><?php  echo $row['PriceOld']; ?></td>
						<td><?php  echo $row['PriceNew']; ?></td>
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