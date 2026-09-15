(function () {
	'use strict';
	var tabs = document.querySelectorAll('.svac-admin .nav-tab');
	tabs.forEach(function (tab) {
		tab.addEventListener('click', function (event) {
			event.preventDefault();
			tabs.forEach(function (item) { item.classList.remove('nav-tab-active'); });
			document.querySelectorAll('.svac-admin section').forEach(function (section) { section.hidden = true; });
			tab.classList.add('nav-tab-active');
			document.querySelector(tab.getAttribute('href')).hidden = false;
		});
	});
}());
