<?php
/**
 * AI Provider Hub admin page.
 *
 * @var string               $title
 * @var list<array<string,mixed>> $cards
 * @var array<string,mixed>  $dashboard
 * @var list<array<string,mixed>> $logs
 * @var string               $mask
 *
 * @package RecipeSeoAiPro
 */

use RecipeSeoAiPro\Views\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title     = isset( $title ) ? (string) $title : __( 'AI Provider Hub', 'recipe-seo-ai-pro' );
$cards     = isset( $cards ) && is_array( $cards ) ? $cards : array();
$dashboard = isset( $dashboard ) && is_array( $dashboard ) ? $dashboard : array();
$logs      = isset( $logs ) && is_array( $logs ) ? $logs : array();
$mask      = isset( $mask ) ? (string) $mask : '••••••••';

View::render(
	'partials/header',
	array(
		'title'       => $title,
		'hide_sidebar'=> true,
	)
);

$logo_map = array(
	'openai'       => 'dashicons-cloud',
	'deepseek'     => 'dashicons-search',
	'gemini'       => 'dashicons-google',
	'anthropic'    => 'dashicons-buddicons-replies',
	'openrouter'   => 'dashicons-randomize',
	'groq'         => 'dashicons-performance',
	'xai'          => 'dashicons-star-filled',
	'together'     => 'dashicons-groups',
	'mistral'      => 'dashicons-flag',
	'cohere'       => 'dashicons-share',
	'ollama'       => 'dashicons-desktop',
	'lmstudio'     => 'dashicons-laptop',
	'azure_openai' => 'dashicons-cloud',
	'custom'       => 'dashicons-admin-generic',
);
?>

<section class="rsaip-ai-hub-dashboard rsaip-fade-in" aria-labelledby="rsaip-ai-dash-title">
	<div class="rsaip-opt-card-head">
		<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
		<h2 id="rsaip-ai-dash-title"><?php echo esc_html__( 'AI Dashboard', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<div class="rsaip-ai-hub-metrics" id="rsaip-ai-hub-metrics">
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Current Provider', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="current_provider"><?php echo esc_html( (string) ( $dashboard['current_provider'] ?? '—' ) ); ?></strong>
		</div>
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Current Model', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="current_model"><?php echo esc_html( (string) ( $dashboard['current_model'] ?? '—' ) ); ?></strong>
		</div>
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Average Latency', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="average_latency"><?php echo esc_html( (string) (int) ( $dashboard['average_latency'] ?? 0 ) ); ?> ms</strong>
		</div>
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( "Today's Requests", 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="today_requests"><?php echo esc_html( (string) (int) ( $dashboard['today_requests'] ?? 0 ) ); ?></strong>
		</div>
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Token Usage', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="token_usage"><?php echo esc_html( number_format_i18n( (int) ( $dashboard['token_usage'] ?? 0 ) ) ); ?></strong>
		</div>
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Estimated Cost', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="estimated_cost">$<?php echo esc_html( number_format_i18n( (float) ( $dashboard['estimated_cost'] ?? 0 ), 4 ) ); ?></strong>
		</div>
		<div class="rsaip-ai-hub-metric">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Provider Health', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="provider_health"><?php echo esc_html( (string) ( $dashboard['provider_health'] ?? 'unknown' ) ); ?></strong>
		</div>
		<div class="rsaip-ai-hub-metric rsaip-ai-hub-metric-wide">
			<span class="rsaip-ai-hub-metric-label"><?php echo esc_html__( 'Last Error', 'recipe-seo-ai-pro' ); ?></span>
			<strong data-metric="last_error"><?php echo esc_html( (string) ( $dashboard['last_error'] ?? '' ) !== '' ? (string) $dashboard['last_error'] : '—' ); ?></strong>
		</div>
	</div>

	<div class="rsaip-ai-hub-failover">
		<label>
			<input type="checkbox" id="rsaip-ai-failover-enabled" <?php checked( ! empty( $dashboard['failover_enabled'] ) ); ?> />
			<?php echo esc_html__( 'Enable failover (retry once, then backup providers)', 'recipe-seo-ai-pro' ); ?>
		</label>
		<div class="rsaip-ai-hub-failover-chain">
			<label for="rsaip-ai-failover-chain"><?php echo esc_html__( 'Failover order (comma-separated provider ids)', 'recipe-seo-ai-pro' ); ?></label>
			<input
				type="text"
				id="rsaip-ai-failover-chain"
				value="<?php echo esc_attr( implode( ', ', is_array( $dashboard['failover'] ?? null ) ? $dashboard['failover'] : array() ) ); ?>"
				placeholder="deepseek, openrouter"
			/>
			<button type="button" class="button" id="rsaip-ai-failover-save"><?php echo esc_html__( 'Save Failover', 'recipe-seo-ai-pro' ); ?></button>
		</div>
	</div>
</section>

<section class="rsaip-ai-hub-providers rsaip-fade-in" aria-labelledby="rsaip-ai-providers-title">
	<div class="rsaip-opt-card-head">
		<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
		<h2 id="rsaip-ai-providers-title"><?php echo esc_html__( 'AI Providers', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<p class="rsaip-note"><?php echo esc_html__( 'Configure any provider below. Feature modules always talk to one interface — switching providers here applies instantly.', 'recipe-seo-ai-pro' ); ?></p>

	<div class="rsaip-ai-hub-grid" id="rsaip-ai-hub-grid">
		<?php foreach ( $cards as $card ) : ?>
			<?php
			$id     = (string) ( $card['id'] ?? '' );
			$status = (string) ( $card['status'] ?? 'disconnected' );
			$icon   = $logo_map[ $id ] ?? 'dashicons-cloud';
			$active = ! empty( $card['is_active'] );
			?>
			<article
				class="rsaip-ai-provider-card<?php echo $active ? ' is-active' : ''; ?>"
				data-provider="<?php echo esc_attr( $id ); ?>"
				data-status="<?php echo esc_attr( $status ); ?>"
			>
				<header class="rsaip-ai-provider-card-head">
					<span class="rsaip-ai-provider-logo dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
					<div>
						<h3 class="rsaip-ai-provider-name"><?php echo esc_html( (string) ( $card['label'] ?? $id ) ); ?></h3>
						<span class="rsaip-ai-provider-status status-<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( ucfirst( $status ) ); ?>
							<?php if ( $active ) : ?>
								· <?php echo esc_html__( 'Active', 'recipe-seo-ai-pro' ); ?>
							<?php endif; ?>
						</span>
					</div>
				</header>

				<dl class="rsaip-ai-provider-meta">
					<div>
						<dt><?php echo esc_html__( 'Current Model', 'recipe-seo-ai-pro' ); ?></dt>
						<dd class="js-card-model"><?php echo esc_html( (string) ( $card['model'] ?? '—' ) ); ?></dd>
					</div>
					<div>
						<dt><?php echo esc_html__( 'Latency', 'recipe-seo-ai-pro' ); ?></dt>
						<dd class="js-card-latency"><?php echo esc_html( (string) (int) ( $card['latency_ms'] ?? 0 ) ); ?> ms</dd>
					</div>
					<div>
						<dt><?php echo esc_html__( 'Last Check', 'recipe-seo-ai-pro' ); ?></dt>
						<dd class="js-card-last-check"><?php echo esc_html( (string) ( $card['last_check'] ?? '—' ) !== '' ? (string) $card['last_check'] : '—' ); ?></dd>
					</div>
				</dl>

				<div class="rsaip-ai-provider-actions">
					<button type="button" class="button button-primary js-ai-configure"><?php echo esc_html__( 'Configure', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button js-ai-fetch-models"><?php echo esc_html__( 'Fetch Models', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button js-ai-refresh-models"><?php echo esc_html__( 'Refresh Models', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button js-ai-toggle-manual"><?php echo esc_html__( 'Manual Entry', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button js-ai-test"><?php echo esc_html__( 'Test Connection', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button js-ai-activate"><?php echo esc_html__( 'Activate', 'recipe-seo-ai-pro' ); ?></button>
					<button type="button" class="button-link-delete js-ai-disconnect"><?php echo esc_html__( 'Disconnect', 'recipe-seo-ai-pro' ); ?></button>
				</div>

				<form class="rsaip-ai-provider-config" hidden>
					<div class="rsaip-ai-config-grid">
						<label>
							<span><?php echo esc_html__( 'API Key', 'recipe-seo-ai-pro' ); ?></span>
							<input type="password" name="api_key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $card['has_api_key'] ) ? $mask : '' ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Endpoint', 'recipe-seo-ai-pro' ); ?></span>
							<input type="url" name="endpoint" value="<?php echo esc_attr( (string) ( $card['endpoint'] ?? '' ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Selected Model', 'recipe-seo-ai-pro' ); ?></span>
							<input type="text" name="model" class="js-ai-selected-model" value="<?php echo esc_attr( (string) ( $card['model'] ?? '' ) ); ?>" list="rsaip-models-<?php echo esc_attr( $id ); ?>" readonly />
							<datalist id="rsaip-models-<?php echo esc_attr( $id ); ?>"></datalist>
						</label>
						<label class="rsaip-ai-manual-entry" hidden>
							<span><?php echo esc_html__( 'Manual Entry', 'recipe-seo-ai-pro' ); ?></span>
							<input type="text" name="manual_model" class="js-ai-manual-model" value="<?php echo esc_attr( (string) ( $card['manual_model'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'Type a model or deployment name', 'recipe-seo-ai-pro' ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Temperature', 'recipe-seo-ai-pro' ); ?></span>
							<input type="number" step="0.1" min="0" max="2" name="temperature" value="<?php echo esc_attr( (string) ( $card['temperature'] ?? 0.2 ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Top P', 'recipe-seo-ai-pro' ); ?></span>
							<input type="number" step="0.05" min="0" max="1" name="top_p" value="<?php echo esc_attr( (string) ( $card['top_p'] ?? 1 ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Max Tokens', 'recipe-seo-ai-pro' ); ?></span>
							<input type="number" min="64" max="128000" name="max_tokens" value="<?php echo esc_attr( (string) ( $card['max_tokens'] ?? 1200 ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Timeout (seconds)', 'recipe-seo-ai-pro' ); ?></span>
							<input type="number" min="5" max="120" name="timeout" value="<?php echo esc_attr( (string) ( $card['timeout'] ?? 25 ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Organization', 'recipe-seo-ai-pro' ); ?></span>
							<input type="text" name="organization" value="<?php echo esc_attr( (string) ( $card['organization'] ?? '' ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Retry count', 'recipe-seo-ai-pro' ); ?></span>
							<input type="number" min="0" max="5" name="retry_count" value="<?php echo esc_attr( (string) ( $card['retry_count'] ?? 1 ) ); ?>" />
						</label>
						<label>
							<span><?php echo esc_html__( 'Custom Headers (JSON object)', 'recipe-seo-ai-pro' ); ?></span>
							<textarea name="custom_headers" rows="2" placeholder='{"X-Title":"Recipe SEO AI Pro"}'></textarea>
						</label>
						<label class="rsaip-ai-checkbox">
							<input type="checkbox" name="streaming" value="1" <?php checked( ! empty( $card['streaming'] ) ); ?> />
							<span><?php echo esc_html__( 'Prefer streaming when available', 'recipe-seo-ai-pro' ); ?></span>
						</label>
						<label class="rsaip-ai-checkbox">
							<input type="checkbox" name="activate" value="1" <?php checked( $active ); ?> />
							<span><?php echo esc_html__( 'Set as active provider', 'recipe-seo-ai-pro' ); ?></span>
						</label>
					</div>
					<div class="rsaip-ai-config-actions">
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save', 'recipe-seo-ai-pro' ); ?></button>
						<button type="button" class="button js-ai-fetch-models"><?php echo esc_html__( 'Fetch Models', 'recipe-seo-ai-pro' ); ?></button>
						<button type="button" class="button js-ai-refresh-models"><?php echo esc_html__( 'Refresh Models', 'recipe-seo-ai-pro' ); ?></button>
						<button type="button" class="button js-ai-toggle-manual"><?php echo esc_html__( 'Manual Entry', 'recipe-seo-ai-pro' ); ?></button>
						<button type="button" class="button-link js-ai-config-close"><?php echo esc_html__( 'Close', 'recipe-seo-ai-pro' ); ?></button>
					</div>

					<div class="rsaip-ai-model-browser" hidden>
						<div class="rsaip-ai-model-browser-toolbar">
							<input type="search" class="js-ai-model-search" placeholder="<?php echo esc_attr__( 'Search models…', 'recipe-seo-ai-pro' ); ?>" />
							<label class="rsaip-ai-checkbox">
								<input type="checkbox" class="js-ai-fav-only" />
								<span><?php echo esc_html__( 'Favorites only', 'recipe-seo-ai-pro' ); ?></span>
							</label>
							<span class="js-ai-model-meta rsaip-ai-model-meta"></span>
						</div>
						<div class="rsaip-ai-model-error js-ai-model-error" hidden></div>
						<div class="rsaip-ai-model-list js-ai-model-list" role="listbox" aria-label="<?php echo esc_attr__( 'Available models', 'recipe-seo-ai-pro' ); ?>"></div>
					</div>

					<pre class="rsaip-ai-test-result" hidden></pre>
				</form>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<section class="rsaip-ai-hub-logs rsaip-fade-in" aria-labelledby="rsaip-ai-logs-title">
	<div class="rsaip-opt-card-head">
		<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
		<h2 id="rsaip-ai-logs-title"><?php echo esc_html__( 'AI Logs', 'recipe-seo-ai-pro' ); ?></h2>
	</div>
	<p class="rsaip-note"><?php echo esc_html__( 'API keys are never stored in logs.', 'recipe-seo-ai-pro' ); ?></p>
	<div class="rsaip-table-wrap">
		<table class="widefat striped rsaip-ai-logs-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Time', 'recipe-seo-ai-pro' ); ?></th>
					<th><?php echo esc_html__( 'Provider', 'recipe-seo-ai-pro' ); ?></th>
					<th><?php echo esc_html__( 'Model', 'recipe-seo-ai-pro' ); ?></th>
					<th><?php echo esc_html__( 'Latency', 'recipe-seo-ai-pro' ); ?></th>
					<th><?php echo esc_html__( 'Tokens', 'recipe-seo-ai-pro' ); ?></th>
					<th><?php echo esc_html__( 'Cost', 'recipe-seo-ai-pro' ); ?></th>
					<th><?php echo esc_html__( 'Result', 'recipe-seo-ai-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="7"><?php echo esc_html__( 'No AI requests logged yet.', 'recipe-seo-ai-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['provider'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['model'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) (int) ( $row['latency_ms'] ?? 0 ) ); ?> ms</td>
							<td><?php echo esc_html( (string) (int) ( $row['total_tokens'] ?? 0 ) ); ?></td>
							<td>$<?php echo esc_html( number_format_i18n( (float) ( $row['estimated_cost'] ?? 0 ), 6 ) ); ?></td>
							<td>
								<?php if ( ! empty( $row['success'] ) ) : ?>
									<span class="rsaip-ai-log-ok"><?php echo esc_html__( 'Success', 'recipe-seo-ai-pro' ); ?></span>
								<?php else : ?>
									<span class="rsaip-ai-log-fail" title="<?php echo esc_attr( (string) ( $row['error_message'] ?? '' ) ); ?>"><?php echo esc_html__( 'Failure', 'recipe-seo-ai-pro' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<?php
View::render( 'partials/footer' );
