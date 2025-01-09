class FeedMigrate {

	/**
	 * Manage Feed Migration.
	 * 
	 * @since 7.4.9
	 * 
	 * @param {string} id Podcast player ID. 
	 */
	constructor() {

		// Define variables.
		this.data = window.ppjsAdminOpt || {};
		this.adminPage = jQuery('#pp-options-page');
        this.openSourceUrlBtn = this.adminPage.find('.pp-podcast-source-btn');
        this.addSourceUrlBtn = this.adminPage.find('.pp-podcast-new-source-btn');
        this.delSourceBtn = this.adminPage.find('.pp-podcast-delete-source-url');
		this.newFeedback = jQuery('#pp-action-feedback');

		// Run methods.
		this.events();
	}

	// Event handling.
	events() {
		const _this = this;
        this.openSourceUrlBtn.on('click', function(e) {
            const $this = jQuery(this);
            const wrapper = $this.closest('.pp-podcast-info');
            const container = wrapper.find('.pp-podcast-source-container');
            container.slideToggle('fast');
        });

        this.addSourceUrlBtn.on('click', function(e) {
            const $this = jQuery(this);
            const container  = $this.closest('.pp-podcast-source-container');
            const swrapper   = container.find('.pp-podcast-existing-source');
            const sContainer = swrapper.find('.pp-podcast-existing-source-url');
            const wrapper    = container.find('.pp-podcast-new-source');
            const inputField = wrapper.find('.pp-podcast-new-source-url');
            const newSourceUrl = inputField.val();
            if (!newSourceUrl) {
                _this.newResponse(_this.data.messages.nosource, 'pp-error');
                return;
            }
            const podcastId = wrapper.closest('.pp-podcast-list-item').data('podcast');
            const ajaxConfig = {
                action: 'pp_migrate_podcast',
                security: _this.data.security,
                podcast_id: podcastId,
                source_url: newSourceUrl,
            };
            inputField.attr('disabled', true);
            $this.attr('disabled', true);
            jQuery.ajax( {
                url: _this.data.ajaxurl,
                data: ajaxConfig,
                type: 'POST',
                timeout: 60000,
                success: response => {
                    const details = JSON.parse( response );
                    if (!jQuery.isEmptyObject(details)) {
                        if ('undefined' !== typeof details.error) {
                            _this.newResponse(details.error, 'pp-error');
                        } else if ('undefined' !== typeof details.message) {
                            sContainer.html(newSourceUrl);
                            swrapper.show();
                            inputField.val('');
                            _this.newResponse(details.message, 'pp-success');
                        }
                    }
                    inputField.attr('disabled', false);
                    $this.attr('disabled', false);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    inputField.attr('disabled', false);
                    $this.attr('disabled', false);
                    _this.newResponse(errorThrown, 'pp-error');
                }
            } );
        });

        this.delSourceBtn.on('click', function(e) {
            e.preventDefault();
            const $this      = jQuery(this);
            const wrapper    = $this.closest('.pp-podcast-existing-source');
            const sContainer = wrapper.find('.pp-podcast-existing-source-url');
            const podcastId  = $this.closest('.pp-podcast-list-item').data('podcast');
            const ajaxConfig = {
                action: 'pp_delete_source',
                podcast_id: podcastId,
                security: _this.data.security,
            };
            $this.attr('disabled', true);
            jQuery.ajax( {
                url: _this.data.ajaxurl,
                data: ajaxConfig,
                type: 'POST',
                timeout: 60000,
                success: response => {
                    const details = JSON.parse( response );
                    if (!jQuery.isEmptyObject(details)) {
                        if ('undefined' !== typeof details.error) {
                            _this.newResponse(details.error, 'pp-error');
                        } else if ('undefined' !== typeof details.message) {
                            sContainer.empty();
                            wrapper.hide();
                            _this.newResponse(details.message, 'pp-success');
                        }
                    }
                    $this.attr('disabled', false);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    $this.attr('disabled', false);
                    _this.newResponse(errorThrown, 'pp-error');
                }
            } );
        });
	}

	/**
	 * Display action feedback.
	 * 
	 * @since 6.6.0
	 * 
	 * @param string message
	 * @param string type
	 */
	newResponse(message = '', type = false) {
		this.newFeedback.removeClass('pp-error pp-success pp-running');
		if (false !== type) {
			this.newFeedback.addClass(type);
			this.newFeedback.find('.pp-feedback').text(message);
		}

		// Remove classes after 2 seconds
		setTimeout(function() {
			this.newFeedback.removeClass('pp-success pp-running');
		}.bind(this), 1500);
	}
}

export default FeedMigrate;
