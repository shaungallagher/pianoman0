console.log("Sprint UI loaded");

function initVenueFinderGeo() {
	const btn = document.getElementById('use-my-location-btn');
	if (!btn) return;

	const statusEl = document.getElementById('location-status');
	const latInput = document.querySelector('input[name="lat"]');
	const lngInput = document.querySelector('input[name="lng"]');

	function setStatus(msg) {
		if (statusEl) statusEl.textContent = msg;
	}

	btn.addEventListener('click', function () {
		if (!('geolocation' in navigator)) {
			setStatus('Geolocation is not supported by your browser.');
			return;
		}
		btn.disabled = true;
		setStatus('Getting your location...');

		navigator.geolocation.getCurrentPosition(
			function (pos) {
				const lat = pos.coords.latitude;
				const lng = pos.coords.longitude;
				if (latInput) latInput.value = String(lat);
				if (lngInput) lngInput.value = String(lng);
				setStatus('Location captured.');
				btn.disabled = false;
			},
			function (err) {
				const reason = err && err.message ? err.message : 'Unable to retrieve location.';
				setStatus('Could not get location: ' + reason);
				btn.disabled = false;
			},
			{ enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
		);
	});
}

document.addEventListener('DOMContentLoaded', function () {
	initVenueFinderGeo();
	const search = document.getElementById('event-search');
	const grid = document.querySelector('.card-grid');
	if (!search || !grid) return;


	const noResults = document.createElement('div');
	noResults.className = 'no-results';
	noResults.textContent = 'No events found';
	noResults.style.padding = '1rem';
	noResults.style.color = 'var(--muted)';
	noResults.style.display = 'none';
	noResults.setAttribute('role', 'status');
	noResults.setAttribute('aria-live', 'polite');
	grid.parentNode.insertBefore(noResults, grid.nextSibling);

	const cards = () => Array.from(grid.querySelectorAll('.card'));

	search.addEventListener('input', function (e) {
		const q = (e.target.value || '').trim().toLowerCase();
		let any = false;
		cards().forEach(card => {
			const text = card.textContent.toLowerCase();
			const match = q === '' || text.indexOf(q) !== -1;
			card.style.display = match ? '' : 'none';
			if (match) any = true;
		});
		noResults.style.display = any ? 'none' : '';
	});
});

