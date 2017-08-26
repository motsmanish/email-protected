<!DOCTYPE html>
<html <?php language_attributes(); ?>>
		<head>
				<meta charset="<?php bloginfo('charset'); ?>" />
				<meta name="viewport" content="width=device-width" />
				<?php if (get_theme_mod('passenger_favicon')) : ?>
					<link rel="shortcut icon" href="<?php echo get_theme_mod('passenger_favicon'); ?>">
				<?php endif; ?>
				<?php wp_head(); ?>
				<?php do_action('login_enqueue_scripts'); ?>
				<script type="text/javascript">
					try {
						document.getElementById('email_protected_email_field').focus();
					} catch (e) {
					}
					if (typeof wpOnload == 'function')
						wpOnload();
				</script>
		</head>

		<body class="login login-email-protected login-action-email-protected-login wp-core-ui">

				<?php do_action('before'); ?>
				<header id="masthead" class="site-header" role="banner">
						<div class="page hfeed site">
								<?php if (get_theme_mod('passenger_logo')) : ?>
									<div class="site-logo"> <a href="<?php echo esc_url(home_url('/')); ?>" title="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>" rel="home"><img src="<?php echo get_theme_mod('passenger_logo'); ?>" alt="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>"></a> </div>
								<?php else : ?>
									<div class="site-branding">
											<h1 class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
															<?php bloginfo('name'); ?>
													</a></h1>
											<h2 class="site-description">
													<?php bloginfo('description'); ?>
											</h2>
									</div>
								<?php endif; ?>
								<nav id="site-navigation" class="navigation-main" role="navigation">
										<h1 class="menu-toggle anarielgenericon">
												<?php _e('Menu', 'passenger'); ?>
										</h1>
										<div class="screen-reader-text skip-link"><a href="#content" title="<?php esc_attr_e('Skip to content', 'passenger'); ?>">
														<?php _e('Skip to content', 'passenger'); ?>
												</a></div>
										<?php wp_nav_menu(array('theme_location' => 'primary')); ?>
								</nav>
						</div>
				</header>
