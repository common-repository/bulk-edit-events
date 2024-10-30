<?php

defined( 'ABSPATH' ) || exit;
if ( !function_exists( 'wpsee_fs' ) ) {
    // Create a helper function for easy SDK access.
    function wpsee_fs() {
        global $wpsee_fs;
        if ( !isset( $wpsee_fs ) ) {
            if ( !defined( 'WP_FS__PRODUCT_2830_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_2830_MULTISITE', true );
            }
            $wpsee_fs = fs_dynamic_init( array(
                'id'             => '2830',
                'slug'           => 'bulk-edit-events',
                'type'           => 'plugin',
                'public_key'     => 'pk_8708c85922d2d3fa1e448b9a68298',
                'is_premium'     => false,
                'has_addons'     => false,
                'has_paid_plans' => true,
                'menu'           => array(
                    'slug'       => 'wpsee_welcome_page',
                    'first-path' => 'admin.php?page=wpsee_welcome_page',
                    'support'    => false,
                ),
                'is_live'        => true,
            ) );
        }
        return $wpsee_fs;
    }

    // Init Freemius.
    wpsee_fs();
    // Signal that SDK was initiated.
    do_action( 'wpsee_fs_loaded' );
}