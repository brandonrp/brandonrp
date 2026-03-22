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
