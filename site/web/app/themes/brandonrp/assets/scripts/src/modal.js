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
