<?php

if ( !class_exists( 'WPSE_The_Events_Calendar_Organizers' ) ) {
    class WPSE_The_Events_Calendar_Organizers extends WPSE_Sheet_Factory {
        function __construct() {
            $allowed_columns = array();
            $allowed_columns = array(
                'ID',
                'post_title',
                'post_content',
                'post_status',
                'post_name',
                'view_post'
            );
            parent::__construct( array(
                'fs_object'       => wpsee_fs(),
                'post_type'       => array('tribe_organizer'),
                'post_type_label' => array('Organizers'),
                'toolbars'        => array($this, 'get_toolbars'),
                'allowed_columns' => $allowed_columns,
            ) );
        }

        function get_toolbars() {
            $toolbars = array();
            $post_types = array(
                'tribe_events' => 'Events',
                'tribe_venue'  => 'Venues',
            );
            foreach ( $post_types as $post_type => $post_type_label ) {
                $toolbars['bulk_edit_' . $post_type] = array(
                    'type'         => 'button',
                    'help_tooltip' => __( 'Open spreadsheet.', vgse_events()->textname ),
                    'content'      => sprintf( __( 'Edit %s', vgse_events()->textname ), esc_html( $post_type_label ) ),
                    'icon'         => 'fa fa-edit',
                    'url'          => VGSE()->helpers->get_editor_url( $post_type ),
                );
            }
            return $toolbars;
        }

    }

    new WPSE_The_Events_Calendar_Organizers();
}