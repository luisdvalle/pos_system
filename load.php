<?php

/* Load the correct database class file. */

function require_db() {
	global $db;

	require_once( ABSPATH . 'db.php' );  //include 'ABSPATH .' when the absolute path constant is defined

	if ( isset( $db ) )
		return;

	$db = new db( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
}

/* Sets format specifiers for database table columns. */

function is_set_db_vars() {
	global $db;

	$db->field_types = array( 'client_id' => '%d', 'staff_id' => '%d', 'product_id' => '%d', 'product_nomPrice' => '%f', 'product_sizeCode' => '%f', 'followups_id' => '%d',
		'followups_client_id' => '%d', 'followups_staff_id' => '%d','address_id' => '%d', 'cusAddrRel_client_id' => '%d', 'cusAddrRel_address_id' => '%d', 'contract_id' => '%d', 
		'contract_installments' => '%d', 'contract_installment1' => '%f', 'contract_installment2' => '%f', 'contract_installment3' => '%f', 'contract_installment4' => '%f', 
		'contract_client_id' => '%d', 'contract_product_id' => '%d', 'contract_staff_id' => '%d', 
	);

}

	
