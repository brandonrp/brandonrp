function homeGalleryCarousel() {
  var $carousels = jQuery('.home-gallery-carousel');

  if (!$carousels.length) {
    return;
  }

  var intervalMs = 3000;

  $carousels.each(function() {
    var $slides = jQuery(this).find('.featured-image');

    if ($slides.length < 2) {
      return;
    }

    var index = 0;

    setInterval(function() {
      $slides.eq(index).removeClass('is-active');
      index = (index + 1) % $slides.length;
      $slides.eq(index).addClass('is-active');
    }, intervalMs);
  });
}
