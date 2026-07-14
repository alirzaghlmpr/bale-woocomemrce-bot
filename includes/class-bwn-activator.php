<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BWN_Activator {
    /**
     * Set up default options upon plugin activation.
     */
    public static function activate() {
        add_option( BWN_OPTIONS_KEY, array( 
            'bot_token'        => '', 
            'chat_id'          => '', 
            'message_template' => '' 
        ) );
        add_option( 'bwn_log', array() );
    }
}
