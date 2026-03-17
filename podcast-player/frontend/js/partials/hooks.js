const ppHooks = window.PP_Hooks || {
	hooks: {},
	actions: {},

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

	addAction(hookName, callback, priority = 10) {
		const hooks = this.actions[hookName] = this.actions[hookName] || [];
		hooks.push({ callback, priority });
		hooks.sort((a, b) => a.priority - b.priority);
	},

	doAction(hookName, ...args) {
		const hooks = this.actions[hookName];
		if (!hooks) {
			return;
		}
		hooks.forEach(({ callback }) => callback(...args));
	},
};

window.PP_Hooks = ppHooks;

export default ppHooks;
