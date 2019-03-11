document.addEventListener('DOMContentLoaded', function(){
	var notification = document.querySelector('aside.notification');
	if (notification){
		notification.addEventListener('click', function(){
			this.classList.add('dismissed');
		});
	}
});
