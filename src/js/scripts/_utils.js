/*
Debounce and Throttle are extremely important things to have on hand, particularly when observing rapid-firing events (like resize or mousemove)

They do similar--but distinct--things, which can make them confusing: https://css-tricks.com/the-difference-between-throttling-and-debouncing/

var yourfunction = debounce(function(foo){
	// this will only execute 150ms after the last time it's called (so it waits for your event to settle)
}, 150);

var yourfunction = throttle(function(bar){
	// this will only execute once every 150ms regardless of how many calls it gets (so it fires continuously, but LESS continuously)
}, 150);
*/
function debounce(func, wait, immediate){
	var timeout;
	return function(){
		var context = this,
			args = arguments,
			later = function(){
				timeout = null;
				if (!immediate) func.apply(context, args);
			}
		var callNow = immediate && !timeout;
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
		if (callNow) func.apply(context, args);
	}
}

function throttle(callback, limit) {
	var wait = false;
	return function() {
		if (wait) {
			return;
		}
		callback.call();
		wait = true;
		setTimeout(function() {
			wait = false;
		}, limit);
	}
}
