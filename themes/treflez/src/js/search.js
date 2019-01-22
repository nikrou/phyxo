import 'selectize';

$(function() {
    $('#authors, #tags, #categories').each(function() {
        $(this).selectize({
            plugins: ['remove_button'],
            maxOptions: $(this).find('option').length
        });
    });
});
