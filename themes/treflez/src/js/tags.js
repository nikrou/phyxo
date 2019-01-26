import './jquery.awesomeCloud';

$(function() {
    if ($('#tagCloudCanvas').length > 0) {
        $('#tagCloudCanvas').awesomeCloud({
            size: {
                grid: 12,
                factor: 0,
                normalize: false
            },
            options: {
                color: 'random-light',
                rotationRatio: 0.4
            },
            color: {
                start: $('#tagCloudGradientStart').css('color'),
                end: $('#tagCloudGradientEnd').css('color')
            },
            font: "'Helvetica Neue',Helvetica,Arial,sans-serif",
            shape: 'circle'
        });
    }
});
