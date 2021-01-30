$('[data-action="appCache"]').on('click', function(e) {
  e.preventDefault();

  const tag_a = this;
  const fetch_params = {
    method: 'GET',
    mode: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  };

  fetch(tag_a.href, fetch_params)
    .then(response => {
      // Cache clear
    	document.location.reload();
    });
});
