<?php
/**
 * Admin: Unified SEO Workspace (Phase 4.2) — navigation hub only.
 *
 * @var string $title
 * @var string $note
 * @var int    $project_id
 * @var string $project_name
 * @var list<array{id:int,name:string}> $projects
 * @var list<array<string,mixed>> $workflow
 * @var list<array<string,mixed>> $widgets
 * @var list<array<string,mixed>> $quick_links
 * @var string $projects_url
 * @var string $research_url
 * @var string $brief_new
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::render(
	'partials/header',
	array(
		'title'        => $title,
		'description'  => $note,
		'project_name' => ! empty( $project_name ) ? (string) $project_name : '',
	)
);

$next_link = ( is_array( $quick_links ) && isset( $quick_links[0] ) ) ? $quick_links[0] : null;
?>

<div class="rsaip-ws-hero rsaip-fade-in">
	<section class="rsaip-panel">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rsaip-actions rsaip-ws-project-form">
			<input type="hidden" name="page" value="rsaip-seo-workspace" />
			<label class="rsaip-inline">
				<?php echo esc_html__( 'Project dashboard', 'recipe-seo-ai-pro' ); ?>
				<select name="project_id" id="rsaip-ws-project" class="rsaip-input" onchange="this.form.submit()">
					<option value="0"><?php echo esc_html__( 'All projects (overview)', 'recipe-seo-ai-pro' ); ?></option>
					<?php foreach ( $projects as $p ) : ?>
						<option value="<?php echo esc_attr( (string) $p['id'] ); ?>" <?php selected( (int) $project_id, (int) $p['id'] ); ?>>
							<?php echo esc_html( (string) $p['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( $project_id > 0 && $project_name !== '' ) : ?>
				<strong class="rsaip-ws-project-name"><?php echo esc_html( $project_name ); ?></strong>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( $projects_url ); ?>"><?php echo esc_html__( 'Manage Projects', 'recipe-seo-ai-pro' ); ?></a>
			<a class="button" href="<?php echo esc_url( $research_url ); ?>"><?php echo esc_html__( 'Keyword Research', 'recipe-seo-ai-pro' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( $brief_new ); ?>"><?php echo esc_html__( 'New Brief', 'recipe-seo-ai-pro' ); ?></a>
		</form>
	</section>

	<section class="rsaip-panel rsaip-ws-next-card">
		<div class="rsaip-ws-next-label">
			<span class="dashicons dashicons-flag" aria-hidden="true"></span>
			<?php echo esc_html__( 'Suggested next action', 'recipe-seo-ai-pro' ); ?>
		</div>
		<?php if ( is_array( $next_link ) ) : ?>
			<h3><?php echo esc_html( (string) $next_link['label'] ); ?></h3>
			<p class="rsaip-note"><?php echo esc_html( (string) $next_link['hint'] ); ?></p>
			<p class="rsaip-actions rsaip-kpi-actions">
				<a class="button button-primary" href="<?php echo esc_url( (string) $next_link['url'] ); ?>">
					<?php echo esc_html__( 'Continue', 'recipe-seo-ai-pro' ); ?>
				</a>
			</p>
		<?php else : ?>
			<div class="rsaip-empty-state">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'You are all set', 'recipe-seo-ai-pro' ); ?></h3>
				<p><?php echo esc_html__( 'Pick a module below to keep your content sprint moving.', 'recipe-seo-ai-pro' ); ?></p>
			</div>
		<?php endif; ?>
	</section>
</div>

<section class="rsaip-panel rsaip-fade-in" aria-labelledby="rsaip-ws-workflow-heading">
	<h2 id="rsaip-ws-workflow-heading"><?php echo esc_html__( 'SEO workflow', 'recipe-seo-ai-pro' ); ?></h2>
	<div class="rsaip-ws-progress" aria-hidden="true"><span class="rsaip-ws-progress-bar"></span></div>
	<ol class="rsaip-ws-workflow">
		<?php
		$i = 0;
		foreach ( $workflow as $step ) :
			$i++;
			?>
			<li class="rsaip-ws-step">
				<a class="rsaip-ws-step-link" href="<?php echo esc_url( (string) $step['url'] ); ?>">
					<span class="rsaip-ws-step-num"><?php echo esc_html( (string) $i ); ?></span>
					<span class="rsaip-ws-step-label"><?php echo esc_html( (string) $step['label'] ); ?></span>
					<?php if ( ! empty( $step['next_label'] ) ) : ?>
						<span class="rsaip-sub">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: next step label */
									__( 'Next: %s', 'recipe-seo-ai-pro' ),
									(string) $step['next_label']
								)
							);
							?>
						</span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ol>
</section>

<section class="rsaip-panel rsaip-fade-in" aria-labelledby="rsaip-ws-modules-heading">
	<h2 id="rsaip-ws-modules-heading">
		<?php
		echo $project_id > 0
			? esc_html__( 'Project modules', 'recipe-seo-ai-pro' )
			: esc_html__( 'Module hub', 'recipe-seo-ai-pro' );
		?>
	</h2>
	<div class="rsaip-ws-widgets">
		<?php foreach ( $widgets as $widget ) : ?>
			<a class="rsaip-ws-widget" href="<?php echo esc_url( (string) $widget['url'] ); ?>">
				<span class="rsaip-ws-widget-label"><?php echo esc_html( (string) $widget['label'] ); ?></span>
				<span class="rsaip-ws-widget-count">
					<?php if ( in_array( (string) $widget['id'], array( 'reports', 'analytics' ), true ) ) : ?>
						<?php echo esc_html__( 'Open', 'recipe-seo-ai-pro' ); ?>
					<?php else : ?>
						<?php echo esc_html( (string) (int) $widget['count'] ); ?>
						<span class="rsaip-sub"><?php echo esc_html( (string) $widget['count_label'] ); ?></span>
					<?php endif; ?>
				</span>
				<span class="rsaip-ws-widget-desc"><?php echo esc_html( (string) $widget['description'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<section class="rsaip-panel rsaip-fade-in" aria-labelledby="rsaip-ws-quick-heading">
	<h2 id="rsaip-ws-quick-heading"><?php echo esc_html__( 'Quick launch', 'recipe-seo-ai-pro' ); ?></h2>
	<ul class="rsaip-ws-quick">
		<?php foreach ( $quick_links as $link ) : ?>
			<li>
				<a href="<?php echo esc_url( (string) $link['url'] ); ?>"><strong><?php echo esc_html( (string) $link['label'] ); ?></strong></a>
				<span class="rsaip-sub"> — <?php echo esc_html( (string) $link['hint'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php
View::render( 'partials/footer' );
