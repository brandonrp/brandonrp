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
