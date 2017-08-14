/**
 * Initialize the Laravel forms.
 */
window.Laravel = {};
Laravel.forms = {};

/**
 * Load the LaravelForm helper class.
 */
require('./form');

/**
 * Define the LaravelFormError collection class.
 */
require('./errors');

/**
 * Add additional HTTP / form helpers to the Laravel object.
 */
Object.assign(Laravel, require('./http'));
// $.extend(Laravel, require('./http'));