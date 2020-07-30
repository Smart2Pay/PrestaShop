/**
 * 2015 Smart2Pay
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this plugin
 * in the future.
 *
 * @author    Smart2Pay
 * @copyright 2015 Smart2Pay
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 **/
var initialEnvironment = null;

function activateTabAndPanel(thisElem) {
    thisElem.parent().addClass('active').siblings().removeClass('active');
    var panel = thisElem.attr('data-panel');
    $('#' + panel).show().siblings().hide();
}

function activateTheProperTab() {
    var tabHash = window.location.hash, tab;
    if (!tabHash.length) {
        tab = $('#navigation').children(':first').children(':first');
    } else {
        tab = $('a[href=' + tabHash + ']');
    }
    setTimeout(function () {
        tab.click();
    }, 1);
}

function checkEnvironment() {
    initialEnvironment = $('input[name=S2P_ENV]:checked').val();
    switch (initialEnvironment) {
        case 'demo':
            $('.create-account-notification').show();
            break;

        case 'test':
            $('.env-test').show();
            $('.return-url').show();
            break;

        case 'live':
            $('.kyc-notification').show();
            $('.env-live').show();
            $('.return-url').show();
            break;
    }
}

function toggleEnvironment() {
    $('.smart2pay_radio ').click(function () {
        var value = $('input[name=S2P_ENV]:checked').val();
        switch (value) {
            case 'demo':
                $('.env-test, .env-live, .kyc-notification, .return-url').slideUp(200, function () {
                    $('.create-account-notification').slideDown(200);
                });
                break;

            case 'test':
                $('.create-account-notification, .env-live, .kyc-notification').slideUp(200, function () {
                    $('.env-test').slideDown(200);
                    $('.return-url').slideDown(200);
                });
                break;

            case 'live':
                $('.create-account-notification, .env-test').slideUp(200, function () {
                    $('.env-live').slideDown(200);
                    $('.kyc-notification').slideDown(200);
                    $('.return-url').slideDown(200);
                });
                break;
        }

        if (value !== initialEnvironment) {
            $('.change-env-notification').slideDown(200);
        } else {
            $('.change-env-notification').slideUp(200);
        }

    });
}

$(document).ready(function () {
    if (!$('#navigation').children('.active').length) {
        activateTheProperTab();
    } else {
        $('#navigation').children('.active').click();
    }

    $('.nav-tabs a').click(function () {
        activateTabAndPanel($(this));
    });

    $('.smart2pay_radio').toggleInput();

    checkEnvironment();
    toggleEnvironment();
});