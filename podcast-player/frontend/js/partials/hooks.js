const ppHooks = window.PP_Hooks || {
	hooks: {},
	actions: {},
	actionHistory: {},

	addFilter(hookName, callback, priority = 10) {
		const hooks = this.hooks[hookName] = this.hooks[hookName] || [];
		hooks.push({ callback, priority });
		hooks.sort((a, b) => a.priority - b.priority);
	},

	applyFilters(hookName, value, ...args) {
		const hooks = this.hooks[hookName];
		if (!hooks) {
			return value;
		}
		return hooks.reduce((currentValue, { callback }) => callback(currentValue, ...args), value);
	},

	addAction(hookName, callback, priority = 10, options = {}) {
		const hooks = this.actions[hookName] = this.actions[hookName] || [];
		hooks.push({ callback, priority });
		hooks.sort((a, b) => a.priority - b.priority);

		if (options.replay && this.actionHistory[hookName]) {
			this.actionHistory[hookName].forEach(args => callback(...args));
		}
	},

	doAction(hookName, ...args) {
		if ('podcast_player_transcripts_setup' === hookName) {
			const history = this.actionHistory[hookName] = this.actionHistory[hookName] || [];
			const instance = args[0];
			const isStored = instance ? history.some(storedArgs => storedArgs[0] === instance) : false;
			if (!isStored) {
				history.push(args);
			}
		}

		const hooks = this.actions[hookName];
		if (!hooks) {
			return;
		}
		hooks.forEach(({ callback }) => callback(...args));
	},
};

window.PP_Hooks = ppHooks;

export default ppHooks;
