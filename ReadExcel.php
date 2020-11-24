<?php
header("Content-Type: text/html; charset=windows-1250");
use Phppot\DataSource;

require_once 'DataSource.php';
$db = new DataSource();
$conn = $db->getConnection();

if (isset($_POST["import"]) || isset($_POST["importxml"])) {
    
	$fileName = $_FILES["file"]["tmp_name"];
	
    if ($_FILES["file"]["size"] > 0) {
        
        $file = fopen($fileName, "r");        
		$longestRow = 10000;		
		$xml = "";
		
		

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
    $("#frmExcelImport").on("submit", function () {

	    $("#response").attr("class", "");
        $("#response").html("");
        var fileType = ".xlsx";
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
    <h2>Read Excel file. Than call API. </h2>

    <div id="response"
        class="<?php if(!empty($type)) { echo $type . " display-block"; } ?>">
        <?php if(!empty($message)) { echo $message; } ?>
        </div>
    <div class="outer-scontainer">
        <div class="row">

            <form class="form-horizontal" action="" method="post"
                name="frmExcelImport" id="frmExcelImport"
                enctype="multipart/form-data">
                <div class="input-row">
                    <label class="col-md-4 control-label">Choose .xlsx
                        File</label> <input type="file" name="file"
                        id="file" accept=".xlsx">
                    <button type="submit" id="submit" name="import" class="btn-submit">Read .xlsx file</button>
                    <br />

                </div>

            </form>

        </div>
		
		<?php 
		if (!empty($xml)) { 
			echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>'; }
		?>
		
		
        <?php
		
		echo 'DONE';
		/*
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
        <?  php } */ ?>

    </div>

</body>

</html>