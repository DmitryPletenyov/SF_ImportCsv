<?php
include "help-functions.php";
//$id = 8888; //431.3 1
//$id = 7912; //1071.63 1

//getProductInfo($id);

updatePropertyMultiple();

$id = 8888;
//getProductInfo($id);

//SELECT p.amountInStock, (ip.availability * 1) AS `a_numb`, p.price, ip.webprice, ip.itaInfoStatus_id, p.id, p.productnumber FROM `products` as p 
//	join ita_products as ip on ip.product_id = p.id
//where  
//	p.amountInStock = (ip.availability * 1)
//	and ABS(ip.webprice - p.price) < 1
	
?>


