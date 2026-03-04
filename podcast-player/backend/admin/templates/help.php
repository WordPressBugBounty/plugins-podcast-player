<?php
/**
 * Podcast player documentation page
 *
 * @package Podcast Player
 * @since 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$doc_sections = array(
	array(
		'title' => esc_html__( 'Getting Started', 'podcast-player' ),
		'items' => array(
			array(
				'label' => esc_html__( 'Introduction to Podcast Player', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17187',
			),
			array(
				'label' => esc_html__( 'How to install Podcast Player', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17188',
			),
			array(
				'label' => esc_html__( 'How to get your podcast feed URL', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17225',
			),
		),
	),
	array(
		'title' => esc_html__( 'Display Podcast on Your Site', 'podcast-player' ),
		'items' => array(
			array(
				'label' => esc_html__( 'Podcast Player Shortcode', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17191',
			),
			array(
				'label' => esc_html__( 'Podcast Player Editor Block', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17189',
			),
			array(
				'label' => esc_html__( 'Elementor Page Builder', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17190',
			),
			array(
				'label' => esc_html__( 'Podcast Player Widget', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17192',
			),
			array(
				'label' => esc_html__( 'Display Single Episode with Free Version', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17193',
			),
		),
	),
	array(
		'title' => esc_html__( 'Settings & Configuration', 'podcast-player' ),
		'items' => array(
			array(
				'label' => esc_html__( 'Podcast Player Admin Settings', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17194',
			),
			array(
				'label' => esc_html__( 'Update Podcast Feed Data', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17195',
			),
		),
	),
	array(
		'title' => esc_html__( 'Podcast Player Pro', 'podcast-player' ),
		'items' => array(
			array(
				'label' => esc_html__( 'Why upgrade to the Pro plugin?', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17196',
			),
			array(
				'label' => esc_html__( 'Installing the Pro plugin', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17197',
			),
			array(
				'label' => esc_html__( 'What is new after installing Pro', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17198',
			),
			array(
				'label' => esc_html__( 'Podcast Player Statistics', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17200',
			),
			array(
				'label' => esc_html__( 'Display Player from WP Posts', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17201',
			),
			array(
				'label' => esc_html__( 'Display Player from Audio URL', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17202',
			),
			array(
				'label' => esc_html__( 'Shortcode from Feed URL', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17203',
			),
			array(
				'label' => esc_html__( 'Shortcode from Posts', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17204',
			),
			array(
				'label' => esc_html__( 'Shortcode from Audio', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17205',
			),
		),
	),
	array(
		'title' => esc_html__( 'Troubleshooting & FAQ', 'podcast-player' ),
		'items' => array(
			array(
				'label' => esc_html__( 'Podcast player not getting updated', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17206',
			),
			array(
				'label' => esc_html__( 'Podcast player is not showing on front-end', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17207',
			),
			array(
				'label' => esc_html__( 'Play button is not playing the episode', 'podcast-player' ),
				'url'   => 'https://easypodcastpro.com/docs7/?easyDocId=17208',
			),
		),
	),
);
?>
<div class="space-y-6">
	<section class="rounded-xl border border-slate-200 bg-slate-50 p-6 sm:p-7">
		<p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"><?php esc_html_e( 'Documentation', 'podcast-player' ); ?></p>
		<h2 class="m-0 text-2xl font-semibold text-slate-900 sm:text-[30px]"><?php esc_html_e( 'Help & Support', 'podcast-player' ); ?></h2>
		<p class="mb-0 mt-3 max-w-3xl text-base text-slate-700"><?php esc_html_e( 'Everything you need to launch, configure, and troubleshoot Podcast Player. Start with setup basics, then move to display methods and advanced tools.', 'podcast-player' ); ?></p>
		<div class="mt-5 flex flex-wrap gap-2">
			<a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 no-underline hover:border-slate-400 hover:text-slate-900" href="https://easypodcastpro.com/docs7/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Full Docs', 'podcast-player' ); ?></a>
			<a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 no-underline hover:border-slate-400 hover:text-slate-900" href="https://wordpress.org/support/plugin/podcast-player/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support Forum', 'podcast-player' ); ?></a>
			<a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 no-underline hover:border-slate-400 hover:text-slate-900" href="https://easypodcastpro.com/contact-us-2/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contact Team', 'podcast-player' ); ?></a>
		</div>
	</section>

	<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
		<?php foreach ( $doc_sections as $section ) : ?>
			<section class="rounded-lg border border-solid border-slate-300 bg-white p-5 sm:p-6">
				<div class="mb-4 border-b border-slate-200 pb-3">
					<h3 class="m-0 text-lg font-semibold text-slate-900"><?php echo esc_html( $section['title'] ); ?></h3>
				</div>
				<ul class="space-y-2">
					<?php foreach ( $section['items'] as $item ) : ?>
						<li class="mb-0">
							<a class="inline-flex text-sm font-medium text-sky-700 no-underline hover:text-sky-900 hover:underline" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $item['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
</div>
