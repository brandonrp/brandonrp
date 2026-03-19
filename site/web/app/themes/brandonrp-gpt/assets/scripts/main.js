/* ========================================================================
 * DOM-based Routing
 * Based on http://goo.gl/EUTi53 by Paul Irish
 *
 * Only fires on body classes that match. If a body class contains a dash,
 * replace the dash with an underscore when adding it to the object below.
 *
 * .noConflict()
 * The routing is enclosed within an anonymous function so that you can
 * always reference jQuery with $, even when in .noConflict() mode.
 *
 * Google CDN, Latest jQuery
 * To use the default WordPress version of jQuery, go to lib/config.php and
 * remove or comment out: add_theme_support('jquery-cdn');
 * ======================================================================== */
window.nav = window.nav || {};   // prevent ReferenceError

(function(jQuery) {

  // Use this variable to set up the common and page specific functions. If you
  // rename this variable, you will also need to rename the namespace below.
  var Sage = {
    // All pages
    'common': {
      init: function() {
        // JavaScript to be fired on all pages
        if (typeof nav.setup === 'function') nav.setup();
        modal.open('a[href*="#contact"]');
        // Tag image links inside entry content for Fancybox.
        // Fancybox binds to `[data-fancybox]` click events.
        jQuery('.entry-content a').each(function() {
          var $a = jQuery(this);
          var href = $a.attr('href') || '';

          // Typical case: gallery/attachment links that contain an <img>.
          // Fallback: anchors that link directly to an image file.
          var hasImg = $a.find('img').length > 0;
          var isImageHref = /\.(jpe?g|png|gif|webp)(\\?.*)?$/i.test(href);

          if (hasImg || isImageHref) {
            $a.attr('data-fancybox', 'projects');
          }
        });
      }
    },
    'home': {
      init: function() {
        function resetHeight(){
          // reset the body height to that of the inner browser
          $docHeight = jQuery(".brand.main-header").outerHeight();
          jQuery(".main-wrapper").height($docHeight + 'px');
        }

        if (isMobile) {
          // reset the height whenever the window's resized
          window.addEventListener("resize", resetHeight);
          // called to initially set the height.
          resetHeight();
        }
      }
    },
    'single': {
      init: function() {
        wrapVideoEmbed();
      }
    },
    'archive': {
      init: function() {
        imageLoader();

        jQuery(window).on('load resize orientationChange', function(event) {
          if(jQuery('.project-filters').length && jQuery('.project-filters').css('display') != 'none') {
            jQuery('main.main').css( {'margin-top': jQuery('.project-filters').outerHeight()} );
          } else {
            jQuery('main.main').css( {'margin-top': 0} );
          }
        });
      }
    },
    'blog': {
      init: function() {
        imageLoader();
      }
    }

  };

  // The routing fires all common scripts, followed by the page specific scripts.
  // Add additional events for more control over timing e.g. a finalize event
  var UTIL = {
    fire: function(func, funcname, args) {
      var fire;
      var namespace = Sage;
      funcname = (funcname === undefined) ? 'init' : funcname;
      fire = func !== '';
      fire = fire && namespace[func];
      fire = fire && typeof namespace[func][funcname] === 'function';

      if (fire) {
        namespace[func][funcname](args);
      }
    },
    loadEvents: function() {
      // Fire common init JS
      UTIL.fire('common');

      // Fire page-specific init JS, and then finalize JS
      jQuery.each(document.body.className.replace(/-/g, '_').split(/\s+/), function(i, classnm) {
        UTIL.fire(classnm);
        UTIL.fire(classnm, 'finalize');
      });

      // Fire common finalize JS
      UTIL.fire('common', 'finalize');
    }
  };

  // Load Events
  jQuery(document).ready(UTIL.loadEvents);

})(jQuery); // Fully reference jQuery after this point.
