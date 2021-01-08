<?php
header("Content-Type: text/html; charset=windows-1250");
use Phppot\DataSource;

require_once 'DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

if (isset($_POST["import"]) || isset($_POST["importxml"])) {
    
	// $importXML == TRUE  -> import from CSV to XML
	// $importXML == FALSE -> import from CSV to DB
	$importXML = isset($_POST["importxml"]) && !isset($_POST["import"]);
    $fileName = $_FILES["file"]["tmp_name"];
    
    if ($_FILES["file"]["size"] > 0) {
        
        $file = fopen($fileName, "r");        
		$longestRow = 10000;		
		$xml = "";
		
		if (!$importXML) {
			// Delete old rows - it's bad idea as we have connected table by id. So use update instead.
			// DELETE old rows
			// maybe it make sense to call UPDATE (row exists in CSV and DB), then CREATE (row exists in CSV and missing DB), then DELETE (row missing in CSV and exists DB)
			//$sqlDelete = "DELETE FROM products";
			//if ($conn->query($sqlDelete) === FALSE) {
			//	$type = "error";
			//	$message = "Problem in query 'DELETE FROM products'";
			//}
		} else{
			$xml = "<root_product>";
		}
		
        while (($column = fgetcsv($file, $longestRow, ";")) !== FALSE) {
            // ---- schema ----
			//CREATE TABLE `products` (
			//  `id` int(11) NOT NULL,
			//  `productId` int(8) NOT NULL,
			//  `name` varchar(100)  NULL,
			//  `secondName` varchar(100)  NULL,
			//  `description` varchar(1000)  NULL,
			//  `picture` varchar(200) NULL,
			//  `available` BOOLEAN, -- = Je skladem
			//  `price` DECIMAL(10,2) NOT NULL, -- just value, currency is kc by default = Nase Cena
			//  `secondPrice` DECIMAL(10,2) NOT NULL, -- just value, currency is kc by default = bezna Cena
			//  `productnumber` varchar(50)  NOT NULL,
			//  `previewPicture` varchar(200) NULL,
			//  `gallery0` varchar(200) NULL, -- it seems maximum 3 pictures are used
			//  `gallery1` varchar(200) NULL,
			//  `gallery2` varchar(200) NULL,
			//  `vat` int(8)  NULL, -- by default 21
			//  `vatlevel` int(8)  NULL, -- by default 1
			//  `amountInStock` int(8)  NULL,
			//  `avaibilityId` int(8)  NULL,
			//  `ean` varchar(50)  NULL,
			//  `unsaleable` BOOLEAN, --  = Tento produkt nezobrazovat v eshopu
			//  `categories` varchar(200)  NULL,
			//  `changedAt` DATETIME  NULL, -- no such field in CSV, only in API response
			//  `dt` DATETIME  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			//);
            $productId = "";
            if (isset($column[0])) {
                $productId = mysqli_real_escape_string($conn, $column[0]);
            }
            $name = "";
            if (isset($column[2])) {
                $name = mysqli_real_escape_string($conn, $column[2]);
            }
			
			if ($productId == "" || $name == "") {
				continue;
			}
			
            $secondName = "";
            if (isset($column[3])) {
                $secondName = mysqli_real_escape_string($conn, $column[3]);
            }
			$description = "";
			if (isset($column[4])) {
                $description = mysqli_real_escape_string($conn, $column[4]);
            }
            $picture = "";
            if (isset($column[7])) {
                $picture = mysqli_real_escape_string($conn, $column[7]);
            }
            $available = "";
            if (isset($column[25])) {
                $available = mysqli_real_escape_string($conn, $column[25]);
            }
			$price = "";
            if (isset($column[6])) {
                $price = mysqli_real_escape_string($conn, $column[6]);
            }
			$secondPrice = "";
            if (isset($column[5])) {
                $secondPrice = mysqli_real_escape_string($conn, $column[5]);
            }
			$productnumber = "";
            if (isset($column[1])) {
                $productnumber = mysqli_real_escape_string($conn, $column[1]);
            }
			$previewPicture = "";
            if (isset($column[8])) {
                $previewPicture = mysqli_real_escape_string($conn, $column[8]);
            }
			$gallery0 = "";
            if (isset($column[16])) {
                $gallery0 = mysqli_real_escape_string($conn, $column[16]);
            }
			$gallery1 = "";
            if (isset($column[17])) {
                $gallery1 = mysqli_real_escape_string($conn, $column[17]);
            }
			$gallery2 = "";
            if (isset($column[18])) {
                $gallery2 = mysqli_real_escape_string($conn, $column[18]);
            }
			$vat = "21"; // by default 21 as vatlevel is 1 by default
			$vatlevel = "1";
            if (isset($column[33])) {
                $vatlevel = mysqli_real_escape_string($conn, $column[33]);
            }
			$amountInStock = "";
            if (isset($column[26])) {
                $amountInStock = mysqli_real_escape_string($conn, $column[26]);
            }
			$avaibilityId = "";
            if (isset($column[27])) {
                $avaibilityId = mysqli_real_escape_string($conn, $column[27]);
            }
			$ean = "";
            if (isset($column[35])) {
                $ean = mysqli_real_escape_string($conn, $column[35]);
            }
			$unsaleable = "";
            if (isset($column[40])) {
                $unsaleable = mysqli_real_escape_string($conn, $column[40]);
            }
			$categories = "";
            if (isset($column[12])) {
                $categories = mysqli_real_escape_string($conn, $column[12]);
            }
            
			if (!$importXML) {
				// check if such productId is new or already exists
				$newProductIdInDB = true;
				$sqlSelect = "SELECT COUNT(id) AS CountId FROM `products` WHERE productId = ".$productId;
				$selected = $db->select($sqlSelect);
				if (! empty($selected)) {
					if ($selected[0]['CountId'] > 0) {
						$newProductIdInDB = false;
					}
				}
				
				if ($newProductIdInDB) {
					//import from CSV to DB
					$sqlInsert = "INSERT into products (productId,name,secondName,description,picture,available,price,secondPrice,productnumber,previewPicture,gallery0,gallery1,gallery2,vat,vatlevel,amountInStock,avaibilityId,ean,unsaleable,categories,changedAt)
						   values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,now())";
					$paramType = "issssiddsssssiiiisis";
					$paramArray = array(
						$productId,
						$name,
						$secondName,
						$description,
						$picture,
						$available,
						$price,
						$secondPrice,
						$productnumber,
						$previewPicture,
						$gallery0,
						$gallery1,
						$gallery2,
						$vat,
						$vatlevel,
						$amountInStock,
						$avaibilityId,
						$ean,
						$unsaleable,
						$categories
					);
					$insertId = $db->insert($sqlInsert, $paramType, $paramArray);
					
					if (! empty($insertId)) {
						$type = "success";
						$message = isset($message) ? $message."<br/>CSV Data Imported into the Database id = $insertId productId = $productId" : "CSV Data Imported into the Database id = $insertId productId = $productId";
					} else {
						$type = "error";
						$message = isset($message) ? $message."<br/>Problem in Importing CSV Data" : "Problem in Importing CSV Data";
					}
				} else {
					$type = "success";
					$message = isset($message) ? $message."<br/>Product (productId = $productId) already exists in Database" : "Product (productId = $productId) already exists in Database";
				}
			} else {
				//import from CSV to XML
				$xml .= '<product productId="'.$productId.'" name="'.$name.'" secondName="'.$secondName.'" available="'.$available.'" price="'.$price.'" productnumber="'.$productnumber.'" amountInStock="'.$amountInStock.'"></product>';
			}
        }
    
		if ($importXML) {
			$xml .= "</root_product>";
			$xml = iconv('WINDOWS-1250', 'UTF-8', $xml);
			$sxe = new SimpleXMLElement($xml);
			$dom = new DOMDocument('1,0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($sxe->asXML());			
			
			$dom->save('OutputXML/products.xml');			
			
			$type = "success";
			$message = "XML was successfully created";
		}
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
    width: 550px;
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
<script type="text/javascript">
$(document).ready(function() {
    $("#frmCSVImport").on("submit", function () {

	    $("#response").attr("class", "");
        $("#response").html("");
        var fileType = ".csv";
        var regex = new RegExp("([a-zA-Z0-9\s_\\.\-:])+(" + fileType + ")$");
        if (!regex.test($("#file").val().toLowerCase())) {
        	    $("#response").addClass("error");
        	    $("#response").addClass("display-block");
            $("#response").html("Invalid File. Upload : <b>" + fileType + "</b> Files.");
            return false;
        }
        return true;
    });
});
</script>
</head>

<body>
    <h2>Import CSV file into Mysql using PHP</h2>

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
                    <label class="col-md-4 control-label">Choose CSV
                        File</label> <input type="file" name="file"
                        id="file" accept=".csv">
                    <button type="submit" id="submit" name="import" class="btn-submit">Import CSV->DB</button>
					<button type="submit" id="submit" name="importxml" class="btn-submit">Import CSV->XML</button>
                    <br />

                </div>

            </form>

        </div>
		
		<?php 
		if (!empty($xml)) { 
			echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>'; }
		?>
		
        <?php
            $sqlSelect = "SELECT id,productId,name,productnumber FROM products LIMIT 20";
            $result = $db->select($sqlSelect);
            if (! empty($result)) {
                ?>
			<span>First 20 rows from products table</span>
            <table id='userTable'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>productId</th>
                    <th>name</th>
                    <th>productnumber</th>

                </tr>
            </thead>
<?php
                
                foreach ($result as $row) {
                    ?>
                    
                <tbody>
                <tr>
                    <td><?php  echo $row['id']; ?></td>
                    <td><?php  echo $row['productId']; ?></td>
                    <td><?php  echo $row['name']; ?></td>
                    <td><?php  echo $row['productnumber']; ?></td>
                </tr>
                    <?php
                }
                ?>
                </tbody>
        </table>
        <?php } ?>
    </div>

</body>

</html>