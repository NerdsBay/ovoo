<?php

ini_set('max_execution_time', 300); //300 seconds 

if (isset($_POST)) {
    $host           = $_POST["host"];
    $dbuser         = $_POST["dbuser"];
    $dbpassword     = $_POST["dbpassword"];
    $dbname         = $_POST["dbname"];

    $first_name     = $_POST["first_name"];
    $last_name      = $_POST["last_name"];
    $admin_name     = $first_name.' '.$last_name;
    $email          = $_POST["email"];
    $login_password = $_POST["password"] ? $_POST["password"] : "";

    $purchase_code  = $_POST["purchase_code"];

    //check required fields
    if (!($host && $dbuser && $dbname && $first_name && $last_name && $email && $login_password && $purchase_code)) {
        echo json_encode(array("success" => false, "message" => "Please input all fields."));
        exit();
    }


    //check for valid email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        echo json_encode(array("success" => false, "message" => "Please input a valid email."));
        exit();
    }


    //validate purchase code
    $verification = valid_purchase_code($purchase_code);

    if (!$verification || $verification != "verified") {
        echo json_encode(array("success" => true, "message" => "Please enter a valid purchase code."));
        exit();
    }



    //check for valid database connection
    $mysqli = @new mysqli($host, $dbuser, $dbpassword, $dbname);

    if (mysqli_connect_errno()) {
        echo json_encode(array("success" => false, "message" => $mysqli->connect_error));
        exit();
    }


    //all input seems to be ok. check required fiels
    if (!is_file('database.sql')) {
        echo json_encode(array("success" => false, "message" => "The database.sql file could not found in install folder!"));
        exit();
    }





    /*
     * check the db config file
     * if db already configured, we'll assume that the installation has completed
     */


    $db_file_path = "../application/config/database.php";
    $db_file = file_get_contents($db_file_path);
    $is_installed = strpos($db_file, "enter_hostname");

    if (!$is_installed) {
        echo json_encode(array("success" => false, "message" => "Seems this app is already installed! You can't reinstall it again."));
        exit();
    }


    //start installation

    $sql = file_get_contents("database.sql");


    //set admin information to database
    $sql = str_replace('admin_name', $admin_name, $sql);
    $sql = str_replace('admin_username', $email, $sql);
    $sql = str_replace('admin_email', $email, $sql);
    $sql = str_replace('admin_password', md5($login_password), $sql);
    $sql = str_replace('item_purchase_code', $purchase_code, $sql);

    //create tables in datbase 

    $mysqli->multi_query($sql);
    do {
        
    } while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));


    $mysqli->close();
    // database created
    // set the database config file

    $db_file = str_replace('enter_hostname', $host, $db_file);
    $db_file = str_replace('enter_db_username', $dbuser, $db_file);
    $db_file = str_replace('enter_db_password', $dbpassword, $db_file);
    $db_file = str_replace('enter_database_name', $dbname, $db_file);

    file_put_contents($db_file_path, $db_file);


    // set random enter_encryption_key

    $config_file_path = "../application/config/config.php";
    $encryption_key = substr(md5(rand()), 0, 15);
    $config_file = file_get_contents($config_file_path);
    $config_file = str_replace('enter_encryption_key', $encryption_key, $config_file);

    file_put_contents($config_file_path, $config_file);


    // set the environment = production

    $index_file_path = "../index.php";

    $index_file = file_get_contents($index_file_path);
    $index_file = preg_replace('/pre_installation/', 'production', $index_file, 1); //replace the first occurence of 'pre_installation'

    file_put_contents($index_file_path, $index_file);


    echo json_encode(array("success" => true, "message" => "Installation successfull."));
    exit();
}

function valid_purchase_code($purchase_code =''){
    $purchase_code = urlencode($purchase_code);
    $curl               =   curl_init('http://marketplace.envato.com/api/edge/spagreen/1pctqxxhd7ohojufb2pn9dqs0ip3y5o5/verify-purchase:'.$purchase_code.'.xml');       
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt( $curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
    
    $purchase_data      =   curl_exec($curl);
    curl_close($curl);
    $purchase_data      =   json_decode(json_encode((array) simplexml_load_string($purchase_data)),1);

    if ( isset($purchase_data['verify-purchase']['item-id']) && $purchase_data['verify-purchase']['item-id'] == '20180569')
    {
        return 'unverified';
    }
    else
    {
        return 'verified';
    }
}