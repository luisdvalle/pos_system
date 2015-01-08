<?php
session_start();
include('includes/sessioncheck.php');
include('includes/functions.php'); 

if ( $_SESSION['contract_paymentTypeInst1'] == "credit card" && ( $_SESSION['contract_installments'] == 2 || $_SESSION['contract_installments'] == 3)  ) {

	require_once('eway/recurring/webservice/lib/nusoap.php');
	// read ID, Username and Password from config.ini
	$config = parse_ini_file("eway/recurring/webservice/config.ini");
	// init soap client
	$client = new nusoap_client("https://www.ewaygateway.com/gateway/rebill/manageRebill.asmx", false);
	//$client = new nusoap_client("https://www.eway.com.au/gateway/rebill/test/manageRebill_test.asmx", false);
	$err = $client->getError(); 
	if ($err) {
		echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
		echo '<h2>Debug</h2><pre>' . htmlspecialchars($client->getDebug(), ENT_QUOTES) . '</pre>';
		exit();
	}
	// set namespace
	$client->namespaces['man'] = 'http://www.eway.com.au/gateway/rebill/manageRebill';
	// set SOAP header	
	$headers = "<man:eWAYHeader><man:eWAYCustomerID>" . $config['eWAYCustomerID'] . "</man:eWAYCustomerID><man:Username>" . $config['UserName'] . "</man:Username><man:Password>" . $config['Password'] . "</man:Password></man:eWAYHeader>";
	$client->setHeaders($headers);
}

/*--- eWay API connection ---*/

$customer_email = ($_SESSION['client_email'] != "") ? $_SESSION['client_email'] : 'info@incentivemedia.com.au';

if ( $_SESSION['contract_paymentTypeInst1'] == "credit card" && ( $_SESSION['contract_installments'] == 2 || $_SESSION['contract_installments'] == 3 ) ) {

$requestbody = array(
        'man:customerTitle' => $_POST['customerTitle'],
        //'man:customerFirstName' => ucfirst($first_name_is),
		'man:customerFirstName' => ucfirst($_SESSION['client_contact']),
        //'man:customerLastName' => ucfirst($second_name_is),
		'man:customerLastName' => ucfirst($_SESSION['client_contact_surname']),
        'man:customerAddress' => ucwords($_SESSION['address_streetbs']),
        'man:customerSuburb' => ucwords($_SESSION['address_citybs']),
        'man:customerState' => strtoupper($_SESSION['address_statebs']),
        'man:customerCompany' => ucwords($_SESSION['client_tradingName']),
        'man:customerPostCode' => $_SESSION['address_pcodebs'],
        'man:customerCountry' => 'Australia',
        //'man:customerEmail' => $_SESSION['client_email'],
		//'man:customerEmail' => 'info@incentivemedia.com.au',
        'man:customerEmail' => $customer_email,
        'man:customerFax' => $_POST['customerFax'],
        'man:customerPhone1' => $_POST['customerPhone1'],
        'man:customerPhone2' => $_POST['customerPhone2'],
        'man:customerRef' => 'IM-00'.$_SESSION['contract_id'],
        'man:customerJobDesc' => ucwords($_SESSION['client_contactPosition']),
        'man:customerComments' => $_POST['customerComments'],
        //'man:customerURL' => $_SESSION['client_website'],
		//'man:customerURL' => $_POST['customerURL']
		'man:customerURL' => 'http://incentivemedia.com.au'
    );
	$soapaction = 'http://www.eway.com.au/gateway/rebill/manageRebill/CreateRebillCustomer';
    $result = $client->call('man:CreateRebillCustomer', $requestbody, '', $soapaction);
	$_SESSION['createRebillCustomer_result'] = $result;

if ($client->fault) {
	echo '<h2>Fault (Expect - The request contains an invalid SOAP body)</h2><pre>'; print_r($result); echo '</pre>';
} else {
	$err = $client->getError();
	if ($err) {
		echo '<h2>Error</h2><pre>' . $err . '</pre>';
		$_SESSION['createRebillCustomer_errors'] = $err;
	} else {
		$_SESSION['createRebillCustomer_result'] = $result;
		if ( $result['RebillCustomerID'] == 0 || $result['RebillCustomerID'] == "" ) {
			header("Location: createRebillCustomerError.php");
			exit;
		} else {
			$_SESSION['client_ewayId'] = $result['RebillCustomerID'];
			//$array_ewayid = array( 'client_ewayId' => $result['RebillCustomerID'] );
			//$array_ewayid = array( 'client_ewayId' => $_SESSION['client_ewayId'] );
			//$array_where_ewayid = array( 'client_id' => $client_id );
			//$isdb->update( 'incmedia_client', $array_ewayid, $array_where_ewayid  );
		}
	}
}
} //end of if stament for installments equal to 2 or 3


/*--- eWay API connection ---*/

$names_is = $_SESSION['client_contact'];
list($first_name_is, $second_name_is) = explode( " ", $names_is );


//Getting arrays with data for database
$array_client = client_array(); 
$array_addr_bs =  address_array('b'); 
$array_addr_ml =  address_array('m');
$array_contract = contract_array();
//echo "Array Client: <br/>";
//var_dump($array_client);
//echo "Array Address Business: <br/>";
//var_dump($array_addr_bs);
//echo "Array Address Mailing: <br/>";
//var_dump($array_addr_ml);
//echo "Array Contract: <br/>";
//var_dump($array_contract);

//Inserting Client data
$isdb->insert( 'incmedia_client', $array_client ); 
$client_id = $isdb->insert_id;
//echo "Client ID: ".$client_id;

//Inserting Addresses and Relationships
$array_same = $_POST['address_same'];
if( $array_same == 1 ) {
	$isdb->insert( 'incmedia_address', $array_addr_bs );
	$address_id = $isdb->insert_id;
	$array_cusAddrRel_bs = array( 'cusAddrRel_client_id' => $client_id,  'cusAddrRel_address_id' => $address_id, 'cusAddrRel_addrType' => 'business address' );
	$isdb->insert( 'incmedia_cusAddrRel', $array_cusAddrRel_bs );
	$array_cusAddrRel_ml = array( 'cusAddrRel_client_id' => $client_id,  'cusAddrRel_address_id' => $address_id, 'cusAddrRel_addrType' => 'mailing address' );
	$isdb->insert( 'incmedia_cusAddrRel', $array_cusAddrRel_ml );
} else {
	$isdb->insert( 'incmedia_address', $array_addr_bs );
	$address_id_bs = $isdb->insert_id;
	$isdb->insert( 'incmedia_address', $array_addr_ml );
	$address_id_ml = $isdb->insert_id;
	$array_cusAddrRel_bs = array( 'cusAddrRel_client_id' => $client_id,  'cusAddrRel_address_id' => $address_id_bs, 'cusAddrRel_addrType' => 'business address' );
	$isdb->insert( 'incmedia_cusAddrRel', $array_cusAddrRel_bs );
	$array_cusAddrRel_ml = array( 'cusAddrRel_client_id' => $client_id,  'cusAddrRel_address_id' => $address_id_ml, 'cusAddrRel_addrType' => 'mailing address' );
	$isdb->insert( 'incmedia_cusAddrRel', $array_cusAddrRel_ml );
}

//Inserting Contract Data
$array_contract['contract_client_id'] = $client_id;
$total_price = $_POST['total_price'];
$array_contract['contract_total'] = $total_price;
$installment1 = $_SESSION['$installment1'] = $_POST['installment1'];
$installment2 = $_SESSION['$installment2'] = $_POST['installment2'];
$installment3 = $_SESSION['$installment3'] = $_POST['installment3'];
$array_contract['contract_installment1'] = $installment1;
$array_contract['contract_installment2'] = $installment2;
$array_contract['contract_installment3'] = $installment3;

date_default_timezone_set('Australia/Sydney');

$today = $_SESSION['date_first_inst'];
$today = date( "Y-m-d", strtotime( $today ) );

//$today = date("Y-m-d");
$today_4w = strtotime( '+4 week' , strtotime( $today ) );
$today_4w = date( 'Y-m-d' , $today_4w );
$today_8w = strtotime( '+8 week' , strtotime( $today ) );
$today_8w = date( 'Y-m-d' , $today_8w );
if ( $_SESSION['contract_installments'] == 3 ) {
	$array_contract['contract_dueDate2'] = $today_4w;
	$array_contract['contract_dueDate3'] = $today_8w;
} elseif ( $_SESSION['contract_installments'] == 2 ) {
	$array_contract['contract_dueDate2'] = $today_4w;
	$array_contract['contract_dueDate3'] = $today;
} elseif ( $_SESSION['contract_installments'] == 1 ) {
	$array_contract['contract_dueDate2'] = $today;
	$array_contract['contract_dueDate3'] = $today;
}

$isdb->insert( 'incmedia_contract', $array_contract );
$_SESSION['contract_id'] = $isdb->insert_id;

//Inserting eWay customer ID in database
$array_ewayid = array( 'client_ewayId' => $_SESSION['client_ewayId'] );
$array_where_ewayid = array( 'client_id' => $client_id );
$isdb->update( 'incmedia_client', $array_ewayid, $array_where_ewayid  );


/*$customer_email = ($_SESSION['client_email'] != "") ? $_SESSION['client_email'] : 'info@incentivemedia.com.au';

if ( $_SESSION['contract_paymentTypeInst1'] == "credit card" && ( $_SESSION['contract_installments'] == 2 || $_SESSION['contract_installments'] == 3 ) ) {

$requestbody = array(
        'man:customerTitle' => $_POST['customerTitle'],
        'man:customerFirstName' => ucfirst($first_name_is),
        'man:customerLastName' => ucfirst($second_name_is),
        'man:customerAddress' => ucwords($_SESSION['address_streetbs']),
        'man:customerSuburb' => ucwords($_SESSION['address_citybs']),
        'man:customerState' => strtoupper($_SESSION['address_statebs']),
        'man:customerCompany' => ucwords($_SESSION['client_tradingName']),
        'man:customerPostCode' => $_SESSION['address_pcodebs'],
        'man:customerCountry' => 'Australia',
        //'man:customerEmail' => $_SESSION['client_email'],
	//'man:customerEmail' => 'info@incentivemedia.com.au',
        'man:customerEmail' => $customer_email,
        'man:customerFax' => $_POST['customerFax'],
        'man:customerPhone1' => $_POST['customerPhone1'],
        'man:customerPhone2' => $_POST['customerPhone2'],
        'man:customerRef' => 'IM-00'.$_SESSION['contract_id'],
        'man:customerJobDesc' => ucwords($_SESSION['client_contactPosition']),
        'man:customerComments' => $_POST['customerComments'],
        //'man:customerURL' => $_SESSION['client_website'],
		//'man:customerURL' => $_POST['customerURL']
		'man:customerURL' => 'http://incentivemedia.com.au'
    );
	$soapaction = 'http://www.eway.com.au/gateway/rebill/manageRebill/CreateRebillCustomer';
    $result = $client->call('man:CreateRebillCustomer', $requestbody, '', $soapaction);

if ($client->fault) {
	echo '<h2>Fault (Expect - The request contains an invalid SOAP body)</h2><pre>'; print_r($result); echo '</pre>';
} else {
	$err = $client->getError();
	if ($err) {
		echo '<h2>Error</h2><pre>' . $err . '</pre>';
		$_SESSION['createRebillCustomer_errors'] = $err;
		$_SESSION['createRebillCustomer_result'] = $result;
	} else {
		//echo '<h2>Result</h2><pre>'; print_r($result); echo '</pre>';
		if ( $result['RebillCustomerID'] == 0 || $result['RebillCustomerID'] == "" ) {
			header("Location: createRebillCustomerError.php");
		} else {
			$_SESSION['client_ewayId'] = $result['RebillCustomerID'];
			$array_ewayid = array( 'client_ewayId' => $result['RebillCustomerID'] );
			$array_where_ewayid = array( 'client_id' => $client_id );
			$isdb->update( 'incmedia_client', $array_ewayid, $array_where_ewayid  );
		}
	}
}
} */ //end of if stament for installments equal to 2 or 3


if ( $_SESSION['contract_paymentTypeInst1'] == 'credit card' ) {
	if ( $_SESSION['contract_installments'] == 2 || $_SESSION['contract_installments'] == 3 ) {
		header("Location: eway/recurring/webservice/rebill.php");
	} elseif ($_SESSION['contract_installments'] == 1) {
		header("Location: eway/directpayment/rebill.php");
	}
} elseif ( $_SESSION['client_type'] <> 'real estate' && $_SESSION['client_type'] <> 'wedding venue' ) {
	header("Location: rebill_cch.php");
} else {
	header("Location: confirmation_re.php");
}

?>
