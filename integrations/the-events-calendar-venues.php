<?php

if ( !class_exists( 'WPSE_The_Events_Calendar_Venues' ) ) {
    class WPSE_The_Events_Calendar_Venues extends WPSE_Sheet_Factory {
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
                'fs_object'          => wpsee_fs(),
                'post_type'          => array('tribe_venue'),
                'post_type_label'    => array('Venues'),
                'serialized_columns' => array(),
                'columns'            => array($this, 'get_columns'),
                'toolbars'           => array($this, 'get_toolbars'),
                'allowed_columns'    => $allowed_columns,
            ) );
            add_filter( 'vg_sheet_editor/provider/post/get_items_args', array($this, 'disable_date_filter') );
        }

        function get_toolbars() {
            $toolbars = array();
            $post_types = array(
                'tribe_events'    => 'Events',
                'tribe_organizer' => 'Organizers',
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

        function get_columns() {
            $columns = array();
            return $columns;
        }

        function disable_date_filter( $wp_query ) {
            if ( in_array( $wp_query['post_type'], $this->post_type ) ) {
                $wp_query['tribe_remove_date_filters'] = true;
            }
            return $wp_query;
        }

    }

    new WPSE_The_Events_Calendar_Venues();
}