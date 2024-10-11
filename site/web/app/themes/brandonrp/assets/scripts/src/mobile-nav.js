var nav = {
  toggle: jQuery('.nav-toggle, .main-nav a'),
  toggle2: jQuery('body'),
  wrapper: jQuery('.main-header'),
  activeClass: 'menu-active',
  setup: function () {

    window.navFlag = false;

    if (isMobile) {
      // Prevent the logo from firing anything
      jQuery('.icon-logo').on('click touchstart', function (event) {
        event.preventDefault();
      });

      // iOS double tap bug fix
      jQuery('nav a').on('click touchend', function() {
        var el = jQuery(this);
        window.location = el.attr('href');
      });
    }

    nav.toggle.on('click touchend', function (event) {

      event.stopPropagation();

      if(isMobile) {
        event.preventDefault();
      }

      if (window.navFlag === false) {

        if(jQuery('.nav-toggle').is(':visible')) {
          // Open the nav
          setTimeout(function(){
            nav.wrapper.addClass(nav.activeClass);

            // Set flag to open
            window.navFlag = true;
          }, 100);

          // Delay close button transition
          nav.toggle.hide(0, function () {
            setTimeout(function () {
              nav.toggle.fadeIn(0, function () {});
            }, 500);
          });

          // Disable content so user cant scroll it
          jQuery('.main-wrapper').addClass('disabled');
          jQuery('body, html').css({
            'overflow-y': 'hidden',
            'position': 'relative'
          });
        }

        jQuery(window).on('touchmove', function(){
          if(window.navFlag === true){
            return false;
          }
        });
      } else {

        // Close the nav
        setTimeout(function(){
          nav.wrapper.removeClass(nav.activeClass);

          // Set flag to closed
          window.navFlag = false;

        }, 100);

        // Allow content interaction
        jQuery('.main-wrapper').removeClass('disabled');
        jQuery('body, html').css({
          'overflow-y': '',
          'position': ''
        });
      }

      nav.toggle2.on('click touchend', function (event) {
        if(isMobile) {
          event.preventDefault();
        }

        if (window.navFlag === true) {
          // Close the nav
          setTimeout(function () {
            nav.wrapper.removeClass(nav.activeClass);

            // Set flag to closed
            window.navFlag = false;

          }, 100);

          // Allow content interaction
          jQuery('.main-wrapper').removeClass('disabled');
          jQuery('body, html').css({
            'overflow-y': '',
            'position': ''
          });
        }
      });
    });
  }
};
