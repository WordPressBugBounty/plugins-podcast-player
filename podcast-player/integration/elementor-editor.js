/* global elementor, ppElementor */
(function ($) {
	'use strict';

	$(window).on('elementor:init', function () {
		const Select2 = elementor.modules.controls.Select2;
		const dependencies = {
			feed_url: [],
			pp_taxonomy: ['pp_post_type', 'pp_fetch_method'],
			pp_terms: ['pp_post_type', 'pp_taxonomy', 'pp_fetch_method'],
			pp_slist: ['feed_url', 'pp_fetch_method'],
			pp_catlist: ['feed_url', 'pp_fetch_method'],
			pp_elist: ['feed_url', 'pp_fetch_method', 'pp_post_type', 'pp_taxonomy', 'pp_terms', 'pp_slist', 'pp_catlist', 'sortby', 'filterby']
		};

		const QueryControl = Select2.extend({
			onReady: function () {
				this.queryField = this.model.get('pp_query_field');
				this.$status = $('<div class="elementor-control-field-description" role="status"></div>').appendTo(this.$el);
				const events = (dependencies[this.queryField] || []).map(key => 'change:' + key).join(' ');
				if (events) {
					this.listenTo(this.container.settings, events, function () {
						this.setValue(this.model.get('multiple') ? [] : '');
						this.ui.select.val(this.getControlValue()).trigger('change.select2');
						clearTimeout(this.queryTimer);
						this.queryTimer = setTimeout(() => this.loadOptions(), 350);
					});
				}
				this.loadOptions();
			},

			onBaseInputChange: function () {
				Select2.prototype.onBaseInputChange.apply(this, arguments);
				if (this.queryField === 'feed_url' && this.getControlValue()) {
					// Use Elementor's command so the URL field, preview, and undo history update together.
					$e.run('document/elements/settings', {
						container: this.container,
						settings: { feed_url: this.getControlValue() }
					});
				}
			},

			loadOptions: function () {
				if (this.request) this.request.abort();
				this.$status.text('');
				this.ui.select.prop('disabled', true);
				this.request = $.ajax({
					url: ppElementor.url, method: 'POST', dataType: 'json',
					data: { action: 'pp_elementor_options', nonce: ppElementor.nonce,
						field: this.queryField, settings: JSON.stringify(this.container.settings.toJSON()) }
				}).done(response => {
					if (!response.success) {
						this.$status.text(response.data && response.data.message || ppElementor.error);
						return;
					}
					const value = this.getControlValue();
					const selected = Array.isArray(value) ? value.map(String) : [String(value || '')];
					this.ui.select.empty();
					if (!this.model.get('multiple')) this.ui.select.append(new Option('', ''));
					const seen = new Set();
					response.data.forEach(option => {
						const id = String(option.id);
						seen.add(id);
						this.ui.select.append(new Option(option.text, id, false, selected.includes(id)));
					});
					// Retain saved values if a feed temporarily loses an episode or is unavailable.
					selected.filter(id => id && !seen.has(id)).forEach(id => {
						this.ui.select.append(new Option(id, id, false, true));
					});
					this.ui.select.val(value).trigger('change.select2');
				}).fail((request, status) => {
					if (status !== 'abort') this.$status.text(ppElementor.error);
				}).always(() => this.ui.select.prop('disabled', false));
			},

			onBeforeDestroy: function () {
				clearTimeout(this.queryTimer);
				if (this.request) this.request.abort();
				Select2.prototype.onBeforeDestroy.apply(this, arguments);
			}
		});
		elementor.addControlView('pp-query', QueryControl);
	});
})(jQuery);
