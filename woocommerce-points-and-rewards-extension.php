<?php
/**
 * Plugin Name: WooCommerce Points and Rewards Refferal Extension
 * Depends: WooCommerce Points and Rewards
 * Description: This plugin adds referal functionality to the WooCommerce Points and Rewards plugin.
 * Version: 1.0.0
 * Author: Bradley Baago
 * Author URI: https://bradtech.ca
 */
 
//Adds a setting for awarding points to a a referee. 
add_filter( 'wc_points_rewards_action_settings', 'wdm_points_rewards_friend_referral_referee_settings' );
function wdm_points_rewards_friend_referral_referee_settings( $settings ) {
  $settings[] = array(
    'title'    => __( 'Points earned for being refered by a friend.' ),
    'desc_tip' => __( 'Enter the amount of points earned when someone signs up from a referal link' ),
    'id'       => 'wdm_points_rewards_friend_referral_referee',
  );
  return $settings;
}
//On user signup if there is a referal award the user points.
add_action('user_register', 'wdm_points_rewards_friend_referral_referee_action' );
function wdm_points_rewards_friend_referral_referee_action( $userid ) {
    if ( isset($_GET['raf']) ){
      // get the points associated with friend referral action
      $points = get_option( 'wdm_points_rewards_friend_referral_referee' );
      if ( ! empty( $points ) ) {
        WC_Points_Rewards_Manager::increase_points( $userid, $points, 'get-referal-success');
      }
      //Save referal id and redemption status.
      add_user_meta( $userid, 'wdm_store_referral', $_GET['raf'] );
      add_user_meta( $userid, 'wdm_store_referral_used', false );
      // get user email with the refid
      $user = reset(
      get_users(
	array(
	'meta_key' => 'wdm_store_referral_id',
	'meta_value' => $_GET['raf'],
	'number' => 1,
	'count_total' => false
	)
      )
      );
      //Notify the referer of the registration
      wp_mail( $user->user_email, "Referal Status", "A user you refered: ".get_userdata($userid)->user_email." has registered. They have received ".$points." points for doing so.");
    }
    //Create referal id for user.
    add_user_meta( $userid, 'wdm_store_referral_id', rand().rand().rand().rand() );
}
//Adds a setting for awarding points to a a referer. 
add_filter( 'wc_points_rewards_action_settings', 'wdm_points_rewards_friend_referral_referer_settings' );
function wdm_points_rewards_friend_referral_referer_settings( $settings ) {
  $settings[] = array(
    'title'    => __( "Points earned for refering a friend after friend's purchase." ),
    'desc_tip' => __( 'Enter the amount of points earned when refered friend makes a purchase.' ),
    'id'       => 'wdm_points_rewards_friend_referral_referer',
  );
  return $settings;
}
//On user purchase award referer points
add_action('woocommerce_checkout_order_processed', 'wdm_points_rewards_friend_referral_referer_action');
function wdm_points_rewards_friend_referral_referer_action()
{
  if (null !== get_user_meta( get_current_user_id(), 'wdm_store_referral', true ) && get_user_meta( get_current_user_id(), 'wdm_store_referral_used', true ) == false) {
    $refid = get_user_meta( get_current_user_id(), 'wdm_store_referral', true );
    // get user with the refid
    $user = reset(
	  get_users(
	    array(
	    'meta_key' => 'wdm_store_referral_id',
	    'meta_value' => $refid,
	    'number' => 1,
	    'count_total' => false
	    )
	  )
	  );
    $userid = $user->ID;
    //Get points to Rewards
    $points = get_option( 'wdm_points_rewards_friend_referral_referer' );
    // award the points using WC_Points_Rewards_Manager
    WC_Points_Rewards_Manager::increase_points( $userid, $points, 'send-referal-success');
    // Set rewarded to true.
    update_user_meta( get_current_user_id(), 'wdm_store_referral_used', true );
    
    //Notify the referer of the registration purchase.
    wp_mail( $user->user_email, "Referal Status", "A user you refered: ".get_currentuserinfo()->user_email."has made a purchase. You have been rewarded ".$points." points.");
  }
}
//logging function
add_filter('wc_points_rewards_event_description', 'add_points_rewards_newsletter_action_event_description', 10, 3 );
function add_points_rewards_newsletter_action_event_description( $event_description, $event_type, $event ) {
    $points_label = get_option( 'wc_points_rewards_points_label' );
    switch ( $event_type ) {
      case 'get-referal-success': $event_description = sprintf( __( '%s earned for being refered by a friend' ), $points_label ); break;
      case 'send-referal-success': $event_description = sprintf( __( '%s earned for refering a friend' ), $points_label ); break;
    }
    return $event_description;
}
//The referal form
function html_form_code() {
    echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post">';
    echo '<p>';
    echo 'Referee Email<br />';
    echo '<input type="email" name="cf-email" value="' . ( isset( $_POST["cf-email"] ) ? esc_attr( $_POST["cf-email"] ) : '' ) . '" size="40" />';
    echo '</p>';
    echo '<p><input type="submit" name="cf-submitted" value="Send"/></p>';
    echo '</form>';
}
//The send email function for the form.
function deliver_mail() {

    // if the submit button is clicked, send the email
    if ( isset( $_POST['cf-submitted'] ) ) {
	
	//Get the user id
	$user_id = get_current_user_id();
	
        // sanitize email.
        $email   = sanitize_email( $_POST["cf-email"] );
        
        //Get refid. Create one if missing.
        $refid = null;
        if(null !== get_user_meta( get_current_user_id(), 'wdm_store_referral_id', true ) && get_user_meta( get_current_user_id(), 'wdm_store_referral_id', true ) != "")
        {
	  $refid = get_user_meta( get_current_user_id(), 'wdm_store_referral_id', true );
        }
        elseif(get_user_meta( get_current_user_id(), 'wdm_store_referral_id', true ) == "")
        {
	  $refid = rand().rand().rand().rand();
	  update_user_meta( $user_id, 'wdm_store_referral_id', $refid );
        }
        else
        {
	  $refid = rand().rand().rand().rand();
	  add_user_meta( $user_id, 'wdm_store_referral_id', $refid );
        }
        $message = wp_get_current_user()->user_email." has refered you to ".$_SERVER['SERVER_NAME'].". To signup click here : ".$_SERVER['SERVER_NAME']."/my-account/?raf=".$refid;

        // If email has been process for sending, display a success message
        if ( wp_mail( $email, "You have been refered to ".$_SERVER['SERVER_NAME'].".", $message, $headers ) ) {
            echo '<div>';
            echo '<p>Refferal Success.</p>';
            echo '</div>';
        } else {
            echo 'An unexpected error occurred';
        }
    }
}
//Shortcode function
function cf_shortcode() {
    ob_start();
    deliver_mail();
    html_form_code();

    return ob_get_clean();
}
//Shorcode
add_shortcode( 'woocommerce_refferal_form', 'cf_shortcode' );