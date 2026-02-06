class ShortcodeGenerator {

	/**
	 * Manage Widget editor options.
	 * 
	 * @since 3.3
	 */
	constructor() {
        this.data = window.ppjsAdminOpt || {};
		this.newFeedback = jQuery('#pp-action-feedback');
		this.serverTimeOut = null;
		// Run methods.
		this.events();
	}

	// Event handling.
	events() {
		const _this  = this;
		const widget = jQuery('#pp-options-module-shortcode');
		const doc    = jQuery(document);

		widget.on('change', '.pp-getval', function() {
			clearTimeout( _this.serverTimeOut );
			_this.serverTimeOut = setTimeout( function() {
				_this.updatePreview( jQuery(this) );
			}, 100);
		});

		widget.on('click', '#pp-shortcode-generator-btn', function() {
			_this.blankShortcodeTemplate( jQuery(this) );
		});

		widget.on('click', '#pp-shortcode-generator-submit-btn', function() {
			_this.createNewShortcode( jQuery(this) );
		});

		widget.on('click', '#pp-shortcode-generator-delete-btn', function() {
			widget.find('#pp-shortcode-action-modal').removeClass( 'podcast-player-hidden' );
		});

		widget.on('click', '#pp-shortcode-deletion-btn', function() {
			_this.deleteShortcode( jQuery(this) );
		});

		widget.on('click', '#pp-shortcode-deletion-cancel', function() {
			widget.find('#pp-shortcode-action-modal').addClass( 'podcast-player-hidden' );
		});

		widget.on('click', '#pp-shortcode-generator-update-btn', function() {
			_this.updateShortcode( jQuery(this) );
		});

		widget.on('change', 'select.pp-shortcode-dropdown', function() {
			_this.loadShortcode( jQuery(this) );
		});

		widget.on('click', '.pp-collapse-sidebar', function(e) {
			e.preventDefault();
			_this.toggleSidebar( jQuery(this) );
		});

		widget.on('click', '.pp-copy-shortcode-text', function(e) {
			e.preventDefault();
			_this.copyShortcodeText( jQuery(this) );
		});
	}

	getShortcodeFormValues() {
		const widget   = jQuery('#pp-shortcode-form');
		const fields   = widget.find('.pp-getval');
		const instance = widget.data('instance');
		const values = {};
		fields.each(
			function() {
				// Remove 'dpt_field_name_' from this.name.
				let name = this.name.replace( /^pp_field_name_/, '' );
                if (name.endsWith('[]')) {
                    name = name.replace(/\[\]$/, '');

                    // Initialize the array if it doesn't exist yet
                    if (!Array.isArray(values[name])) {
                        values[name] = [];
                    }

                    if ( 'checkbox' === this.type ) {
                        values[name].push(this.checked ? this.value : '');
                    } else {
                        values[name].push(this.value);
                    }
                } else {
                    if ( 'checkbox' === this.type ) {
                        values[name] = this.checked ? this.value : '';
                    } else {
                        values[name] = this.value;
                    }
                }
			}
		);
		console.log(instance);
		return { instance, values };
	}

	updatePreview( input ) {
		const _this = this;
		const { instance, values } = this.getShortcodeFormValues();
		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: {
				action  : 'pp_render_preview',
				security: this.data.security,
				data    : values,
			},
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.newResponse(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.markup) {
						const wrapper = jQuery('#pp-shortcode-preview');
						wrapper.html( details.markup );
						_this.updateFont();
						// window.dptScriptData.instances = details.instances;
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.newResponse(errorThrown, 'dpt-error');
			}
		} );
	}

	blankShortcodeTemplate(button) {
		button.siblings('select.pp-shortcode-dropdown').val('');
		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: {
				action  : 'pp_blank_shortcode_template',
				security: this.data.security,
			},
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.newResponse(details.error, 'dpt-error');
					} else if ('undefined' !== typeof details.form && 'undefined' !== typeof details.instance) {
						const form = `
						<div class="pp-shortcode-form-wrapper">${details.form}</div>
						<div class="pp-shortcode-form-submit">
							<button id="pp-shortcode-generator-submit-btn" class="button button-secondary" style="width: 100%;">Generate Shortcode</button>
						</div>
						`;

						const preview = `
						<div style="padding: 20px; font-size: 20px; color: #aaa;">
							<span>Shortcode</span>
							<span style="color: #333;">Preview</span>
							<span> will be displayed here.</span>
						</div>
						`;
						const formWrapper = jQuery('#pp-shortcode-form');
						const previewWrapper = jQuery('#pp-shortcode-preview');
						jQuery('.pp-shortcode-result').html( '' );
						formWrapper.html( form ).data('instance', details.instance);
						previewWrapper.html( preview );
						jQuery(document).trigger('pp-widget-added');
						this.newResponse('Shortcode template created successfully', 'pp-success');
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.newResponse(errorThrown, 'pp-error');
			}
		} );
	}

	createNewShortcode() {
		const { instance, values } = this.getShortcodeFormValues();
		const title = values.title || 'Podcast Player Shortcode' + ' ' + (instance + 1);
		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: {
				action  : 'pp_create_new_shortcode',
				security: this.data.security,
				data    : values,
				instance: instance,
			},
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.newResponse(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.success) {
						const widget   = jQuery('#pp-options-module-shortcode');
						const wrapper  = widget.find('.pp-shortcode-action');
						let dropdown = widget.find('select.pp-shortcode-dropdown');
						if (0 === dropdown.length) {
							wrapper.append(`
								<span class="pp-separator">or</span>
								<select class="pp-shortcode-dropdown">
									<option value="" selected="selected">Select a Shortcode to Edit</option>
								</select>
							`);
							dropdown = widget.find('select.pp-shortcode-dropdown');
						}
						dropdown.append(`<option value="${instance}">${title}</option>`);
						dropdown.val(instance);
						dropdown.trigger('change');
						this.newResponse('New shortcode created successfully', 'pp-success');
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.newResponse(errorThrown, 'pp-error');
			}
		} );
	}

	loadShortcode(select) {
		const _this = this;
		const instance = select.val();
		if ( ! instance ) {
			jQuery('#pp-shortcode-form').html('');
			jQuery('#pp-shortcode-preview').html(`
				<div style="padding: 20px; font-size: 20px; color: #aaa;">
					<span>Create a </span>
					<span style="color: #333;">New Shortcode</span>
					<span> or </span>
					<span style="color: #333;">Edit an Existing</span>
					<span> Shortcode using the menu above.</span>
				</div>
			`);
			jQuery('.pp-shortcode-result').html( '' );
			return;
		}
		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: {
				action  : 'pp_load_shortcode',
				security: this.data.security,
				instance: instance,
			},
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.newResponse(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.form && 'undefined' !== typeof details.preview) {
						const form = `
						<div class="pp-shortcode-form-wrapper">${details.form}</div>
						<div class="pp-shortcode-form-update pp-button-wrapper">
							<button id="pp-shortcode-generator-update-btn" class="button button-secondary" style="width: 100%;">Update Shortcode</button>
						</div>
						<div class="pp-shortcode-form-delete pp-button-wrapper">
							<button id="pp-shortcode-generator-delete-btn" class="button button-secondary" style="width: 100%;">Delete Shortcode</button>
						</div>
						`;

						const preview = `
						${details.preview}
						`;
						const resultsWrapper = jQuery('.pp-shortcode-result');
						const formWrapper = jQuery('#pp-shortcode-form');
						const previewWrapper = jQuery('#pp-shortcode-preview');
						formWrapper.html( form ).data('instance', details.instance);
						previewWrapper.html( preview );
						resultsWrapper.html(`
							<div class="pp-shortcode-sidebar-collapse">
								<a href="#" class="pp-collapse-sidebar">
									<span class="dashicons dashicons-arrow-left-alt2"></span>
									<span class="pp-collapse-side">Collapse</span>
									<span class="pp-expand-side" style="display: none;">Expand</span>
								</a>
							</div>
							<div class="pp-shortcode-copy">
								<span>Your shortcode is </span>
								<pre class="pp-shortcode-text"><code>[showpodcastplayer instance="${details.instance}"]</code></pre>
								<a href="#" class="pp-copy-shortcode-text">(Copy shortcode)</a>
							</div>
						`);
						_this.updateFont();
						// window.ppScriptData.instances = details.instances;
						jQuery(document).trigger('pp-widget-added');
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.newResponse(errorThrown, 'pp-error');
			}
		} );
	}

	updateFont() {
		const fontField = jQuery('#pp-options-module-shortcode .podcast-player-pp-fonts');
		if (! fontField.length) return;
		const gFont = fontField.val();
		const allFonts = this.data.gfonts;
		const fontLabel = gFont && allFonts[ gFont ] ? allFonts[ gFont ] : false;
		if (! fontLabel) return;
		const fontName = fontLabel.split( ' ' ).join( '+' );
		if ( 0 === jQuery( 'link#podcast-player-fonts-css-temp' ).length ) {
			const gfontUrl = '//fonts.googleapis.com/css?family=' + fontName;
			const gfontlink = jQuery( '<link>', {
				id: 'podcast-player-fonts-css-temp',
				href: gfontUrl,
				rel: 'stylesheet',
				type: 'text/css'
			} );
			jQuery( 'link:last' ).after( gfontlink );
		} else {
			const elem = jQuery('link#podcast-player-fonts-css-temp');
			const href = elem.attr('href');
			elem.attr( 'href', href + '%7C' + fontName );
		}
	}

	deleteShortcode(button) {
		const widget   = jQuery('#pp-options-module-shortcode');
		const instance = widget.find('#pp-shortcode-form').data('instance');
		const dropdown = widget.find('select.pp-shortcode-dropdown');
		widget.find('#pp-shortcode-action-modal').addClass( 'podcast-player-hidden' );
		if ( 'undefined' === typeof instance ) {
			return;
		}
		widget.find('.pp-shortcode-result').html( '' );
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: {
				action  : 'pp_delete_shortcode',
				security: this.data.security,
				instance: instance,
			},
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.newResponse(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.success) {
						dropdown.val('');
						dropdown.find(`option[value="${instance}"]`).remove();
						// check if dropdown does not have any option left.
						if ( 0 === dropdown.find('option').length ) {
							dropdown.remove();
						} else {
							dropdown.trigger('change');
						}
						this.newResponse('Shortcode deleted successfully', 'pp-success', true);
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.newResponse(errorThrown, 'pp-error');
			}
		} );
	}

	updateShortcode(button) {
		const { instance, values } = this.getShortcodeFormValues();
		if ( values.title ) {
			const selectedShortcode = jQuery('.pp-shortcode-dropdown option:selected');
			selectedShortcode.text( values.title );
		}
		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: {
				action  : 'pp_update_shortcode',
				security: this.data.security,
				data    : values,
				instance: instance,
			},
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.newResponse(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.success) {
						this.newResponse('Shortcode updated successfully', 'pp-success');
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.newResponse(errorThrown, 'pp-error');
			}
		} );
	}

	/**
	 * Display action feedback.
	 * 
	 * @since 2.6.0
	 * 
	 * @param string  message
	 * @param string  type
	 * @param boolean reload
	 */
	newResponse(message = '', type = false, reload = false) {
		this.newFeedback.removeClass('pp-error pp-success pp-running');
		if (false !== type) {
			this.newFeedback.addClass(type);
			this.newFeedback.find('.pp-feedback').text(message);
		}

		// Remove classes after 2 seconds
		setTimeout(function() {
			this.newFeedback.removeClass('pp-success pp-running');
			if (reload) {
				window.location.reload();
			}
		}.bind(this), 1000);
	}

	/**
	 * Toggle form sidebar/
	 *
	 * @since 2.6.0
	 */
	toggleSidebar(link) {
		const sidebar = jQuery('#pp-shortcode-form');
		sidebar.toggleClass('pp-sidebar-close');
		link.toggleClass('pp-sidebar-close');
		window.dispatchEvent(new Event('resize'));
	}

	/**
	 * Copy shortcode text.
	 *
	 * @since 2.6.0
	 */
	copyShortcodeText(link) {
		const wrapper = link.closest('.pp-shortcode-copy');
		const text = wrapper.find('.pp-shortcode-text code').text();
		// Create a temporary textarea to copy the text
		var tempTextarea = jQuery("<textarea>");
		jQuery("body").append(tempTextarea);
		tempTextarea.val(text).select();
		document.execCommand("copy");
		tempTextarea.remove();

		// Notify the user.
		this.newResponse('Shortcode copied to clipboard', 'pp-success');
	}
}

export default ShortcodeGenerator;
