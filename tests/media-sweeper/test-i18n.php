<?php
// Every Persian string literal of the plugin must have an English catalog entry.
define( 'ABSPATH', __DIR__ );
$dir = __DIR__ . '/../../MyContent/plugins/media-sweeper';
$cat = include $dir . '/languages/en_US.php';
$fa  = '/[\x{0600}-\x{06FF}]/u';
$missing = array();

foreach ( array_merge( glob( $dir . '/*.php' ), glob( $dir . '/includes/*.php' ) ) as $f ) {
	foreach ( token_get_all( file_get_contents( $f ) ) as $t ) {
		if ( ! is_array( $t ) || ! in_array( $t[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) || ! preg_match( $fa, $t[1] ) ) {
			continue;
		}
		$s = $t[1];
		if ( T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
			$q = $s[0];
			$s = substr( $s, 1, -1 );
			$s = ( "'" === $q ) ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $s ) : stripcslashes( $s );
		} else {
			$s = stripcslashes( $s );
		}
		// Strings rendered through MSW_I18n::tr() inside markup are checked as plain keys below.
		if ( ! isset( $cat[ $s ] ) && ! preg_match( '/^[<>]|<\/|^\s*>/', $s ) ) {
			$missing[ $s ] = basename( $f );
		}
	}
}
$js = file_get_contents( $dir . '/assets/admin.js' );
preg_match_all( "/'((?:[^'\\\\\\n]|\\\\.)*)'/u", $js, $m );
foreach ( $m[1] as $s ) {
	if ( preg_match( $fa, $s ) ) {
		$s = str_replace( array( '\\n', "\\'", '\\\\' ), array( "\n", "'", '\\' ), $s );
		if ( ! isset( $cat[ $s ] ) ) {
			$missing[ $s ] = 'admin.js';
		}
	}
}
foreach ( $cat as $k => $v ) {
	if ( '' === trim( $v ) ) {
		$missing[ "EMPTY VALUE: $k" ] = 'catalog';
	}
}
foreach ( $missing as $s => $f ) {
	echo "MISSING [$f]: " . str_replace( "\n", '\n', substr( $s, 0, 80 ) ) . "\n";
}
echo $missing ? "\n" . count( $missing ) . " FAILED\n" : "ALL PASSED (" . count( $cat ) . " catalog entries)\n";
exit( $missing ? 1 : 0 );
