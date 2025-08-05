jQuery(document).ready(function($) {
    $('.ab-test-btn').on('click', function() {
        var variant = $(this).data('variant');

        $.post(ABTest.ajaxurl, {
            action: 'ab_test_click',
            nonce: ABTest.nonce,
            variant: variant
        }, function(response) {
            if (response.success) {
                console.log('Click recorded for variant ' + variant);
            }
        });
    });
});
