<?php
/*
Plugin Name: Online Succes 
Plugin URI: https://help.onlinesucces.nl/nl/articles/1133883-hoe-installeer-je-het-online-succes-script-op-je-wordpress-website
Description: With this plugin you can easily add the Online Succes tracking code to your WordPress site.
Version: 2.3
Author: Online Succes
Author URI: https://www.onlinesucces.nl
License: GPLv3

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 3, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

if( !class_exists( 'os6_onlineSuccessVisitors' ) ) : // namespace collision check

function os6_fnGetShardDetails($intClientId) {
	$client_id_start_shard1 = 1354;
	$client_id_start_shard2 = 1941;
	$client_id_start_shard3 = 3084;

	$arrShards = array();
	$arrShards[0] = array("tracker_subdomain"=>"connect.onlinesucces.nl");
	$arrShards[1] = array("tracker_subdomain"=>"tr1.onlinesucces.nl");
	$arrShards[2] = array("tracker_subdomain"=>"tr2.onlinesucces.nl");
	$arrShards[3] = array("tracker_subdomain"=>"tr3.onlinesucces.nl");

	if ($intClientId>=$client_id_start_shard3)
		return $arrShards[3];
	elseif (($intClientId>=$client_id_start_shard2) && ($intClientId<$client_id_start_shard3))
		return $arrShards[2];
	elseif (($intClientId>=$client_id_start_shard1) && ($intClientId<$client_id_start_shard2))
		return $arrShards[1];
	else
		return $arrShards[0];
}

function os6_form_script() {
	$source= "https://cdn.onlinesucces.nl/js/efc/efc.js";
	wp_enqueue_script( "os6_form_script", $source, false, '1.0', true);
}

class os6_onlineSuccessVisitors {
	// declare globals
	var $options_name = 'online_succes_visitors_item';
	var $options_group = 'online_succes_visitors_option_option';
	var $options_page = 'online_succes_visitors';
	var $plugin_name = 'Online Succes';
	var $plugin_textdomain = 'onlineSuccessVisitors';

	// constructor
	function __construct() {
		$options = $this->os6_optionsGetOptions();
		add_filter( 'plugin_row_meta', array( &$this, 'os6_optionsSetPluginMeta' ), 10, 2 ); // add plugin page meta links
		add_action( 'admin_init', array( &$this, 'os6_optionsInit' ) ); // whitelist options page
		add_action( 'admin_menu', array( &$this, 'os6_optionsAddPage' ) ); // add link to plugin's settings page in 'settings' menu on admin menu initilization
		if ($options["form_capture_on"]==1) {
			add_action( 'wp_head', array( &$this, 'os6_setTrackingFormVars' ), 99999 ); 
			add_action( 'wp_enqueue_scripts', 'os6_form_script' );
		}
		add_action( 'wp_footer', array( &$this, 'os6_getTrackingCode' ), 99999 ); 
		register_activation_hook( __FILE__, array( &$this, 'os6_optionsCompat' ) );
	}

	// load i18n textdomain
	/*
	function loadTextDomain() {
		load_plugin_textdomain( $this->plugin_textdomain, false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'lang/' );
	}
	*/
	
	// compatability with upgrade from version <1.4
	function os6_optionsCompat() {
		$old_options = get_option( 'ssga_item' );
		if ( !$old_options ) return false;
		
		$defaults = os6_optionsGetDefaults();
		foreach( $defaults as $key => $value )
			if( !isset( $old_options[$key] ) )
				$old_options[$key] = $value;
		
		add_option( $this->options_name, $old_options, '', false );
		delete_option( 'ssga_item' );
		return true;
	}
	
	// get default plugin options
	function os6_optionsGetDefaults() { 
		$defaults = array( 
			'account' => '', 
			'insert_code' => 0,
			'form_capture_on' => 1
		);
		return $defaults;
	}
	
	function os6_optionsGetOptions() {
		$options = get_option( $this->options_name, $this->os6_optionsGetDefaults() );
		if (!isset($options["form_capture_on"]))
			$options["form_capture_on"] = 1; //DEFAULT VALUE		
		return $options;
	}
	
	// set plugin links
	function os6_optionsSetPluginMeta( $links, $file ) { 
		$plugin = plugin_basename( __FILE__ );
		if ( $file == $plugin ) { // if called for THIS plugin then:
			$newlinks = array( '<a href="options-general.php?page=' . $this->options_page . '">' . __( 'Settings', $this->plugin_textdomain ) . '</a>' ); // array of links to add
			return array_merge( $links, $newlinks ); // merge new links into existing $links
		}
	return $links; // return the $links (merged or otherwise)
	}
	
	// plugin startup
	function os6_optionsInit() { 
		register_setting( $this->options_group, $this->options_name, array( &$this, 'os6_optionsValidate' ) );
	}
	
	// create and link options page
	function os6_optionsAddPage() { 
		add_options_page( $this->plugin_name . ' ' . __( 'Settings', $this->plugin_textdomain ), __( 'Online Succes', $this->plugin_textdomain ), 'manage_options', $this->options_page, array( &$this, 'os6_optionsDrawPage' ) );
	}
	
	// sanitize and validate options input
	function os6_optionsValidate( $input ) { 
	//	$input['insert_code'] = ( $input['insert_code'] ? 1 : 0 ); 	// (checkbox) if TRUE then 1, else NULL
		if (array_key_exists('insert_code', $input)) {( $input['insert_code'] ? 1 : 0 );}else{$input['insert_code']=0;}
		$input['account'] =  wp_filter_nohtml_kses( trim($input['account']) ); // (textbox) safe text, no html
		$input['client'] =  wp_filter_nohtml_kses( trim($input['client']) ); // (textbox) safe text, no html
	//	$input['form_capture_on'] = ( $input['form_capture_on'] ? 1 : 0 ); 	// (checkbox) if TRUE then 1, else NULL
		if (array_key_exists('form_capture_on', $input)) {( $input['form_capture_on'] ? 1 : 0 );}else{$input['form_capture_on']=0;}
		return $input;
	}
	
	// display a checkbox option
	function os6_optionsDrawCheckbox( $slug, $label, $style_checked='', $style_unchecked='' ) { 
		$options = $this->os6_optionsGetOptions();
		if( !$options[$slug] ) 
			if( !empty( $style_unchecked ) ) $style = ' style="' . $style_unchecked . '"';
			else $style = '';
		else
			if( !empty( $style_checked ) ) $style = ' style="' . $style_checked . '"';
			else $style = ''; 
	?>
		 <!-- <?php _e( $label, $this->plugin_textdomain ); ?> -->
			<tr valign="top">
				<th scope="row">
					<label<?php echo $style; ?> for="<?php echo $this->options_name; ?>[<?php echo $slug; ?>]">
						<?php _e( $label, $this->plugin_textdomain ); ?>
					</label>
				</th>
				<td>
					<input name="<?php echo $this->options_name; ?>[<?php echo $slug; ?>]" type="checkbox" value="1" <?php checked( $options[$slug], 1 ); ?>/>					
				</td>
			</tr>
			
	<?php }
	
	// display the options page
	function os6_optionsDrawPage() { 
		$lang = get_locale() ;  //nl_NL, en_US
		if ($lang == "nl_NL") { //Dutch translations
			$strTxtAddTrackingCode = "Trackingcode Invoegen";
			$strTxtAddCorrectClientId = "Vul ajb een correct Account ID in.";
			$strTxtAddCorrectTrackingId = "Vul ajb een correct Tracking ID in.";
			$strTxtSaveSettings = "Instellingen opslaan";
			$strTxtDescription = "Met Online Succes kun je anonieme website bezoekers omzetten in nieuwe verkoopkansen, herken je personen op basis van een formulierinzending, volg je deze automatisch op én verhoog je de ROI van je online marketing activiteiten.";
			$strTxtTryForFree = "Probeer het 30 dagen gratis";
			$strTxtNoObligations = "je zit nergens aan vast.";
			$strTxtWantToKnowMore = "Meer weten over het instellen van deze plugin?";
			$strTxtHelpCentre = "Bekijk het artikel in ons helpcentrum";
			$strTxtFormCapture = "Formulierinzendingen automatisch opvangen";
		}
		else {
			$strTxtAddTrackingCode = "Add Trackingcode";
			$strTxtAddCorrectClientId = "Please use a correct Account ID.";
			$strTxtAddCorrectTrackingId = "Please use a correct Tracking ID.";
			$strTxtSaveSettings = "Save settings";
			$strTxtDescription = "With Online Succes you can convert anonymous website visitors into new sales opportunities, recognize people based on a form submission, follow them up automatically and increase the ROI of your online marketing activities.";
			$strTxtTryForFree = "30 days free trial";
			$strTxtNoObligations = "no obligations.";
			$strTxtWantToKnowMore = "Want to know more about the settings of this plugin?";
			$strTxtHelpCentre = "Check out the article in our helpcentre";						
			$strTxtFormCapture = "Capture form submissions automatically";
		}
		?>
		<div class="wrap">
		<div class="icon32" id="icon-options-general"><br /></div>
			<h2><?php echo $this->plugin_name . __( ' | '.$strTxtAddTrackingCode, $this->plugin_textdomain ); ?></h2>
			<form name="form1" id="form1" method="post" action="options.php">			
				<?php
				settings_fields( $this->options_group ); // nonce settings page 
				$options = $this->os6_optionsGetOptions();  //populate $options array from database 	

				if (array_key_exists('client', $options)) {$intClientId = trim($options['client']);}else{$intClientId='';}
				$strHashId = trim($options['account']);
				$strErrorClientId = "";
				$strErrorHashId = "";
				if (strlen($intClientId)>0) { 
					if ((!ctype_digit($intClientId)) || ($intClientId<1000))
						$strErrorClientId = " ".$strTxtAddCorrectClientId;
					else
						$strErrorClientId = " Ok";
				}
				if (strlen($strHashId)>0) {
					if (strlen($strHashId)<=5)
						$strErrorHashId = " ".$strTxtAddCorrectTrackingId;				
					else
						$strErrorHashId = " Ok";				
				}
				?>				
				<!-- Description -->
				<p style="font-size:14px;"><?php 
					printf( __( $strTxtDescription.' <a href=\'https://app.onlinesucces.nl/redir/?url=https://app.onlinesucces.nl/signup&source=os-wp-plugin\' target=\'_blank\'>'.$strTxtTryForFree.'</a>, '.$strTxtNoObligations.'

<p>'.$strTxtWantToKnowMore.' <a href=\'https://help.onlinesucces.nl/articles/1133883-hoe-installeer-je-het-online-succes-script-op-je-wordpress-website\' target=\'_blank\'>'.$strTxtHelpCentre.'</a>.', $this->plugin_textdomain )); ?></p>
				
				<table class="form-table">
	
				
					<tr valign="top"><th scope="row"><label for="<?php echo $this->options_name; ?>[client]"><?php _e( 'Account ID', $this->plugin_textdomain ); ?>: </label></th>
						<td>
							<input type="text" name="<?php echo $this->options_name; ?>[client]" value="<?php echo $intClientId; ?>" style="width:100px;" maxlength="20" />
							<?php
							if (strlen($strErrorClientId)>0)
								echo " ".$strErrorClientId;
							?>
						</td>
					</tr>


					<tr valign="top"><th scope="row"><label for="<?php echo $this->options_name; ?>[account]"><?php _e( 'Tracking ID', $this->plugin_textdomain ); ?>: </label></th>
						<td>
							<input type="text" name="<?php echo $this->options_name; ?>[account]" value="<?php echo $strHashId; ?>" style="width:400px;" maxlength="100" />
							<?php
							if (strlen($strErrorHashId)>0)
								echo " ".$strErrorHashId;
							?>							
						</td>
					</tr>

				
				<?php $this->os6_optionsDrawCheckbox( 'form_capture_on', $strTxtFormCapture, '', '' ); ?>			

				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( $strTxtSaveSettings, $this->plugin_textdomain ) ?>" />
				</p>
			</form>
		</div>
		
		<?php
	}


	function os6_setTrackingFormVars() { 
		$options = $this->os6_optionsGetOptions();
		$arrShard = os6_fnGetShardDetails($options['client']-1000);
		$form_capture_script = sprintf( '
			<script type="text/javascript" id="myformscript" async data-api="%1$s" data-client="'.$options['client'].'"></script>', 
			$options['account'], 
			$arrShard['tracker_subdomain'] 
		); 		

		// build tracking code
		$strHashId = trim($options['account']);
		if ($options["form_capture_on"]==1) {
			if (strlen($options['client'])>0) { 
				if ((!ctype_digit($options['client'])) || ($options['client']<1000))
					$strErrorClientId = " ".$strTxtAddCorrectClientId;
				else
					echo $form_capture_script;
			}						
		}		
	}

	// 	the Online Success Visitors html tracking code to be inserted in header/footer
	function os6_getTrackingCode() { 
		$options = $this->os6_optionsGetOptions();
		if ($options['client']>0)
			$arrShard = os6_fnGetShardDetails($options['client']-1000);
		if (strlen($arrShard['tracker_subdomain'])==0)
			$arrShard['tracker_subdomain'] = "connect.onlinesucces.nl";
	
	// core tracking code
	$core = sprintf( '<script type="text/javascript">
	var image = document.createElement("img");
	image.src =\'//%2$s/?i=%1$s&ts=\'+new Date().getTime()+\'&f=\'+encodeURIComponent(document.location.href)+\'&r=\'+encodeURIComponent(document.referrer)+\'&t=\'+encodeURIComponent(document.title);
	</script>', 
		$options['account'],
		$arrShard['tracker_subdomain'] 
	); 

	// build tracking code
	$strHashId = trim($options['account']);
	if (strlen($strHashId)>5) {
		echo $core ; 
	}
	/*
	$form_capture_script = '<script type="text/javascript" src="https://cdn.onlinesucces.nl/js/efc/efc.js"></script>'; 	
	
	if ($options["form_capture_on"]==1) {
			if (strlen($options['client'])>0) { 
				if ((!ctype_digit($options['client'])) || ($options['client']<1000))
					$strErrorClientId = " ".$strTxtAddCorrectClientId;
				else
					echo $form_capture_script;
			}						
		}
	*/
	}		
} // end class
endif; // end collision check

$onlineSuccessVisitors_instance = new os6_onlineSuccessVisitors;