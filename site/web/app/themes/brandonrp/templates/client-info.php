<div class="clients-wrapper">
    <?php while ( have_rows('client_info') ) : the_row();
        $type = get_sub_field('client_type');
        $name = get_sub_field('client_name');
        $url = get_sub_field('client_url');
        $logo = get_sub_field('client_logo');
        ?>

        <?php if($type == 'Studio'): ?>
            <div class="client studio">
                <span class="label"><?php echo $type; ?></span>
                <?php if($url): ?><a href="http://<?php echo $url;?>" target="_blank" title="<?php echo $name;?>"><?php echo $name; ?></a>
                <?php else: ?><span><?php echo $name; ?></span><?php endif;?>
            </div>
        <?php else: ?>
            <div class="client">
                <span class="label"><?php echo $type; ?></span>
                <?php if($url): ?><a href="http://<?php echo $url;?>" target="_blank" title="<?php echo $name;?>"><?php echo $name; ?></a>
                <?php else: ?><span><?php echo $name; ?></span><?php endif;?>
            </div>
        <?php endif; ?>

    <?php endwhile; ?>
</div>