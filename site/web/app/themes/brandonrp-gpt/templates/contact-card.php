<div class="contact-card">
  <div class="contact-wrapper">
    <?php // Photo
    $photo = get_field('photo');
    $width = $photo['sizes']['thumbnail-width'];
    $height = $photo['sizes']['thumbnail-height'];

    if( !empty($photo) ): ?>
        <div class="contact-photo">
        <img src="<?php echo $photo['url']; ?>" alt="<?php echo $photo['alt']; ?>" height="<?php echo $height; ?>" width="<?php echo $width; ?>" />
        </div>
    <?php endif; ?>

    <div class="contact-info-wrapper" style="text-align:left; align-items: left;">
    <?php // Name
    if(get_field('name')): ?>
        <div class="contact-info-group">
        <h4 class="contact-info name"><?php the_field('name'); ?></h4>
        </div>
    <?php endif; ?>

    <?php // Email
    if(get_field('e-mail')): ?>
        <div class="contact-info-group">
        <span class="contact-label">Email</span>
        <h4 class="contact-info"><a href="mailto:<?php the_field('e-mail'); ?>"><?php the_field('e-mail'); ?></a></h4>
        </div>
    <?php endif; ?>

    <?php // Email
    if(get_field('resume')): ?>
        <div class="contact-info-group">
            <h4 class="contact-info" style="text-transform: uppercase;"><a href="<?php the_field('resume'); ?>"><span class="contact-label">Download Resume</span></a></h4>
        </div>
    <?php endif; ?>
    </div>
  </div>
</div>
