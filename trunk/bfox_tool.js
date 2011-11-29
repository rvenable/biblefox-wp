/*global jQuery, BfoxAjax */

jQuery(document).ready(function () {
	'use strict';

	BfoxAjax.appendUrlWithParamString = function (url, paramString) {
		return url + ((url.indexOf('?') === -1) ? '?' : '&') + paramString;
	};

	BfoxAjax.formSubmit = function () {
		var parameters, url, updatingElement;

		parameters = jQuery(this).serialize();

		jQuery('.bfox-tool-updatable-form-' + jQuery(this).attr('id')).each(function () {
			updatingElement = this;
			url = jQuery(updatingElement).attr('data-url');
			url = BfoxAjax.appendUrlWithParamString(url, parameters);

			jQuery.get(url, function (response) {
				jQuery(updatingElement).attr('data-url', response.dataUrl);
				jQuery(updatingElement).html(response.html);
			});
		});

		return false;
	};

	BfoxAjax.updateFormRef = function (formSelect, refStr) {
		jQuery(formSelect).find('[name="ref"]').attr('value', refStr);
		jQuery(formSelect).submit();

		return false;
	};

	// Iframes
	jQuery('form.bfox-tool-form').submit(BfoxAjax.formSubmit);
	jQuery('select.bfox-tool-select').change(function () {
		jQuery(this).parent('form.bfox-tool-form').submit();
	});
});
