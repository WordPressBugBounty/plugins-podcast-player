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
            const willOpen = !container.is(':visible');
            container.slideToggle('fast');
            if (willOpen) {
                _this.markCandidateSeen($this, false);
            }
        });

        this.addSourceUrlBtn.on('click', function() {
            _this.submitManualSource(jQuery(this), '');
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

        // Approve a duplicate review item.
        this.adminPage.on('click', '.pp-migration-merge-btn', function() {
            _this.resolveReview(jQuery(this), 'pp_resolve_migration');
        });

        // Dismiss a duplicate review item.
        this.adminPage.on('click', '.pp-migration-dismiss-btn', function() {
            _this.resolveReview(jQuery(this), 'pp_dismiss_migration');
        });

        // Acknowledge an inline redirect notice without deleting its candidate.
        this.adminPage.on('click', '.pp-migration-same-feed-btn', function() {
            _this.markCandidateSeen(jQuery(this), true);
        });

        // Apply an inline redirect notice as the record source.
        this.adminPage.on('click', '.pp-migration-use-source-btn', function() {
            _this.resolveCandidate(jQuery(this), '');
        });
	}

	/**
	 * Mark the current redirect candidate as seen without resolving it.
	 *
	 * @since 8.3.0
	 *
	 * @param {object} $btn     Clicked button.
	 * @param {bool}   announce Whether to show a feedback message.
	 */
	markCandidateSeen($btn, announce) {
		const _this = this;
		const item = $btn.closest('.pp-podcast-list-item');
		const candidate = item.find('.pp-podcast-migration-candidate').first();
		const dot = item.find('.pp-migration-unseen-dot');
		if (!candidate.length || (!dot.length && !announce)) {
			return;
		}
		const feedKey = item.data('podcast');
		if (!feedKey) {
			return;
		}
		jQuery.ajax({
			url: _this.data.ajaxurl,
			type: 'POST',
			timeout: 60000,
			data: {
				action: 'pp_mark_migration_seen',
				security: _this.data.security,
				feed_key: feedKey,
			},
			success: response => {
				let details = {};
				try {
					details = JSON.parse(response);
				} catch (e) {
					details = {};
				}
				if (details && 'undefined' !== typeof details.error) {
					if (announce) {
						_this.newResponse(details.error, 'pp-error');
					}
					return;
				}
				dot.remove();
				if (announce && details && 'undefined' !== typeof details.message) {
					_this.newResponse(details.message, 'pp-success');
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				if (announce) {
					_this.newResponse(errorThrown, 'pp-error');
				}
			}
		});
	}

	/**
	 * Send a manual source-URL change, optionally confirming a shown conflict.
	 *
	 * The manual field goes through the same verify-then-confirm flow as the
	 * review notice: an unverified change reports what differs and applies
	 * nothing until the admin confirms the exact target they were shown.
	 *
	 * @since 8.3.0
	 *
	 * @param {object} $btn          Clicked button.
	 * @param {string} confirmTarget Target URL being confirmed, if any.
	 */
	submitManualSource($btn, confirmTarget) {
		const _this = this;
		const container = $btn.closest('.pp-podcast-source-container');
		const swrapper = container.find('.pp-podcast-existing-source');
		const sContainer = swrapper.find('.pp-podcast-existing-source-url');
		const wrapper = container.find('.pp-podcast-new-source');
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
		if (confirmTarget) {
			ajaxConfig.confirm_target = confirmTarget;
		}
		inputField.attr('disabled', true);
		$btn.attr('disabled', true);
		jQuery.ajax({
			url: _this.data.ajaxurl,
			data: ajaxConfig,
			type: 'POST',
			timeout: 60000,
			success: response => {
				let details = {};
				try {
					details = JSON.parse(response);
				} catch (e) {
					details = {};
				}
				inputField.attr('disabled', false);
				$btn.attr('disabled', false);
				if (!details || jQuery.isEmptyObject(details)) {
					return;
				}
				if ('undefined' !== typeof details.conflict) {
					_this.renderConflict(container, details.conflict, function(target) {
						_this.submitManualSource($btn, target);
					});
					return;
				}
				if ('undefined' !== typeof details.error) {
					_this.newResponse(details.error, 'pp-error');
					return;
				}
				if ('undefined' !== typeof details.message) {
					container.find('.pp-migration-conflict').remove();
					// Rendered as text, never HTML: this value came from an
					// admin-supplied field and is echoed straight back.
					sContainer.text(newSourceUrl);
					swrapper.show();
					inputField.val('');
					_this.newResponse(details.message, 'pp-success');
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				inputField.attr('disabled', false);
				$btn.attr('disabled', false);
				_this.newResponse(errorThrown, 'pp-error');
			}
		});
	}

	/**
	 * Send a review action (approve or dismiss) and remove the item on success.
	 *
	 * @since 8.3.0
	 *
	 * @param {object} $btn   Clicked button.
	 * @param {string} action AJAX action name.
	 */
	resolveReview($btn, action, confirmTarget) {
		const _this = this;
		const item = $btn.closest('.pp-migration-review-item');
		const mtype = item.data('mtype') || 'pair';
		const payload = {
			action: action,
			security: _this.data.security,
			mtype: mtype,
		};
		if ('group' === mtype) {
			const memberKeys = String(item.attr('data-member-keys') || '')
				.split(',')
				.map(key => key.trim())
				.filter(key => key.length > 0);
			if (memberKeys.length < 2) {
				return;
			}
			payload.member_keys = memberKeys;
		} else if ('candidate' === mtype) {
			payload.feed_key = item.data('feed-key');
			if (!payload.feed_key) {
				return;
			}
			// Echo back the exact target the conflict was shown for, so the
			// server can refuse a confirmation aimed at a stale candidate.
			if (confirmTarget) {
				payload.confirm_target = confirmTarget;
			}
		} else {
			payload.pair_key = item.data('pair-key');
			if (!payload.pair_key) {
				return;
			}
		}
		const buttons = item.find('button');
		buttons.attr('disabled', true);
		jQuery.ajax({
			url: _this.data.ajaxurl,
			type: 'POST',
			timeout: 60000,
			data: payload,
			success: response => {
				let details = null;
				try {
					details = JSON.parse(response);
				} catch (e) {
					details = null;
				}
				// An unreadable or empty body is not proof anything was applied,
				// so keep the row and say so rather than quietly removing it.
				if (!details || jQuery.isEmptyObject(details)) {
					_this.newResponse(_this.data.messages.error || '', 'pp-error');
					buttons.attr('disabled', false);
					return;
				}
				if (details && 'undefined' !== typeof details.error) {
					_this.newResponse(details.error, 'pp-error');
					buttons.attr('disabled', false);
					return;
				}
				// Nothing was applied: show the two feed identities and keep the
				// row so the admin can decide deliberately.
				if (details && 'undefined' !== typeof details.conflict) {
					_this.renderConflict(item, details.conflict, function(target) {
						_this.resolveReview($btn, 'pp_resolve_migration', target);
					});
					buttons.attr('disabled', false);
					return;
				}
				// Applied nothing but is not an error either (deferred, noop):
				// report it and leave the suggestion in place.
				if (details && 'undefined' !== typeof details.notice) {
					_this.newResponse(details.notice, 'pp-error');
					buttons.attr('disabled', false);
					return;
				}
				if (details && 'undefined' !== typeof details.message) {
					_this.newResponse(details.message, 'pp-success');
				}
				if (item.data('refresh-on-success')) {
					window.setTimeout(function() {
						window.location.reload();
					}, 350);
					return;
				}
				item.slideUp('fast', function() {
					const list = item.closest('.pp-migration-review-list');
					item.remove();
					if (list.children().length === 0) {
						list.closest('.pp-migration-review').slideUp('fast');
					}
				});
			},
			error: (jqXHR, textStatus, errorThrown) => {
				buttons.attr('disabled', false);
				_this.newResponse(errorThrown, 'pp-error');
			}
		});
	}

	/**
	 * Apply an inline redirect candidate as the podcast source.
	 *
	 * @since 8.3.0
	 *
	 * @param {object} $btn          Clicked button.
	 * @param {string} confirmTarget Target URL being confirmed, if any.
	 */
	resolveCandidate($btn, confirmTarget) {
		const _this = this;
		const item = $btn.closest('.pp-podcast-list-item');
		const candidate = item.find('.pp-podcast-migration-candidate').first();
		const feedKey = item.data('podcast');
		if (!feedKey || !candidate.length) {
			return;
		}

		const payload = {
			action: 'pp_resolve_migration',
			security: _this.data.security,
			mtype: 'candidate',
			feed_key: feedKey,
		};
		if (confirmTarget) {
			payload.confirm_target = confirmTarget;
		}

		const buttons = candidate.find('button');
		buttons.attr('disabled', true);
		jQuery.ajax({
			url: _this.data.ajaxurl,
			type: 'POST',
			timeout: 60000,
			data: payload,
			success: response => {
				let details = null;
				try {
					details = JSON.parse(response);
				} catch (e) {
					details = null;
				}
				if (!details || jQuery.isEmptyObject(details)) {
					_this.newResponse(_this.data.messages.error || '', 'pp-error');
					buttons.attr('disabled', false);
					return;
				}
				if ('undefined' !== typeof details.error) {
					_this.newResponse(details.error, 'pp-error');
					buttons.attr('disabled', false);
					return;
				}
				if ('undefined' !== typeof details.conflict) {
					_this.renderConflict(candidate, details.conflict, function(target) {
						_this.resolveCandidate($btn, target);
					});
					buttons.attr('disabled', false);
					return;
				}
				if ('undefined' !== typeof details.notice) {
					_this.newResponse(details.notice, 'pp-error');
					buttons.attr('disabled', false);
					return;
				}
				if ('undefined' !== typeof details.message) {
					const target = candidate.data('target') || '';
					const swrapper = item.find('.pp-podcast-existing-source');
					swrapper.find('.pp-podcast-existing-source-url').text(target);
					swrapper.show();
					item.find('.pp-migration-unseen-dot').remove();
					candidate.slideUp('fast', function() {
						candidate.remove();
					});
					_this.newResponse(details.message, 'pp-success');
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				buttons.attr('disabled', false);
				_this.newResponse(errorThrown, 'pp-error');
			}
		});
	}

	/**
	 * Show why a source change did not verify, and offer a deliberate override.
	 *
	 * Rendered once per row. The override button carries the target it was shown
	 * for, so a dialog left open while another candidate is raised cannot be
	 * used to approve the newer one.
	 *
	 * @since 8.3.0
	 *
	 * @param {object}   host      Element to render the conflict inside.
	 * @param {object}   conflict  Conflict payload from the server.
	 * @param {function} onConfirm Called with the confirmed target URL.
	 */
	renderConflict(host, conflict, onConfirm) {
		host.find('.pp-migration-conflict').remove();

		const labels = conflict.labels || {};
		const unknown = labels.unknown || '';
		const describe = side => {
			const title = (side && side.title) || unknown;
			const author = (side && side.author) || unknown;
			return jQuery('<span/>').text(title + ' — ' + author);
		};

		const box = jQuery('<div/>', {
			'class': 'pp-migration-conflict mt-2 w-full rounded-md border border-rose-300 bg-rose-50 p-2 text-xs text-rose-900'
		});
		box.append(jQuery('<p/>', { 'class': 'm-0 font-semibold' }).text(conflict.message || ''));
		box.append(jQuery('<p/>', { 'class': 'm-0 mt-1' })
			.append(jQuery('<strong/>').text((labels.current || '') + ': '))
			.append(describe(conflict.old)));
		box.append(jQuery('<p/>', { 'class': 'm-0' })
			.append(jQuery('<strong/>').text((labels.new || '') + ': '))
			.append(describe(conflict.new)));
		if (conflict.target) {
			box.append(jQuery('<p/>', { 'class': 'm-0 mt-1 break-all' }).text(conflict.target));
		}

		const confirm = jQuery('<button/>', {
			type: 'button',
			'class': 'pp-migration-confirm-btn button mt-2'
		}).text(labels.confirm || '');
		confirm.on('click', function() {
			onConfirm(conflict.target);
		});
		box.append(confirm);

		host.append(box);
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
