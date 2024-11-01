jQuery(document).ready(function($) {
	$('.pp-wpsq-jpeg-quality-field').each(function() {
		var $sliderField = $(this);
		var $displayField = $sliderField.clone()
							.attr('name', '')
							.removeClass('pp-wpsq-jpeg-quality-field')
							.addClass('pp-wpsq-jpeg-quality-display')
							.insertBefore($sliderField)
							.change(function() {
								$sliderField.val($displayField.val()).change();
							});
		$sliderField.rangeslider({
			polyfill: false,
			onSlide: function(pos, val) {
				$displayField.val(val);
				if ($sliderField.is('.pp-wpsq-jpeg-quality-field-all')) {
					$('td:nth-child(' + ( $sliderField.parent().index() + 1 ) + ') .pp-wpsq-jpeg-quality-field').not($sliderField).val(val).change();
				}
			},
			onSlideEnd: function(pos, val) {
				this.onSlide(pos, val);
			}
		});
	});
});