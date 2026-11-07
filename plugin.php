<?php
/*
Plugin Name: Asyntai AI Chatbot
Plugin URI: https://asyntai.com/documentation/installation/yourls/
Description: Adds the Asyntai AI chatbot to the public pages of your YOURLS site, such as custom pages, your front page and public link stats pages.
Version: 1.0.0
Author: Asyntai
Author URI: https://asyntai.com
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'ASYNTAI_AI_CHATBOT_SCRIPT_URL', 'https://widget.asyntai.com/static/js/chat-widget.js' );

yourls_add_action( 'plugins_loaded', 'asyntai_ai_chatbot_add_page' );
yourls_add_action( 'html_footer', 'asyntai_ai_chatbot_print_script' );

function asyntai_ai_chatbot_add_page() {
    yourls_register_plugin_page( 'asyntai_ai_chatbot', 'Asyntai AI Chatbot', 'asyntai_ai_chatbot_do_page' );
}

/**
 * Returns a valid widget ID, or an empty string. The owner may paste the
 * whole embed code from the Asyntai dashboard, so the ID is taken from
 * the data-asyntai-id attribute when one is present.
 */
function asyntai_ai_chatbot_clean_widget_id( $value ) {
    $value = trim( (string) $value );

    if( preg_match( '/data-asyntai-id\s*=\s*["\']?([A-Za-z0-9_-]+)/i', $value, $match ) ) {
        $value = $match[1];
    }

    $value = trim( $value, "=;\"' \t\r\n" );

    return preg_match( '/^[A-Za-z0-9_-]{4,64}$/', $value ) ? $value : '';
}

/**
 * Tells if the option value is on. A missing option means on.
 */
function asyntai_ai_chatbot_is_enabled() {
    return yourls_get_option( 'asyntai_ai_chatbot_enabled', '1' ) === '1';
}

/**
 * Returns the pages that must not show the chatbot, from the comma separated option.
 */
function asyntai_ai_chatbot_excluded_pages() {
    $pages = array();

    foreach( explode( ',', (string) yourls_get_option( 'asyntai_ai_chatbot_excluded_pages', '' ) ) as $page ) {
        $page = strtolower( trim( $page, " \t\r\n/" ) );

        if( $page !== '' ) {
            $pages[] = $page;
        }
    }

    return $pages;
}

/**
 * Returns the script tag for a page, or an empty string when nothing must load.
 */
function asyntai_ai_chatbot_script_tag( $context, $request_uri ) {
    // Never in the admin area, and never on the login form of private stats pages.
    if( yourls_is_admin() || $context === 'login' ) {
        return '';
    }

    if( !asyntai_ai_chatbot_is_enabled() ) {
        return '';
    }

    $widget_id = asyntai_ai_chatbot_clean_widget_id( yourls_get_option( 'asyntai_ai_chatbot_widget_id', '' ) );

    if( $widget_id === '' ) {
        return '';
    }

    $path = (string) parse_url( (string) $request_uri, PHP_URL_PATH );
    $last = strtolower( rawurldecode( basename( $path ) ) );

    foreach( asyntai_ai_chatbot_excluded_pages() as $page ) {
        if( $page === $last || $page === strtolower( (string) $context ) || ( $page === 'stats' && $context === 'infos' ) ) {
            return '';
        }
    }

    return '<script src="' . ASYNTAI_AI_CHATBOT_SCRIPT_URL . '" data-asyntai-id="' . yourls_esc_attr( $widget_id ) . '" async></script>';
}

function asyntai_ai_chatbot_print_script() {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

    echo asyntai_ai_chatbot_script_tag( yourls_get_html_context(), $request_uri );
}

/**
 * Saves the form. Returns an error message, or an empty string.
 */
function asyntai_ai_chatbot_save() {
    yourls_verify_nonce( 'asyntai_ai_chatbot' );

    $raw       = isset( $_POST['asyntai_ai_chatbot_widget_id'] ) ? (string) $_POST['asyntai_ai_chatbot_widget_id'] : '';
    $excluded  = isset( $_POST['asyntai_ai_chatbot_excluded_pages'] ) ? (string) $_POST['asyntai_ai_chatbot_excluded_pages'] : '';
    $widget_id = asyntai_ai_chatbot_clean_widget_id( $raw );

    yourls_update_option( 'asyntai_ai_chatbot_enabled', isset( $_POST['asyntai_ai_chatbot_enabled'] ) ? '1' : '0' );
    yourls_update_option( 'asyntai_ai_chatbot_excluded_pages', trim( $excluded ) );

    if( trim( $raw ) !== '' && $widget_id === '' ) {
        return 'This Widget ID is not valid. Copy it from the Install page of your Asyntai dashboard, for example asyntai_1a2b3c4d5e6f.';
    }

    yourls_update_option( 'asyntai_ai_chatbot_widget_id', $widget_id );

    return '';
}

// Display admin page
function asyntai_ai_chatbot_do_page() {
    $error = '';
    $saved = false;

    if( isset( $_POST['asyntai_ai_chatbot_save'] ) ) {
        $error = asyntai_ai_chatbot_save();
        $saved = $error === '';
    }

    $widget_id = (string) yourls_get_option( 'asyntai_ai_chatbot_widget_id', '' );
    $excluded  = (string) yourls_get_option( 'asyntai_ai_chatbot_excluded_pages', '' );
    $enabled   = asyntai_ai_chatbot_is_enabled();

    if( $widget_id === '' ) {
        $status = 'The chatbot does not show yet. Add your Widget ID to switch it on.';
    } elseif( !$enabled ) {
        $status = 'The chatbot is switched off.';
    } else {
        $status = 'The chatbot shows on your public pages.';
    }

    echo '<h2>Asyntai AI Chatbot</h2>';

    if( $saved ) {
        echo '<p><strong>Asyntai settings saved.</strong></p>';
    }

    if( $error !== '' ) {
        echo '<p class="error"><strong>' . yourls_esc_html( $error ) . '</strong></p>';
    }

    echo '<p><strong>Status:</strong> ' . yourls_esc_html( $status ) . '</p>';
    echo '<form method="post">';
    yourls_nonce_field( 'asyntai_ai_chatbot' );
    echo '<p><label for="asyntai_ai_chatbot_widget_id"><strong>Widget ID</strong></label><br>';
    echo '<input type="text" class="text" size="50" id="asyntai_ai_chatbot_widget_id" name="asyntai_ai_chatbot_widget_id" value="' . yourls_esc_attr( $widget_id ) . '"></p>';
    echo '<p>No account yet? Create one free at <a href="https://asyntai.com" target="_blank" rel="noopener">asyntai.com</a>. Then open the Install page of your <a href="https://asyntai.com/dashboard" target="_blank" rel="noopener">Asyntai dashboard</a> and copy your Widget ID.</p>';
    echo '<p><label><input type="checkbox" id="asyntai_ai_chatbot_enabled" name="asyntai_ai_chatbot_enabled" value="1"' . ( $enabled ? ' checked' : '' ) . '> Show the chatbot on the site</label></p>';
    echo '<p><label for="asyntai_ai_chatbot_excluded_pages"><strong>Pages without the chatbot</strong></label><br>';
    echo '<input type="text" class="text" size="50" id="asyntai_ai_chatbot_excluded_pages" name="asyntai_ai_chatbot_excluded_pages" value="' . yourls_esc_attr( $excluded ) . '"></p>';
    echo '<p>Optional. Page names, separated by commas, for example: about, contact. Use stats for all link stats pages. Leave empty to show the chatbot on every public page.</p>';
    echo '<p>The chatbot shows on pages in the user/pages folder, on any front page that calls yourls_html_footer(), and on link stats pages when they are public. Admin pages and login forms never show it. Short links still redirect directly.</p>';
    echo '<p><input type="submit" class="button primary" name="asyntai_ai_chatbot_save" value="Save Changes"></p>';
    echo '</form>';
}
