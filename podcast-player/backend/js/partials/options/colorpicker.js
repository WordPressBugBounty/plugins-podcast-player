class ColorPicker {

	/**
	 * Manage Feed editor options.
	 * 
	 * @since 3.3
	 * 
	 * @param {string} id Podcast player ID. 
	 */
	constructor() {
		// Run methods.
		this.events();
	}

	// Event handling.
	events() {
		const _this  = this;
		jQuery(function() { _this.colorPicker() });
		jQuery(document).on( 'pp-widget-added', function() {
			_this.colorPicker();
		} );
	}

	/**
	 * Color Picker Functionality.
	 * 
	 * @since 3.7.0
	 */
	colorPicker() {
		const params = {
			change: function(e, ui) {
				jQuery( e.target ).val( ui.color.toString() );
				jQuery( e.target ).trigger('change'); // enable widget "Save" button
			},
		};

		const $colorFields = jQuery('.pp-color-picker').not('[id*="__i__"]');
		$colorFields.wpColorPicker(params);
		
		// Handle the "Clear" button
		$colorFields.each(function() {
			const $input = jQuery(this);
			const $container = $input.closest('.wp-picker-container');
			$container.find('.wp-picker-clear').on('click', function() {
				$input.val('').trigger('change'); // manually trigger change when cleared
			});
		});
	}
}

export default ColorPicker;
