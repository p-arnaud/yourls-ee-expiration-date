<?php
/*
Plugin Name: YOURLS EE Expiration Date
Plugin URI: https://github.com/p-arnaud/yourls-ee-expiration-date
Description: This plugin enables the feature of expiration date for your short URLs.
Version: 1.1
Author: p-arnaud
Author URI: https://github.com/p-arnaud
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Add column to admin's url listing
yourls_add_filter('table_head_cells', 'ee_expiration_date_table_head_cells');
function ee_expiration_date_table_head_cells($args) {
    $ee_multi_users_plugin = yourls_is_active_plugin('yourls-ee-multi-users/plugin.php');
    if ($ee_multi_users_plugin == 1 and ee_multi_users_is_admin(YOURLS_USER) === true) {
        return $args;
    }
    else {
        $args['expire-date'] = 'Expiration date';
    }
    return $args;
}
// Show date in admin's url listing
yourls_add_filter('table_add_row_cell_array', 'ee_expiration_date_table_add_row_cell_array');
function ee_expiration_date_table_add_row_cell_array($args) {

    $ee_multi_users_plugin = yourls_is_active_plugin('yourls-ee-multi-users/plugin.php');
    if ($ee_multi_users_plugin == 1 and ee_multi_users_is_admin(YOURLS_USER) === true) {

    }
    else {
        global $ydb;
        $ee_expirationdate_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true );
        if ($ee_expirationdate_array[$args['keyword']['keyword_html']]) {
            $str_date = $ee_expirationdate_array[$args['keyword']['keyword_html']];
            $format = 'Y-m-d';
            $date = DateTime::createFromFormat($format, $str_date);
            $datetime = new DateTime('now');
            $format = 'd/m/Y';
            $str_date = date_format ( $date , $format );
            if ( $date < $datetime ) {

                $str_date = '<s>' . $str_date . '</s>';
            }
        } else {
            $str_date = "";
        }

        $args['expire-date'] = array(
            'template' => '%expire-date%',
            'expire-date' => '<a " href=plugins.php?page=ee_expirationdate&shortname=' . $args['keyword']['keyword_html'] . '><img src="../images/pencil.png"/></a> ' . $str_date,
        );
    }
    return $args;
}

// Do redirection
yourls_add_action( 'pre_redirect', 'ee_check_date' );
function ee_check_date( $args ) {
    global $ydb;

    if( !isset($ydb->option[ 'ee_expirationdate' ]) ){
        yourls_add_option( 'ee_expirationdate', 'null' );
    }

    $ee_expirationdate_fullurl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $ee_expirationdate_urlpath = parse_url( $ee_expirationdate_fullurl, PHP_URL_PATH );
    $ee_expirationdate_pathFragments = explode( '/', $ee_expirationdate_urlpath );
    $ee_expirationdate_short = end( $ee_expirationdate_pathFragments );

    $ee_expirationdate_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true );
    if( array_key_exists( $ee_expirationdate_short, $ee_expirationdate_array ) ) {
        $format = 'Y-m-d';
        $date = DateTime::createFromFormat($format, $ee_expirationdate_array[$ee_expirationdate_short]);
        $datetime = new DateTime('now');
        if ( $date < $datetime ) {
            echo <<<ERROR
      <style>
        #error-box {
          background-color: #e8e8e8;
          box-shadow: 0 10px 16px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19) !important;

          width: 400px !important;
          height: 220px !important;

          position: fixed;
          top: 50%;
          left: 50%;
          /* bring your own prefixes */
          transform: translate(-50%, -50%);
        }
      </style>
      <div id="error-box">
        <p>Sorry, this link has expired.</p>
      </div>
ERROR;
            die;
        }
    }
}

// Register plugin page in admin page
yourls_add_action( 'plugins_loaded', 'ee_expirationdate_display_panel' );
function ee_expirationdate_display_panel() {
    yourls_register_plugin_page( 'ee_expirationdate', 'YOURLS EE Expiration Date', 'ee_expirationdate_display_page' );
}

// Function which will draw the admin page
function ee_expirationdate_display_page() {
    global $ydb;
    if( isset( $_POST[ 'date-checked' ] ) && isset( $_POST[ 'date' ] ) || isset( $_POST[ 'date-unchecked' ] ) ) {
        ee_expirationdate_process_new();
    } else {
        if( !isset( $ydb->option[ 'ee_expirationdate' ] ) ){
            yourls_add_option( 'ee_expirationdate', 'null' );
        }
    }
    ee_expirationdate_process_display();
}

// Set/Delete date from DB
function ee_expirationdate_process_new() {
    global $ydb;
    $ee_expirationdate_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true ); //Get's array of currently active Date Protected URLs

    $ee_multi_users_plugin = yourls_is_active_plugin('yourls-ee-multi-users/plugin.php');
    $user_keywords = array();
    if ($ee_multi_users_plugin == 1) {
        $user_keywords = ee_multi_users_get_current_user_keywords();
    }

    // Sanitize
    foreach ($_POST[ 'date' ] as $key => $value) {
        if (array_search($key, $user_keywords) !== false) {
            $sanitized = ee_expiration_date_sanitize_date($value);
            if ($sanitized === false) {
                unset($ee_expirationdate_array[$key]);
            } else {
                $ee_expirationdate_array[$key] = ee_expiration_date_sanitize_date($value);
            }
        }
    }
    foreach ( $ee_expirationdate_array as $key => $value ){
        if ($ee_multi_users_plugin == 0 || array_search($key, $user_keywords) !== false) {
            if (array_search($key, array_keys($_POST['date'])) === false) {
                unset($ee_expirationdate_array[ $key ]);
            }
        }
    }
    yourls_update_option( 'ee_expirationdate', json_encode( $ee_expirationdate_array ) );
    echo "<p style='color: green'>Success!</p>";
    return yourls_apply_filter( 'ee_expirationdate_process_new', $_POST );
}

//Display Form
function ee_expirationdate_process_display() {
    global $ydb;
    $ee_multi_users_plugin = yourls_is_active_plugin('yourls-ee-multi-users/plugin.php');
    $user_keywords = array();
    if ($ee_multi_users_plugin == 1) {
        $where = ee_multi_users_admin_list_where();
    }
    else {
        $where = "";
    }

    $table = YOURLS_DB_TABLE_URL;
    $query = $ydb->get_results( "SELECT * FROM `$table` WHERE 1=1" . $where );

    $ee_su = yourls__( "Short URL"   , "ee_expirationdate" ); //Translate "Short URL"
    $ee_ou = yourls__( "Original URL", "ee_expirationdate" ); //Translate "Original URL"
    $ee_date = yourls__( "Date"    , "ee_expirationdate" ); //Translate "Date"

    echo <<<TB
	<style>
	table {
		border-collapse: collapse;
		width: 100%;
	}

	th, td {
		text-align: left;
		padding: 8px;
	}

	tr:nth-child(even){background-color: #f2f2f2}
	tr:nth-child(odd){background-color: #fff}
	</style>
	<div style="overflow-x:auto;">
		<form method="post">
			<table>
				<tr>
					<th>$ee_su</th>
					<th>$ee_ou</th>
					<th>$ee_date</th>
				</tr>
TB;
    foreach( $query as $link ) { // Displays all shorturls in the YOURLS DB

        $short = $link->keyword;
        $url = $link->url;
        $ee_expirationdate_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true ); //Get's array of currently active Date Protected URLs

        if( strlen( $url ) > 31 ) { //If URL is too long it will shorten it
            $sURL = substr( $url, 0, 30 ). "...";
        } else {
            $sURL = $url;
        }

        $date = null;
        $date_text = yourls__( "Enable?" );
        $date_date = '';
        $date_checked = '';
        $date_unchecked = ' disabled';
        $date_style = 'display: none';
        $date_disabled = ' disabled';
        if( array_key_exists( $short, $ee_expirationdate_array ) ){ //Check's if URL is currently date protected or not
            $text = yourls__( "Enable?" );
            $date = $ee_expirationdate_array[ $short ];

            $date_checked = " checked";
            $date_unchecked = '';
            $date_style = '';
            $date_disabled = '';
        }
        // Only show selected item if this page is called with 'shortname' parameter
        if ((isset($_GET['shortname']) && $_GET['shortname'] == $link->keyword) || !isset($_GET['shortname'])) {
            $display = 'table-row';
        }
        else {
            $display = 'none';
        }

        echo <<<TABLE
  				<tr style=display:$display>
  					<td>$short</td>
  					<td><span title="$url">$sURL</span></td>
  					<td>
  						<input type="checkbox" name="date-checked[{$short}]" class="ee_expirationdate_checkbox" value="enable" data-input="date-$short"$date_checked> $text
  						<input type="hidden" name="date-unchecked[{$short}]" id="date-{$short}_hidden" value="true"$date_unchecked>
  						<input id="date-$short" type="date" name="date[$short]" style="$date_style" value="$date" placeholder="Date..."$date_disabled ><br>
  					</td>
  				</tr>
TABLE;
        // }
    }
    echo <<<END
			</table>
			<input type="submit" value="Submit">
		</form>
	</div>
	<script>
		$( ".ee_expirationdate_checkbox" ).click(function() {
			var dataAttr = "#" + this.dataset.input;
			$( dataAttr ).toggle();
			if( $( dataAttr ).attr( 'disabled' ) ) {
				$( dataAttr ).removeAttr( 'disabled' );

				$( dataAttr + "_hidden" ).attr( 'disabled' );
				$( dataAttr + "_hidden" ).prop('disabled', true);
			} else {
				$( dataAttr ).attr( 'disabled' );
				$( dataAttr ).prop('disabled', true);

				$( dataAttr + "_hidden" ).removeAttr( 'disabled' );
			}
		});
	</script>
END;
}

// Delete old settings when a link is delete
yourls_add_action( 'delete_link' , 'ee_expiration_date_delete_link');
function ee_expiration_date_delete_link( $args ) {
    $keyword = $args[0];
    global $ydb;

    $ee_expirationdate_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true );
    unset($ee_expirationdate_array[$keyword] );
    if ( count($ee_expirationdate_array) > 0) {
        yourls_update_option( 'ee_expirationdate', json_encode( $ee_expirationdate_array ) );
    }
    else {
        yourls_update_option( 'ee_expirationdate', null );
    }
}


yourls_add_filter( 'api_action_update', 'api_edit_url_update_date' );
function api_edit_url_update_date() {
    global $ydb;
    if( !isset( $ydb->option[ 'ee_expirationdate' ] ) ){
        yourls_add_option( 'ee_expirationdate', 'null' );
    }
    if( isset( $_REQUEST[ 'url-date-active' ]) && ( $_REQUEST[ 'url-date-active' ] === 'true' ) && isset( $_REQUEST[ 'url-date' ] ) ){
        $shorturl = yourls_sanitize_string($_REQUEST['shorturl']);
        $date = ee_expiration_date_sanitize_date($_REQUEST[ 'url-date' ]);
        $ee_date_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true );
        $ee_date_array[$shorturl] = $date;
        yourls_update_option( 'ee_expirationdate', json_encode( $ee_date_array ) );
    }
    if (isset( $_REQUEST[ 'url-date-active' ]) && $_REQUEST[ 'url-date-active' ] === 'false') {
        $shorturl = yourls_sanitize_string($_REQUEST['shorturl']);
        $ee_expirationdate_array = json_decode( $ydb->option[ 'ee_expirationdate' ], true );
        unset($ee_expirationdate_array[$shorturl] );
        yourls_update_option( 'ee_expirationdate', json_encode( $ee_expirationdate_array ) );
    }
}

function ee_expiration_date_sanitize_date($date) {
    if( !preg_match( '!^\d{4}-\d{1,2}-\d{1,2}$!' , $date ) ) {
        return false;
    }
    return $date;
}

?>
