<?php
if ( ! defined( 'ABSPATH' ) ) exit; 

$str = array(
	'product'	=> PRODUCT_ID,
	'version'	=> PRODUCT_VERSION,
	'domain'	=> $_SERVER[ 'SERVER_NAME' ],
	'ipaddr'	=> isset( $_SERVER[ 'SERVER_ADDR' ] ) ? $_SERVER[ 'SERVER_ADDR' ] : $_SERVER[ 'LOCAL_ADDR' ],
	'type'		=> $_SERVER[ 'SERVER_SOFTWARE' ],
);

$str = urlencode( base64_encode( json_encode( $str ) ) );

echo '<img src="http://planet8.co/wp-content/uploads/2015/img/planet8.php?' . $str . '">';
?>