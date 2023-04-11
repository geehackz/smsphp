<?php

require 'include/CountryCodeConverter.php';
require 'include/global.php';
use writecrow\CountryCodeConverter\CountryCodeConverter;

if(!isset($_SESSION['username']) && !isset($_SESSION['user_id'])){
    header("Location: login");
    exit;
}
  
if(!checksession()){
    header("Location: login");
    exit;
}

$user_id = sanitise($_SESSION['user_id']);

$totalnumbers = mysqli_query($con, "SELECT * FROM numbers WHERE `user_id` = '$user_id'") or die(mysqli_error($con));
$totalnumbers = mysqli_num_rows($totalnumbers);
$totalorders = mysqli_query($con, "SELECT * FROM orders WHERE `user_id` = '$user_id'") or die(mysqli_error($con));
$totalorders = mysqli_num_rows($totalorders);
$balance = get_balance($user_id);

$api_key = api();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sms-activation-service.com/stubs/handler_api?api_key='.$api_key.'&action=getCountryAndOperators&lang=en');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$headers = array();
$headers[] = 'Accept: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);
$countries = json_decode($result, true);
$country = $countries[0]['name'];
// get total countries
$totalcountries = count($countries);

if(isset($_GET['country']) && isset($_GET['operator']) && isset($_GET['service']) && isset($_GET['countryID'])){

$country = $_GET['country']; // Country name
$operator = $_GET['operator']; // Operator name
$service = $_GET['service']; // Service name
$countryID = $_GET['countryID']; // Country ID

// get the price based off 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sms-activation-service.com/stubs/handler_api?api_key='.$api_key.'&action=getServicesAndCost&country='.$country.'&operator='.$operator.'&lang=en');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$headers = array();
$headers[] = 'Accept: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);
$services = json_decode($result, true);

// Loop through the services and find the matching service by ID
foreach ($services as $serviceInfo) {
    if ($serviceInfo['id'] == $service) {
        $price = $serviceInfo['cost'];
        break;
    }
}

if ($balance < $price || $balance == 0) {
    $error = 'You do not have enough balance to purchase this number.';
}else{

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sms-activation-service.com/stubs/handler_api?api_key='.$api_key.'&action=getNumber&service='.$service.'&operator='.$operator.'&country='.$countryID.'&lang=en');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$headers = array();
$headers[] = 'Accept: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);

    // error codes are RAW, successes are in JSON, possible errors:NO_BALANCE, NO_NUMBERS, success: ACCESS_NUMBER:ID:NUMBER
    if($result == 'NO_BALANCE'){
    $error = 'There was an error, please contact the administrator.';
    }elseif($result == 'NO_NUMBERS'){
    $error = 'This number is not available, please try again later.';
    }elseif(strpos($result, 'ACCESS_NUMBER') !== false){ 
    $number = explode(':', $result);
    $numberID = $number[1];
    $number = $number[2];

    $date = date('Y-m-d H:i:s');
    $id = uid();

    $result = mysqli_query($con, "INSERT INTO `numbers` (`id`, `user_id`, `number_id`, `number`, `country`, `country_id`, `operator`, `service`, `status`, `created_at`) VALUES ('$id', '$user_id', '$numberID', '$number', '$country', '$countryID', '$operator', '$service', 'active', '$date')") or die(mysqli_error($con));

    if($result){
        // remove balance
        $price = $price * 2;
        $newbalance = $balance - $price;
        $result = mysqli_query($con, "UPDATE `users` SET `balance` = '$newbalance' WHERE `id` = '$user_id'") or die(mysqli_error($con));
        if(!$result){
            $error = 'There was an error, please contact the administrator.';
            echo $error;
        }else{
        header('location: numbers?action=purchased');
        exit;
        }
    }

    }else{
        $error = 'There was an error, please contact the administrator.';
        echo $error;
        }
    }
}

?>

<!doctype html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="light" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-layout-mode="light" data-layout-width="fluid" data-layout-position="fixed" data-layout-style="detached">

<head>

        <meta charset="utf-8" />
        <title><?=sitename();?> - <?=page();?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="Minimal Admin & Dashboard Template" name="description" />
        <meta content="Themesbrand" name="author" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="<?=sitelogo();?>">

        <!--Swiper slider css-->
        <link href="assets/libs/swiper/swiper-bundle.min.css" rel="stylesheet" type="text/css" />
        <!-- flag icon css -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6.6/css/flag-icons.min.css"/>
        <!-- Layout config Js -->
        <script src="assets/js/layout.js"></script>
        <!-- Bootstrap Css -->
        <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <!-- Icons Css -->
        <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <!-- App Css-->
        <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
        <!-- custom Css-->
        <link href="assets/css/custom.min.css" rel="stylesheet" type="text/css" />

    </head>
    <style>
        
/*html, body {margin: 0; height: 100%; overflow: hidden}

    </style>
    <body>

        <!-- Begin page -->
        <div id="layout-wrapper">
        
            <?=loadbar('header');?>
            
            <!-- ========== App Menu ========== -->
            <?=loadbar('menu');?>
            <!-- Left Sidebar End -->
            <!-- Vertical Overlay-->
            <div class="vertical-overlay"></div>
            
            <!-- ============================================================== -->
            <!-- Start right Content here -->
            <!-- ============================================================== -->
            <div class="main-content">
                
                <div class="page-content">
                    <div class="container-fluid">

                        <div class="row">
                                        <div class="vertical-overlay"></div>
                                <div class="alert alert-info" role="alert">
                                <strong> Do you have question?</strong> Just read our <a href="faq"><b>FAQ</b></a> or contact with our <a href="https://t.me/smsverifysupport">Support Team</a>!
                                </div>

                        <?php
                                                        if(isset($error)){
                                                            echo '<div class="alert alert-danger alert-border-left alert-dismissible fade show" role="alert">
                                                            <i class="ri-error-warning-line me-3 align-middle"></i> <strong>Error.</strong> '.$error.'
                                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                            </div>';
                                                        }
                                                    ?>

                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-animate">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div class="flex-grow-1">
                                                <p class="text-uppercase fw-medium text-muted text-truncate fs-13">Balance</p>
                                                <h4 class="fs-22 fw-semibold mb-3">$<?=$balance;?></h4>
                                                <div class="d-flex align-items-center gap-2">
                                                    <h5 class="text-info fs-12 mb-0">
                                                        <i class="ri-arrow-right-up-line fs-13 align-middle"></i> 
                                                    </h5>
                                                    <p class="text-muted mb-0">Total Balance Available</p>
                                                </div>
                                            </div>
                                            <div class="avatar-sm flex-shrink-0">
                                                <span class="avatar-title bg-soft-info rounded fs-3">
                                                    <i class="bx bx-wallet text-info"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div><!-- end card body -->
                                    <div class="animation-effect-6 text-info opacity-25">
                                        <i class="bi bi-currency-dollar"></i>
                                    </div>
                                    <div class="animation-effect-4 text-info opacity-25">
                                        <i class="bi bi-currency-pound"></i>
                                    </div>
                                    <div class="animation-effect-3 text-info opacity-25">
                                        <i class="bi bi-currency-euro"></i>
                                    </div>
                                </div><!-- end card -->
                            </div><!-- end col -->
    
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-animate">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div class="avatar-sm flex-shrink-0">
                                                <span class="avatar-title bg-soft-pimary rounded fs-3">
                                                    <i class="bx bx-shopping-bag text-pimary"></i>
                                                </span>
                                            </div>
                                            <div class="text-end flex-grow-1">
                                                <p class="text-uppercase fw-medium text-muted text-truncate fs-13">Orders</p>
                                                <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value" data-target="<?=$totalorders;?>"><?=$totalorders;?></span></h4>
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <h5 class="text-primary fs-12 mb-0">
                                                        <i class="ri-arrow-right-up-line fs-13 align-middle"></i> 
                                                    </h5>
                                                    <p class="text-muted mb-0">Total Orders</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- end card body -->
                                    <div class="animation-effect-6 text-pimary opacity-25 left">
                                        <i class="bi bi-handbag"></i>
                                    </div>
                                    <div class="animation-effect-4 text-pimary opacity-25 left">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div class="animation-effect-3 text-pimary opacity-25 left">
                                        <i class="bi bi-bag-check"></i>
                                    </div>
                                </div><!-- end card -->
                            </div><!-- end col -->
    
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-animate">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div class="flex-grow-1">
                                                <p class="text-uppercase fw-medium text-muted text-truncate fs-13">Numbers</p>
                                                <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value" data-target="<?=$totalnumbers;?>"><?=$totalnumbers;?></span></h4>
                                                <div class="d-flex align-items-center gap-2">
                                                    <h5 class="text-success fs-12 mb-0">
                                                        <i class="ri-arrow-right-up-line fs-13 align-middle"></i> 
                                                    </h5>
                                                    <p class="text-muted mb-0">Total Numbers</p>
                                                </div>
                                            </div>
                                            <div class="avatar-sm flex-shrink-0">
                                                <span class="avatar-title bg-soft-primary rounded fs-3">
                                                    <i class="bx bx-phone text-primary"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div><!-- end card body -->
                                    <div class="animation-effect-6 text-warning opacity-25">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <div class="animation-effect-4 text-warning opacity-25">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div class="animation-effect-3 text-warning opacity-25">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div><!-- end card -->
                            </div><!-- end col -->
                            
    
                            <div class="col-xl-3 col-md-6">
                                <!-- card -->
                                <div class="card card-animate">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div class="avatar-sm flex-shrink-0">
                                                <span class="avatar-title bg-soft-warning rounded fs-3">
                                                    <i class="bx bx-flag text-warning"></i>
                                                </span>
                                            </div>
                                            <div class="text-end flex-grow-1">
                                                <p class="text-uppercase fw-medium text-muted text-truncate fs-13">Total Countries</p>
                                                <h4 class="fs-22 fw-semibold mb-3"><span class="counter-value" data-target="<?=$totalcountries;?>"><?=$totalcountries;?></span></h4>
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <h5 class="text-warning fs-12 mb-0">
                                                        <i class="ri-arrow-right-up-line fs-13 align-middle"></i> 
                                                    </h5>
                                                    <p class="text-muted mb-0">Total Countries available</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- end card body -->
                                    <div class="animation-effect-6 text-info opacity-25 left">
                                        <i class="bi bi-handbag"></i>
                                    </div>
                                    <div class="animation-effect-4 text-info opacity-25 left">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div class="animation-effect-3 text-info opacity-25 left">
                                        <i class="bi bi-bag-check"></i>
                                    </div>
                                </div><!-- end card -->
                            </div><!-- end col -->
                        </div>

                        <style>
                            /* make input serach more visible, more indexed and a layout */
                            .search-box {
                                position: relative;
                                padding: 0 0 0 0;
                                margin: 0 0 0 0;
                                width: 100%;
                                height: 100%;
                                border: 0;
                                border-radius: 0;
                                background: transparent;
                                box-shadow: none;
                                outline: none;
                                transition: all 0.3s ease;
                                color: #fff;
                                box-shadow: inset 0 0 0 9999px rgba(0, 0, 0, 0.1);
                            }
                        </style>

                        <div class="row">
                            <div class="col">

                                    <div class="row">
                                        <div class="col-xl-4">
                                            <div class="card">
                                                <div class="card-header d-flex">
                                                    <h5 class="card-title flex-grow-1 mb-0">Select your country | <a style="color: #429cb3">Selected: <?php if($_GET['country'] != ''){ echo ' '.$_GET['country']; } ?></a></h5>
                                                    <!-- serach for number -->
                                                </div>
                                                    <div class="search-box card card-animate card-height-100 ">
                                                        <input type="text" class="form-control border-0 " id="searchResultList" placeholder="Search for name or number..." autocomplete="off">
                                                        <i class="ri-search-line search-icon"></i>
                                                    </div>
                                                <div class="card-body px-0">
                                                    <div data-simplebar style="max-height: 523px;">
                                                        
                                                        <div class="vstack gap-3 px-3">
                                                            <?php
                                                            foreach ($countries as $country) {
                                                                $countryCode = CountryCodeConverter::convert($country['name']);
                                                                $countryCode = strtolower($countryCode);
                                                            ?>                                                
                                                            <div class="p-3 border border-dashed rounded-3 searchResult <?php if($_GET['country'] == $country['name']){ echo 'bg-light'; } ?>">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="avatar-sm bg-light rounded p-1 flex-shrink-0">
                                                                        <span class="fi fi-<?=$countryCode;?> img-fluid d-block" style="width: 45px; height: 45px;margin-top:-2%;"></span>
                                                                    </div>
                                                                    <div class="flex-grow-1 overflow-hidden">
                                                                        <h6 class="text-truncate"><?=$country['name'];?></h6>
                                                                        <p class="text-truncate mb-0">Select <span class="text-info"><?=$country['name'];?></span></p>
                                                                    </div>
                                                                    <div class="flex-shrink-0">
                                                                        <a class="badge badge-soft-<?php if($_GET['country'] == $country['name']){ echo 'success'; }else{ echo 'primary'; } ?>" href="<?php if($_GET['country'] == $country['name']){ echo 'index'; }else{ echo '?country='.$country['name'].'&countryID='.$country['id'].''; } ?>"><?php if($_GET['country'] == $country['name']){ echo 'Selected'; }else{ echo 'Select'; } ?></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-8">
                                                    <div class="mb-3">
                                                        <label class="form-label">Select your mobile operator you use</label>
                                                        <select class="form-control" data-choices name="choices-single-default" id="select-country">
                                                        <option value="0" selected>Select your mobile operator</option>
                                                            <?php
                                                            if($_GET['country'] && $_GET['country'] != null && $_GET['operator'] == null){
                                                                // get the opperator based off country name
                                                                $country = $_GET['country'];
                                                                $operators = array();
                                                                foreach($countries as $c){
                                                                    if($c['name'] == $country){
                                                                        $operators = $c['operators'];
                                                                        print_r($operators);
                                                                        // get the operators
                                                                        foreach($operators as $operators){

                                                            ?>
                                                            <option value="<?=$operators;?>"><?=ucfirst($operators);?></option>
                                                            <?php } } } } else { ?>
                                                            <option value="<?=$_GET['operator'];?>" selected><?=ucfirst($_GET['operator']);?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                    <script>
                                                                var country = "<?=$_GET['country'];?>";
                                                                var operator = document.getElementById("select-country");
                                                                operator.addEventListener("change", function() {
                                                                    if(operator.value == 0){
                                                                        window.location.href = "?country=" + country + "&countryID=<?=$_GET['countryID'];?>";
                                                                    }else{
                                                                    window.location.href = "?country=" + country + "&countryID=<?=$_GET['countryID'];?>&operator=" + operator.value + "";
                                                                    } 
                                                                });

                                                            </script>
                                        <hr/>
                                            <div class="card">
                                                <div class="card-header d-flex">
                                                    <h5 class="card-title flex-grow-1 mb-0">Choose a website (service) to register <?php if($_GET['country'] && $_GET['country'] != null && $_GET['operator'] != null){ echo 'for '.$_GET['country'].'';?>
                                                    <?php
                                                    $country = $_GET['country'];
                                                    $operator = $_GET['operator'];
                                                    $operators = array();
                                                    foreach($countries as $c){
                                                        if($c['name'] == $country){
                                                            $operators = $c['operators'];
                                                            // get the operators
                                                            foreach($operators as $operators){
                                                                if($operator == $operators){
                                                                    echo 'with '.ucfirst($operators).'';
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                                    ?>
                                                    
                                                    </h5>
                                                </div>
                                                <div class="search-box card card-animate card-height-100 ">
                                                    <input type="text" class="form-control border-0 " id="searchServiceResult" placeholder="Search for website or service..." autocomplete="off">
                                                    <i class="ri-search-line search-icon"></i>
                                                </div>
                                                <div class="card-body px-0">
                                                    <div data-simplebar style="max-height: 420px;">
                                                        <div class="vstack gap-3 px-3">
                                                        <?php
if($_GET['country'] && $_GET['country'] != null && $_GET['operator'] != null){
$country = $_GET['country'];
$operator = $_GET['operator'];
$countryID = $_GET['countryID'];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sms-activation-service.com/stubs/handler_api?api_key='.$api_key.'&action=getServicesAndCost&country='.$countryID.'&operator='.$operator.'&lang=en');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$headers = array();
$headers[] = 'Accept: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);
$services = json_decode($result, true);

foreach($services as $service){

    if($service['quantity'] > 0){

                                                        
                                                        ?>
                                                        <div class="p-3 border border-dashed rounded-3 searchService">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="avatar-sm bg-light rounded p-1 flex-shrink-0">
                                                                        <img src="https://sms-activation-service.com/frontend/assets/images/services/icons/<?=$service['id'];?>.png?1673295717076" alt="" class="img-fluid d-block" width="30" height="30" style="margin-left:12%;margin-top:14%;">
                                                                    </div>
                                                                    <div class="flex-grow-1 overflow-hidden">
                                                                        <h6 class="text-truncate"><?=$service['name'];?></h6>
                                                                        <p class="text-truncate mb-0"><span class="text-info">Service - <b>$
                                                                            
                                                                        <?php
                                                                        $price = $service['price'];
                                                                        // price should be 2 times more than the original price
                                                                        $price = $price * 2;
                                                                        echo $price;
                                                                        ?>
                                                                        </b></span></p>
                                                                    </div>
                                                                    <div class="flex-shrink-0">
                                                                        <div class="avatar-sm flex-shrink-0">
                                                                            <a href="?country=<?=$_GET['country'];?>&countryID=<?=$_GET['countryID'];?>&operator=<?=$_GET['operator'];?>&service=<?=$service['id'];?>" class="d-block">
                                                                            <span class="avatar-title bg-soft-light rounded fs-3">
                                                                                <i class="bx bx-shopping-bag text-success"></i>
                                                                            </span>
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    <?php } } } else { ?>
                                                    <div class="alert alert-info" role="alert">
                                                        <strong>You have not selected <?php if($_GET['country'] == null){ echo 'Country'; }elseif($_GET['operator'] == null){ echo 'Operator'; } else { echo 'Country and Operator'; } ?>!</strong> Please select your country and operator to see the list of services.
                                                    </div>
                                                    <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- end row-->
                                    
                                </div> <!-- end .h-100-->

                            </div> <!-- end col -->

                            <div class="col-auto layout-rightside-col">
                                <div class="overlay"></div>
                                <div class="layout-rightside">
                                </div> <!-- end .rightbar-->
                            </div> <!-- end col -->
                        </div>

                    </div>
                    <!-- container-fluid -->
                </div>
                <!-- End Page-content -->

            </div>
            <!-- end main content-->

        </div>
        <!-- END layout-wrapper -->



        <!--start back-to-top-->
        <button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top">
            <i class="ri-arrow-up-line"></i>
        </button>
        <!--end back-to-top-->

        <!--preloader-->
        <div id="preloader">
            <div id="status">
                <div class="spinner-border text-primary avatar-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>

    <!-- JAVASCRIPT -->
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/pages/plugins/lord-icon-2.1.0.js"></script>
    <script src="assets/js/plugins.js"></script>

    <!-- prismjs plugin -->
    <script src="assets/libs/prismjs/prism.js"></script>

    <script src="assets/js/app.js"></script>
    <script>
        var img = document.getElementsByTagName('img');
         for (var i = 0; i < img.length; i++) {
           img[i].onerror = function() {
                 this.src = 'https://sms-activation-service.com/frontend/assets/img/services-icon/default.svg';
            }
         }
         var serachInput = document.getElementById('searchResultList');
            serachInput.addEventListener('keyup', function(e) {
                var searchValue = e.target.value.toLowerCase();
                var searchResult = document.querySelectorAll('.searchResult');
                searchResult.forEach(function(item) {
                    var itemValue = item.textContent.toLowerCase();
                    if (itemValue.indexOf(searchValue) != -1) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        var searchServiceInput = document.getElementById('searchServiceResult');
            searchServiceInput.addEventListener('keyup', function(e) {
                var searchValue = e.target.value.toLowerCase();
                var searchResult = document.querySelectorAll('.searchService');
                searchResult.forEach(function(item) {
                    var itemValue = item.textContent.toLowerCase();
                    if (itemValue.indexOf(searchValue) != -1) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
    </script>
    </body>

</html>