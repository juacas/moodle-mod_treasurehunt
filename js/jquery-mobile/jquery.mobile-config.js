define(['jquery'], function ($) {
    $(document).on('mobileinit', function () {
        console.log('mobileinit');
        $.mobile.autoInitializePage = false;
        $.mobile.defaultPageTransition = "none";
    });
    $(document).on('pagebeforecreate', function () {
        console.log('pagebeforecreate');
    });
    $(document).on('pagecontainershow', function () {
        console.log('pagecontainershow');
    });
});
