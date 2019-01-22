$(function() {
    function setPrivacyLevel(id, level, label) {
        const url = phyxo_root_url + 'ws.php?method=pwg.images.setPrivacyLevel';

        const fetch_params = {
            method: 'POST',
            mode: 'same-origin',
            credentials: 'same-origin',
            headers: new Headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ image_id: id, level: level })
        };

        fetch(url, fetch_params)
            .then(response => response.json())
            .then(json => {
                $('#dropdownPermissions').html(label);
                $('.permission-li').removeClass('active');
                $('#permission-' + level).addClass('active');
            })
            .catch(err => console.log(err));
    }

    function addToCaddie(aElement, image_id) {
        const url = phyxo_root_url + `ws.php?method=pwg.caddie.add&image_id=${image_id}`;

        const fetch_params = {
            method: 'GET',
            mode: 'same-origin',
            credentials: 'same-origin'
        };

        fetch(url, fetch_params)
            .then(response => response.json())
            .then(json => {
                aElement.disabled = false;
            })
            .catch(err => console.log(err));
    }

    function updateRating(image_id, rate) {
        const url = phyxo_root_url + 'ws.php?method=pwg.images.rate';

        const fetch_params = {
            method: 'POST',
            mode: 'same-origin',
            credentials: 'same-origin',
            headers: new Headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ image_id: image_id, rate: rate })
        };

        fetch(url, fetch_params)
            .then(response => response.json())
            .then(json => json.result)
            .then(rating => {
                $('#updateRate').html(phyxo_update_your_rating);
                $('#ratingScore').html(rating.score);
                let count_message = '';
                if (rating.count == 1) {
                    count_message = phyxo_rating_1.replace('%d', rating.count);
                } else {
                    count_message = phyxo_ratings.replace('%d', rating.count);
                }
                $('#ratingCount').html(' (' + count_message + ')');
            })
            .catch(err => console.log(err));
    }

    $('#rateForm input[name="rating"]').change(function(e) {
        updateRating(phyxo_image_id, this.value);
    });

    $('[data-action="setPrivacyLevel"]').click(function(e) {
        setPrivacyLevel($(this).data('id'), $(this).data('level'), $(this).data('label'));
    });

    $('[data-action="addToCaddie"]').click(function(e) {
        e.preventDefault();
        addToCaddie($(this), $(this).data('id'));
    });
});
