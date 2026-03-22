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
