// detect if mobile browser. regex -> http://detectmobilebrowsers.com
isMobile = false; //initiate as false

// device detection
if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0,4))) {
  isMobile = true;
}

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

  var modal = {
  modalClass: jQuery('.modal-wrapper'),
  flag: false,
  open: function(link) {
    tempFlag = false;

    jQuery(link).on('click touchstart', function(event) {

      event.preventDefault();

      if(!modal.flag) {
        modal.flag = true;
        tempFlag = modal.flag;
      }

      modal.modalClass.fadeIn('200', function(){
        jQuery('.modal-content .wpcf7').addClass('scale');

        // Disable content so user cant scroll it
        jQuery('.main-wrapper').addClass('disabled');
        jQuery('body, html').css({
          'overflow-y': 'hidden',
          'position': 'relative'
        });

        jQuery(window).on('touchmove', function(){
          if(tempFlag) {
            return false;
          }
        });

        modal.close(modal.flag);
      });
    });

  },
  close: function(flag) {
    modal.modalClass.on('click touchstart', '.modal-close', function() {
      if (flag) {
        modal.modalClass.fadeOut('200', function(){
          jQuery('.modal-content .wpcf7').removeClass('scale');
        });

        // Allow content interaction
        jQuery('.main-wrapper').removeClass('disabled');
        jQuery('body, html').css({
          'overflow-y': '',
          'position': ''
        });
      }

      modal.flag = false;
      tempFlag = false;
    });
  }
};

function portfolioHover() {

  var $hoverImage = $('.portfolio-block .featured-image');
  var $hoverFilterAmount = $hoverImage.css('filter');
  var $triggerElement = $('.portfolio-block .button');

  while($hoverFilterAmount === 'none') {
    $hoverFilterAmount = $hoverImage.css('-webkit-filter');

    if($hoverFilterAmount !== 'none') {
      break;
    }

    $hoverFilterAmount = $hoverImage.css('-moz-filter');

    if($hoverFilterAmount !== 'none') {
      break;
    }

    $hoverFilterAmount = $hoverImage.css('-ms-filter');

    if($hoverFilterAmount !== 'none') {
      break;
    }

    $hoverFilterAmount = $hoverImage.css('-o-filter');
  }

  $triggerElement.hover(
    function() {
      $hoverImage.css({
        'filter':'none',
        '-webkit-filter':'none',
        '-moz-filter':'none',
        '-o-filter':'none',
        '-ms-filter':'none'
      });
    }, function() {
      $hoverImage.css({
        'filter':$hoverFilterAmount,
        '-webkit-filter':$hoverFilterAmount,
        '-moz-filter':$hoverFilterAmount,
        '-o-filter':$hoverFilterAmount,
        '-ms-filter':$hoverFilterAmount
      });
    }
  );

}

function imageLoader() {
  var prefixedOpacity = Modernizr.prefixed('opacity');

  // Opacity is set in CSS on .post-teaser class.
  //$postTeaser.css(prefixedOpacity, 0);

  jQuery('.preload img').each(function(index){
    var $image = jQuery(this);

    if ($image[0].complete) {
      showImage($image);
    }

    $image.on('load', function(){
      showImage($image);
    });
  });
}

function showImage($image) {
  var prefixedOpacity = Modernizr.prefixed('opacity');
  var $postTeaser = jQuery('.post-thumbnail[style*="'+$image.attr('src')+'"]');

  $postTeaser.siblings().css(prefixedOpacity, 1.0);

  setTimeout(function(){
    $postTeaser.css(prefixedOpacity, 1.0);
  }, 100);
}

function wrapVideoEmbed() {
  var $iframe = jQuery('iframe[src*=vimeo], iframe[src*=youtu]');
  var wrapperClass = 'video-wrapper';

  if($iframe){
    $iframe.wrap('<div class="' + wrapperClass + '"></div>');
    jQuery('.'+wrapperClass).unwrap();
  } else {
    return false;
  }
}

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
//# sourceMappingURL=main.js.map
