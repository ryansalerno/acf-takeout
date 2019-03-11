document.addEventListener('DOMContentLoaded', function() {
});

function lazy_load_images(el){
	var imgs = el.querySelectorAll('[data-src]'),
		imgs_length = imgs.length;
	if (!imgs_length){ return; }

	for (var i = 0; i < imgs_length; i++) {
		imgs[i].src = imgs[i].getAttribute('data-src');
	}
}
