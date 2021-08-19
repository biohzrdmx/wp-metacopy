jQuery(document).ready(function($) {
	$('.js-toggle-switch').each(function() {
		var el = $(this),
			widget = $('<div class="toggle-switch" tabindex="0"></div>');
		el.hide();
		el.after(widget);
		widget.append('<div class="switch-handle"></div>');
		widget.append(el);
		el.on('change', function() {
			if ( el.prop('checked') ) {
				widget.addClass('is-on');
			} else {
				widget.removeClass('is-on');
			}
		}).trigger('change');
		widget.on('click', function(e) {
			e.preventDefault();
			el.prop('checked', !el.prop('checked')).trigger('change');
		});
		widget.on('keyup', function(e) {
			if (e.keyCode == 13 || e.keyCode == 32) {
				widget.trigger('click');
			}
		});
	});
});