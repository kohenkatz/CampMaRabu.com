$(window).load(function() {
	var slider = $('#slider')
	if (slider) {
		slider.nivoSlider();
	}
	
	$('a[rel*=lightbox]').fancybox({
		closeBtn: false,
		helpers: {
			title: { type : 'inside' },
			buttons: {}
		}
	});
});