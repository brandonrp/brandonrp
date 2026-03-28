var nav = {
  activeClass: 'menu-active',
  setup: function () {
    // Debug: verify setup is being called
    if (typeof console !== 'undefined' && console.log) {
      console.log('Nav setup called');
    }

    // Get elements when DOM is ready
    var toggle = jQuery('.nav-toggle');
    var toggle2 = jQuery('body');
    var wrapper = jQuery('.main-header');

    // Debug: verify elements are found
    if (typeof console !== 'undefined' && console.log) {
      console.log('Nav elements found:', {
        toggle: toggle.length,
        wrapper: wrapper.length
      });
    }

    window.navFlag = false;

    if (isMobile) {
      // iOS double tap bug fix
      jQuery('nav a').on('click touchend', function() {
        var el = jQuery(this);
        window.location = el.attr('href');
      });
    }

    // Handle toggle button click
    toggle.on('click touchend', function (event) {
      // Debug: verify click is being registered
      if (typeof console !== 'undefined' && console.log) {
        console.log('Nav toggle clicked');
      }

      event.stopPropagation();
      event.preventDefault();

      if (window.navFlag === false) {
        // Open the nav
        setTimeout(function(){
          wrapper.addClass(nav.activeClass);

          // Set flag to open
          window.navFlag = true;
        }, 100);

        // Disable content so user cant scroll it (mobile only)
        if (isMobile) {
          jQuery('.main-wrapper').addClass('disabled');
          jQuery('body, html').css({
            'overflow-y': 'hidden',
            'position': 'relative'
          });

          jQuery(window).on('touchmove', function(){
            if(window.navFlag === true){
              return false;
            }
          });
        }
      } else {
        // Close the nav
        setTimeout(function(){
          wrapper.removeClass(nav.activeClass);

          // Set flag to closed
          window.navFlag = false;

        }, 100);

        // Allow content interaction
        if (isMobile) {
          jQuery('.main-wrapper').removeClass('disabled');
          jQuery('body, html').css({
            'overflow-y': '',
            'position': ''
          });
        }
      }
    });

    // Handle body click to close menu (mobile only)
    if (isMobile) {
      toggle2.on('click touchend', function (event) {
        // Don't close if clicking on the header or toggle
        if (jQuery(event.target).closest('.main-header').length > 0) {
          return;
        }

        if (window.navFlag === true) {
          // Close the nav
          setTimeout(function () {
            wrapper.removeClass(nav.activeClass);

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
    }
  }
};
