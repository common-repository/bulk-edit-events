<?php defined( 'ABSPATH' ) || exit;
if (!class_exists('WPSE_Custom_Tables_Spreadsheet_Bootstrap')) {

	class WPSE_Custom_Tables_Spreadsheet_Bootstrap extends WP_Sheet_Editor_Bootstrap {

		public function __construct($args) {
			parent::__construct($args);
		}

		function render_quick_access() {
			
		}

		function _register_columns() {
			$post_types = $this->enabled_post_types;

			foreach ($post_types as $post_type) {
				$this->columns->register_item('ID', $post_type, array(
					'data_type' => 'post_data', 	
					'column_width' => 75, 
					'title' => __('ID', 'vg_sheet_editor' ),
					'supports_formulas' => false,
					'allow_to_hide' => false,
					'allow_to_save' => false,
					'allow_to_rename' => false,
					'is_locked' => true,
				));
			}
		}

	}

}