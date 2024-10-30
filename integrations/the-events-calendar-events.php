<?php

use TEC\Events\Custom_Tables\V1\Models\Builder;
use TEC\Events\Custom_Tables\V1\Models\Event;
use TEC\Events\Custom_Tables\V1\Models\Occurrence;
use Tribe__Events__Main as TEC;
if ( !class_exists( 'WPSE_The_Events_Calendar' ) ) {
    class WPSE_The_Events_Calendar extends WPSE_Sheet_Factory {
        function __construct() {
            $allowed_columns = array();
            $allowed_columns = array(
                'ID',
                'post_title',
                'post_content',
                'post_status',
                '_EventStartDate_date',
                '_EventEndDate_date',
                '_EventStartDate_time',
                '_EventEndDate_time',
                'post_name',
                'view_post'
            );
            parent::__construct( array(
                'fs_object'          => wpsee_fs(),
                'post_type'          => array('tribe_events'),
                'post_type_label'    => array('Events'),
                'serialized_columns' => array(),
                'columns'            => array($this, 'get_columns'),
                'toolbars'           => array($this, 'get_toolbars'),
                'allowed_columns'    => $allowed_columns,
                'remove_columns'     => array(
                    '_EventStartDateUTC',
                    '_EventEndDateUTC',
                    '_EventDuration',
                    '_EventTimezone',
                    '_EventTimezoneAbbr',
                    '_EventOrganizerID',
                    '_EventEndDate',
                    '_EventStartDate'
                ),
            ) );
            add_filter( 'vg_sheet_editor/provider/post/get_items_args', array($this, 'disable_date_filter') );
            add_filter(
                'vg_sheet_editor/provider/post/update_item_meta',
                array($this, 'filter_cell_data_for_saving'),
                10,
                3
            );
            add_filter(
                'vg_sheet_editor/provider/post/get_item_meta',
                array($this, 'filter_cell_data_for_readings'),
                10,
                5
            );
            add_action(
                'vg_sheet_editor/save_rows/after_saving_post',
                array($this, 'sync_utc_dates'),
                10,
                4
            );
            add_action(
                'vg_sheet_editor/add_new_posts/after_all_posts_created',
                array($this, 'add_required_meta'),
                10,
                2
            );
            add_filter(
                'vg_sheet_editor/columns/blacklisted_columns',
                array($this, 'disable_private_columns'),
                10,
                2
            );
            add_action(
                'vg_sheet_editor/save_rows/before_saving_rows',
                array($this, 'disable_known_range_rebuild'),
                10,
                2
            );
            add_action(
                'vg_sheet_editor/save_rows/after_saving_rows',
                array($this, 'rebuild_known_range'),
                10,
                2
            );
            add_filter(
                'vg_sheet_editor/load_rows/wp_query_args',
                array($this, 'bypass_tec_query_filters'),
                10,
                2
            );
        }

        function bypass_tec_query_filters( $qry, $settings ) {
            if ( $settings['post_type'] === 'tribe_events' ) {
                $qry['tec_events_ignore'] = true;
            }
            return $qry;
        }

        public function update_tec_custom_table( $post_id ) {
            // Make sure to update the real thing.
            if ( version_compare( Tribe__Events__Main::VERSION, '6.0.0' ) < 0 ) {
                return;
            }
            $post_id = Occurrence::normalize_id( $post_id );
            if ( TEC::POSTTYPE !== get_post_type( $post_id ) ) {
                return false;
            }
            $event_data = Event::data_from_post( $post_id );
            $upsert = Event::upsert( array('post_id'), $event_data );
            if ( $upsert === false ) {
                // At this stage the data might just be missing: it's fine.
                return false;
            }
            // Show when an event is updated versus inserted
            if ( $upsert === Builder::UPSERT_DID_INSERT ) {
                /**
                 * When we have created a new event, fire this action with the post ID.
                 *
                 * @since 6.0.0
                 *
                 * @param numeric $post_id The event post ID.
                 */
                do_action( 'tec_events_custom_tables_v1_after_insert_event', $post_id );
            } else {
                /**
                 * When we have updated an existing event, fire this action with the post ID.
                 *
                 * @since 6.0.0
                 *
                 * @param numeric $post_id The event post ID.
                 */
                do_action( 'tec_events_custom_tables_v1_after_update_event', $post_id );
            }
            $event = Event::find( $post_id, 'post_id' );
            if ( !$event instanceof Event ) {
                return false;
            }
            try {
                $occurrences = $event->occurrences();
                $occurrences->save_occurrences();
            } catch ( Exception $e ) {
                return false;
            }
            return true;
        }

        function sync_utc_dates(
            $post_id,
            $item,
            $data,
            $post_type
        ) {
            if ( $post_type !== 'tribe_events' ) {
                return;
            }
            $row_keys = implode( ',', array_keys( $item ) );
            if ( preg_match( '/EventStartDate|EventEndDate/', $row_keys ) ) {
                $site_timezone = Tribe__Timezones::wp_timezone_string();
                $local_start_time = tribe_get_start_date( $post_id, true, Tribe__Date_Utils::DBDATETIMEFORMAT );
                $utc_start_time = Tribe__Timezones::to_utc( $local_start_time, $site_timezone );
                $local_end_time = tribe_get_end_date( $post_id, true, Tribe__Date_Utils::DBDATETIMEFORMAT );
                $utc_end_time = Tribe__Timezones::to_utc( $local_end_time, $site_timezone );
                // The abbreviation needs to be calculated per event as it can vary according to the actual date
                $site_timezone_abbr = Tribe__Timezones::wp_timezone_abbr( $local_start_time );
                update_post_meta( $post_id, '_EventTimezone', $site_timezone );
                update_post_meta( $post_id, '_EventTimezoneAbbr', $site_timezone_abbr );
                update_post_meta( $post_id, '_EventStartDateUTC', $utc_start_time );
                update_post_meta( $post_id, '_EventEndDateUTC', $utc_end_time );
                if ( !empty( $utc_start_time ) && !empty( $utc_end_time ) ) {
                    update_post_meta( $post_id, '_EventDuration', strtotime( $utc_end_time ) - strtotime( $utc_start_time ) );
                }
            }
            $this->update_tec_custom_table( $post_id );
        }

        function modify_values_for_export( $cleaned_rows, $clean_data, $wp_query_args ) {
            if ( in_array( $wp_query_args['post_type'], $this->post_type ) ) {
                $keys = serialize( array_keys( current( $cleaned_rows ) ) );
                if ( strpos( $keys, '_time' ) !== false && !empty( VGSE()->options['events_export_friendly_times'] ) ) {
                    foreach ( $cleaned_rows as $row_index => $row ) {
                        if ( isset( $row['_EventStartDate_time'] ) ) {
                            $row['_EventStartDate_time'] = date( 'h:i A', strtotime( $row['_EventStartDate_time'] ) );
                            $row['_EventStartDate_time'] = str_replace( '12:00 AM', '', $row['_EventStartDate_time'] );
                        }
                        if ( isset( $row['_EventEndDate_time'] ) ) {
                            $row['_EventEndDate_time'] = date( 'h:i A', strtotime( $row['_EventEndDate_time'] ) );
                            $row['_EventEndDate_time'] = str_replace( '12:00 AM', '', $row['_EventEndDate_time'] );
                        }
                        $cleaned_rows[$row_index] = $row;
                    }
                }
                if ( preg_match( '/post_content|post_excerpt/', $keys ) && !empty( VGSE()->options['events_export_plain_text'] ) ) {
                    foreach ( $cleaned_rows as $row_index => $row ) {
                        if ( isset( $row['post_content'] ) ) {
                            $row['post_content'] = wp_strip_all_tags( $row['post_content'] );
                        }
                        if ( isset( $row['post_excerpt'] ) ) {
                            $row['post_excerpt'] = wp_strip_all_tags( $row['post_excerpt'] );
                        }
                        $cleaned_rows[$row_index] = $row;
                    }
                }
            }
            return $cleaned_rows;
        }

        /**
         * Add fields to options page
         * @param array $sections
         * @return array
         */
        function add_settings_page_options( $sections ) {
            if ( !isset( $sections['events'] ) ) {
                $sections['events'] = array(
                    'icon'   => 'el-icon-cogs',
                    'title'  => __( 'Events', VGSE()->textname ),
                    'fields' => array(),
                );
            }
            $sections['events']['fields'][] = array(
                'id'      => 'events_export_friendly_times',
                'type'    => 'switch',
                'title'   => __( 'Export times in friendly format?', VGSE()->textname ),
                'desc'    => __( 'By default, we export times in format H:i:s. Activate this option to export with format like 7:00 pm.', VGSE()->textname ),
                'default' => false,
            );
            $sections['events']['fields'][] = array(
                'id'      => 'events_export_plain_text',
                'type'    => 'switch',
                'title'   => __( 'Export content as plain text?', VGSE()->textname ),
                'desc'    => __( 'By default, we export the content as html, activate this option to strip the html and export as plain text.', VGSE()->textname ),
                'default' => false,
            );
            return $sections;
        }

        /**
         * The Events Calendar plugin rebuilds the date ranges for all events every time
         * an event is created or updated which causes serious performance issues.
         * We disable it here and we trigger the rebuild manually after all the events are saved
         *
         * @param array $data
         * @param string $post_type
         */
        function disable_known_range_rebuild( $data, $post_type ) {
            if ( in_array( $post_type, $this->post_type ) ) {
                add_filter( 'tribe_events_rebuild_known_range', '__return_true' );
            }
        }

        function rebuild_known_range( $data, $post_type ) {
            if ( in_array( $post_type, $this->post_type ) ) {
                Tribe__Events__Dates__Known_Range::instance()->rebuild_known_range();
            }
        }

        function disable_private_columns( $blacklisted_columns, $provider ) {
            $blacklisted_columns = array_merge( $blacklisted_columns, array('_tribe_modified_fields') );
            return $blacklisted_columns;
        }

        function add_required_meta( $new_posts_ids, $post_type ) {
            if ( !in_array( $post_type, $this->post_type ) ) {
                return $new_posts_ids;
            }
            if ( !empty( $new_posts_ids ) ) {
                foreach ( $new_posts_ids as $post_id ) {
                    update_post_meta( $post_id, '_EventStartDate', '' );
                }
            }
            return $new_posts_ids;
        }

        function get_toolbars() {
            $post_types = array(
                'tribe_venue'     => 'Venues',
                'tribe_organizer' => 'Organizers',
            );
            $toolbars = array();
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

        function filter_cell_data_for_readings(
            $value,
            $id,
            $key,
            $single,
            $context
        ) {
            if ( $context !== 'read' || !in_array( get_post_type( $id ), $this->post_type ) ) {
                return $value;
            }
            if ( $key === '_EventStartDate_date' || $key === '_EventEndDate_date' ) {
                $meta_key = str_replace( '_date', '', $key );
                $existing_date_time = VGSE()->helpers->get_current_provider()->get_item_meta( $id, $meta_key, true );
                if ( !empty( $existing_date_time ) ) {
                    $date_parts = explode( ' ', $existing_date_time );
                    $value = current( $date_parts );
                }
            }
            if ( $key === '_EventStartDate_time' || $key === '_EventEndDate_time' ) {
                $meta_key = str_replace( '_time', '', $key );
                $existing_date_time = VGSE()->helpers->get_current_provider()->get_item_meta( $id, $meta_key, true );
                if ( !empty( $existing_date_time ) ) {
                    $date_parts = explode( ' ', $existing_date_time );
                    $value = end( $date_parts );
                }
            }
            if ( strpos( $key, '_EventOrganizerID' ) !== false ) {
                $organizer_number = (int) str_replace( '_EventOrganizerID', '', $key ) - 1;
                $values = get_post_meta( $id, '_EventOrganizerID' );
                $value = ( isset( $values[$organizer_number] ) ? $values[$organizer_number] : '' );
            }
            if ( (in_array( $key, array('_EventVenueID') ) || strpos( $key, '_EventOrganizerID' ) !== false) && $value ) {
                $value = get_the_title( $value );
            }
            return $value;
        }

        function filter_cell_data_for_saving( $new_value, $id, $key ) {
            global $wpdb;
            if ( !in_array( get_post_type( $id ), $this->post_type ) ) {
                return $new_value;
            }
            if ( in_array( $key, array('_EventVenueID') ) ) {
                $venue_id = (int) VGSE()->data_helpers->get_post_id_from_title( $new_value, 'tribe_venue' );
                $new_value = (int) $venue_id;
            }
            if ( strpos( $key, '_EventOrganizerID' ) !== false ) {
                $organizer_number = (int) str_replace( '_EventOrganizerID', '', $key ) - 1;
                $organizer = get_post_meta( $id, '_EventOrganizerID' );
                if ( empty( $organizer ) ) {
                    $organizer = array();
                }
                if ( array_key_exists( $organizer_number, $organizer ) ) {
                    $wpdb->update(
                        $wpdb->postmeta,
                        array(
                            'meta_value' => VGSE()->data_helpers->get_post_id_from_title( $new_value, 'tribe_organizer' ),
                        ),
                        array(
                            'meta_value' => $organizer[$organizer_number],
                            'meta_key'   => '_EventOrganizerID',
                            'post_id'    => $id,
                        ),
                        array('%s'),
                        array('%s', '%s', '%d')
                    );
                } else {
                    add_post_meta( $id, '_EventOrganizerID', VGSE()->data_helpers->get_post_id_from_title( $new_value, 'tribe_organizer' ) );
                }
            }
            return $new_value;
        }

        function save_event_date(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        ) {
            if ( !preg_match( '/\\d{4}-\\d{2}-\\d{2}/', $data_to_save ) ) {
                throw new Exception(__( 'The start date and end date must use the format YYYY-MM-DD. For example, 2021-12-31', VGSE()->textname ), E_USER_ERROR);
            }
            $meta_key = str_replace( '_date', '', $cell_key );
            $existing_date_time = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post_id,
                $meta_key,
                true,
                'save',
                true
            );
            if ( empty( $existing_date_time ) || !preg_match( '/\\d{2}\\:\\d{2}\\:\\d{2}/', $existing_date_time ) ) {
                $time = '00:00:00';
            } else {
                $date_parts = explode( ' ', $existing_date_time );
                $time = end( $date_parts );
            }
            update_post_meta( $post_id, $meta_key, $data_to_save . ' ' . $time );
        }

        function save_event_time(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        ) {
            if ( !empty( $data_to_save ) ) {
                $data_to_save = preg_replace( '/^(\\d{1})\\:(\\d{2}\\:\\d{2})/', '0$1:$2', $data_to_save );
            }
            if ( !preg_match( '/\\d{2}\\:\\d{2}\\:\\d{2}/', $data_to_save ) ) {
                throw new Exception(__( 'The start time and end time must use the format HH:mm:ss. For example, 08:00:00', VGSE()->textname ), E_USER_ERROR);
            }
            $meta_key = str_replace( '_time', '', $cell_key );
            $existing_date_time = VGSE()->helpers->get_current_provider()->get_item_meta(
                $post_id,
                $meta_key,
                true,
                'save',
                true
            );
            if ( empty( $existing_date_time ) ) {
                $date = date( 'Y-m-d' );
            } else {
                $date_parts = explode( ' ', $existing_date_time );
                $date = current( $date_parts );
            }
            update_post_meta( $post_id, $meta_key, $date . ' ' . $data_to_save );
        }

        function get_columns() {
            $columns = array();
            $columns['_EventStartDate_date'] = array(
                'data_type'           => 'meta_data',
                'title'               => __( 'Start date', vgse_events()->textname ),
                'formatted'           => array(
                    'data'             => '_EventStartDate_date',
                    'type'             => 'date',
                    'dateFormatPhp'    => 'Y-m-d',
                    'correctFormat'    => true,
                    'defaultDate'      => '',
                    'datePickerConfig' => array(
                        'firstDay'       => 0,
                        'showWeekNumber' => true,
                        'numberOfMonths' => 1,
                    ),
                ),
                'save_value_callback' => array($this, 'save_event_date'),
                'supports_formulas'   => true,
            );
            $columns['_EventStartDate_time'] = array(
                'data_type'           => 'meta_data',
                'title'               => __( 'Start time (H:i:s)', vgse_events()->textname ),
                'save_value_callback' => array($this, 'save_event_time'),
                'supports_formulas'   => true,
            );
            $columns['_EventEndDate_date'] = array(
                'data_type'           => 'meta_data',
                'title'               => __( 'End date', vgse_events()->textname ),
                'formatted'           => array(
                    'data'             => '_EventEndDate_date',
                    'type'             => 'date',
                    'dateFormatPhp'    => 'Y-m-d',
                    'correctFormat'    => true,
                    'defaultDate'      => '',
                    'datePickerConfig' => array(
                        'firstDay'       => 0,
                        'showWeekNumber' => true,
                        'numberOfMonths' => 1,
                    ),
                ),
                'save_value_callback' => array($this, 'save_event_date'),
                'supports_formulas'   => true,
            );
            $columns['_EventEndDate_time'] = array(
                'data_type'           => 'meta_data',
                'title'               => __( 'End time (H:i:s)', vgse_events()->textname ),
                'save_value_callback' => array($this, 'save_event_time'),
                'supports_formulas'   => true,
            );
            return $columns;
        }

        function save_new_venue( $rows, $settings ) {
            if ( !in_array( $settings['post_type'], $this->post_type, true ) ) {
                return $rows;
            }
            foreach ( $rows as $index => $row ) {
                $new_venue_data = array(
                    'meta_input' => array(
                        '_EventShowMapLink' => 1,
                        '_VenueOrigin'      => 'events-calendar',
                        '_EventShowMap'     => 1,
                        '_VenueShowMapLink' => 1,
                        '_VenueShowMap'     => 1,
                    ),
                );
                foreach ( $row as $column_key => $column_value ) {
                    if ( strpos( $column_key, 'wpse_new_venue' ) !== 0 ) {
                        continue;
                    }
                    if ( $column_key === 'wpse_new_venue_title' ) {
                        $new_venue_data['post_title'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_address' ) {
                        $new_venue_data['meta_input']['_VenueAddress'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_city' ) {
                        $new_venue_data['meta_input']['_VenueCity'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_country' ) {
                        $new_venue_data['meta_input']['_VenueCountry'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_state' ) {
                        $new_venue_data['meta_input']['_VenueProvince'] = $column_value;
                        $new_venue_data['meta_input']['_VenueStateProvince'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_postal_code' ) {
                        $new_venue_data['meta_input']['_VenueZip'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_website' ) {
                        $new_venue_data['meta_input']['_VenueURL'] = $column_value;
                    } elseif ( $column_key === 'wpse_new_venue_phone' ) {
                        $new_venue_data['meta_input']['_VenuePhone'] = $column_value;
                    }
                }
                if ( !empty( $new_venue_data['post_title'] ) ) {
                    $venue_id = wp_insert_post( $new_venue_data );
                    if ( $venue_id ) {
                        $rows[$index]['_EventVenueID'] = $venue_id;
                    }
                }
            }
            return $rows;
        }

        function fake_cell_saving(
            $post_id,
            $cell_key,
            $data_to_save,
            $post_type,
            $cell_args,
            $spreadsheet_columns
        ) {
        }

        function disable_date_filter( $wp_query ) {
            if ( in_array( $wp_query['post_type'], $this->post_type ) ) {
                $wp_query['tribe_remove_date_filters'] = true;
                $wp_query['hide_upcoming'] = false;
                $wp_query['eventDisplay'] = 'custom';
            }
            return $wp_query;
        }

    }

    new WPSE_The_Events_Calendar();
}