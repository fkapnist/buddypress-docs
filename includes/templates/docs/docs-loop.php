<?php if ( have_posts() ) : ?>

	<ul>
	<?php while ( have_posts() ) : the_post() ?>
		<li>
			<h2><?php the_title() ?></h2>
			<?php the_content() ?>
			<?php the_permalink() ?>
		</li>
	<?php endwhile ?>
	</ul>

<?php endif ?>