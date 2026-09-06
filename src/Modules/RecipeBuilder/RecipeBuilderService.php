<?php
declare(strict_types=1);

/**
 * Recipe Builder 2.0 orchestrator (Phase 5.1).
 *
 * Isolated from legacy Recipe Engine / card rendering.
 *
 * @package RecipeSeoAiPro
 */



namespace RecipeSeoAiPro\Modules\RecipeBuilder;

use RecipeSeoAiPro\Contracts\CacheInterface;
use RecipeSeoAiPro\Contracts\EventDispatcherInterface;
use RecipeSeoAiPro\Contracts\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecipeBuilderService
 */
final class RecipeBuilderService {

	private RecipeBuilderRepository $repository;

	private RecipeSectionManager $sections;

	private RecipeIngredientManager $ingredients;

	private RecipeStepManager $steps;

	private CacheInterface $cache;

	private LoggerInterface $logger;

	private EventDispatcherInterface $events;

	public function __construct(
		RecipeBuilderRepository $repository,
		RecipeSectionManager $sections,
		RecipeIngredientManager $ingredients,
		RecipeStepManager $steps,
		CacheInterface $cache,
		LoggerInterface $logger,
		EventDispatcherInterface $events
	) {
		$this->repository  = $repository;
		$this->sections    = $sections;
		$this->ingredients = $ingredients;
		$this->steps       = $steps;
		$this->cache       = $cache;
		$this->logger      = $logger;
		$this->events      = $events;
	}

	public function ensure_tables(): void {
		if ( class_exists( 'RSAIP_DB' ) ) {
			\RSAIP_DB::migrate_recipe_builder_tables();
		}
	}

	/**
	 * @return RecipeBuilderDTO|\WP_Error
	 */
	public function get( int $id ) {
		$this->ensure_tables();
		$row = $this->repository->find_recipe( $id );
		if ( ! $row ) {
			return new \WP_Error( 'rsaip_rb_missing', 'Recipe not found.', array( 'status' => 404 ) );
		}
		return $this->hydrate( $row );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function list_recipes( int $limit = 50 ): array {
		$this->ensure_tables();
		$key = 'rsaip_rb_list_' . $limit;
		$cached = $this->cache->get( $key, null );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$list = $this->repository->list_recipes( $limit );
		$this->cache->set( $key, $list, 20 );
		return $list;
	}

	/**
	 * Create or update a full recipe graph.
	 *
	 * @param array<string, mixed> $payload Recipe payload.
	 * @return RecipeBuilderDTO|\WP_Error
	 */
	public function save( array $payload, int $user_id = 0 ) {
		$this->ensure_tables();
		$title = sanitize_text_field( (string) ( $payload['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'rsaip_rb_title', 'Recipe title is required.' );
		}

		$prep  = absint( $payload['prep_time'] ?? 0 );
		$cook  = absint( $payload['cook_time'] ?? 0 );
		$total = absint( $payload['total_time'] ?? 0 );
		if ( $total <= 0 ) {
			$total = $prep + $cook;
		}

		$equipment = $payload['equipment'] ?? array();
		if ( is_string( $equipment ) ) {
			$parts     = preg_split( '/[\n,]+/', $equipment ) ?: array();
			$equipment = $parts;
		}
		$equip_clean = array();
		if ( is_array( $equipment ) ) {
			foreach ( $equipment as $item ) {
				if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) {
					$equip_clean[] = sanitize_text_field( (string) $item );
				}
			}
		}

		$now = \RSAIP_DB::now_gmt_sql();
		$row = array(
			'post_id'     => absint( $payload['post_id'] ?? 0 ),
			'title'       => mb_substr( $title, 0, 255 ),
			'description' => sanitize_textarea_field( (string) ( $payload['description'] ?? '' ) ),
			'servings'    => max( 0.25, (float) ( $payload['servings'] ?? 4 ) ),
			'prep_time'   => $prep,
			'cook_time'   => $cook,
			'total_time'  => $total,
			'notes'       => sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
			'tips'        => sanitize_textarea_field( (string) ( $payload['tips'] ?? '' ) ),
			'equipment'   => (string) wp_json_encode( $equip_clean ),
			'unit_system' => ( (string) ( $payload['unit_system'] ?? 'metric' ) ) === 'imperial' ? 'imperial' : 'metric',
			'status'      => sanitize_key( (string) ( $payload['status'] ?? 'draft' ) ) ?: 'draft',
			'updated_at'  => $now,
		);

		$id = absint( $payload['id'] ?? 0 );
		if ( $id > 0 && $this->repository->find_recipe( $id ) ) {
			$this->repository->update_recipe( $id, $row );
		} else {
			$row['user_id']    = max( 0, $user_id );
			$row['created_at'] = $now;
			$id                = $this->repository->insert_recipe( $row );
			if ( $id <= 0 ) {
				return new \WP_Error( 'rsaip_rb_save', 'Could not save recipe.' );
			}
		}

		$sections_in = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();
		$saved_sec   = $this->sections->sync( $id, $sections_in );
		$section_map = array();
		foreach ( $saved_sec as $sec ) {
			if ( ( $sec['client_key'] ?? '' ) !== '' ) {
				$section_map[ (string) $sec['client_key'] ] = (int) $sec['id'];
			}
		}

		$ings = isset( $payload['ingredients'] ) && is_array( $payload['ingredients'] ) ? $payload['ingredients'] : array();
		$stps = isset( $payload['steps'] ) && is_array( $payload['steps'] ) ? $payload['steps'] : array();
		$this->ingredients->sync( $id, $ings, $section_map );
		$this->steps->sync( $id, $stps );

		$this->cache->delete( 'rsaip_rb_list_50' );
		$this->events->dispatch( 'rsaip.recipe_builder.saved', array( 'id' => $id ) );
		$this->logger->info( 'recipe_builder.saved', array( 'id' => $id ) );

		return $this->get( $id );
	}

	/**
	 * @return true|\WP_Error
	 */
	public function delete( int $id ) {
		$this->ensure_tables();
		if ( ! $this->repository->find_recipe( $id ) ) {
			return new \WP_Error( 'rsaip_rb_missing', 'Recipe not found.', array( 'status' => 404 ) );
		}
		if ( ! $this->repository->delete_recipe( $id ) ) {
			return new \WP_Error( 'rsaip_rb_delete', 'Could not delete recipe.' );
		}
		$this->cache->delete( 'rsaip_rb_list_50' );
		$this->events->dispatch( 'rsaip.recipe_builder.deleted', array( 'id' => $id ) );
		return true;
	}

	/**
	 * Preview helpers: scale + optional unit system (does not persist).
	 *
	 * @param array<string, mixed> $options servings, unit_system.
	 * @return RecipeBuilderDTO|\WP_Error
	 */
	public function preview( int $id, array $options = array() ) {
		$dto = $this->get( $id );
		if ( is_wp_error( $dto ) ) {
			return $dto;
		}
		$target_servings = isset( $options['servings'] ) ? (float) $options['servings'] : $dto->servings;
		if ( $target_servings > 0 && abs( $target_servings - $dto->servings ) > 0.001 ) {
			$dto->ingredients = $this->ingredients->scale_for_servings( $dto->ingredients, $dto->servings, $target_servings );
			$dto->servings    = $target_servings;
		}
		$system = (string) ( $options['unit_system'] ?? '' );
		if ( $system !== '' && $system !== $dto->unit_system ) {
			$dto->ingredients = $this->ingredients->convert_system( $dto->ingredients, $system );
			$dto->unit_system = $system === 'imperial' ? 'imperial' : 'metric';
		}
		return $dto;
	}

	/**
	 * Live preview HTML for the builder UI only (not public card renderer).
	 */
	public function render_preview_html( RecipeBuilderDTO $dto ): string {
		$total = $dto->total_time > 0 ? $dto->total_time : ( $dto->prep_time + $dto->cook_time );
		$html  = '<div class="rsaip-rb-preview">';
		$html .= '<h2>' . esc_html( $dto->title !== '' ? $dto->title : __( 'Untitled recipe', 'recipe-seo-ai-pro' ) ) . '</h2>';
		if ( $dto->description !== '' ) {
			$html .= '<p class="rsaip-rb-preview-desc">' . esc_html( $dto->description ) . '</p>';
		}
		$html .= '<ul class="rsaip-rb-preview-meta">';
		$html .= '<li>' . esc_html__( 'Servings', 'recipe-seo-ai-pro' ) . ': ' . esc_html( (string) $dto->servings ) . '</li>';
		$html .= '<li>' . esc_html__( 'Prep', 'recipe-seo-ai-pro' ) . ': ' . esc_html( (string) $dto->prep_time ) . ' min</li>';
		$html .= '<li>' . esc_html__( 'Cook', 'recipe-seo-ai-pro' ) . ': ' . esc_html( (string) $dto->cook_time ) . ' min</li>';
		$html .= '<li>' . esc_html__( 'Total', 'recipe-seo-ai-pro' ) . ': ' . esc_html( (string) $total ) . ' min</li>';
		$html .= '</ul>';

		$by_section = array();
		foreach ( $dto->ingredients as $ing ) {
			$sid = (string) (int) ( $ing['section_id'] ?? 0 );
			$by_section[ $sid ][] = $ing;
		}
		$html .= '<h3>' . esc_html__( 'Ingredients', 'recipe-seo-ai-pro' ) . '</h3>';
		if ( $dto->sections ) {
			foreach ( $dto->sections as $sec ) {
				if ( ( $sec['section_type'] ?? '' ) === 'steps' ) {
					continue;
				}
				$sid = (string) (int) ( $sec['id'] ?? 0 );
				$html .= '<h4>' . esc_html( (string) ( $sec['title'] ?? '' ) ) . '</h4><ul>';
				foreach ( $by_section[ $sid ] ?? array() as $ing ) {
					$html .= '<li>' . esc_html( trim( ( $ing['quantity'] ?? '' ) . ' ' . ( $ing['unit'] ?? '' ) . ' ' . ( $ing['name'] ?? '' ) . ( ! empty( $ing['note'] ) ? ' (' . $ing['note'] . ')' : '' ) ) ) . '</li>';
				}
				$html .= '</ul>';
			}
		}
		if ( ! empty( $by_section['0'] ) ) {
			$html .= '<ul>';
			foreach ( $by_section['0'] as $ing ) {
				$html .= '<li>' . esc_html( trim( ( $ing['quantity'] ?? '' ) . ' ' . ( $ing['unit'] ?? '' ) . ' ' . ( $ing['name'] ?? '' ) ) ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '<h3>' . esc_html__( 'Steps', 'recipe-seo-ai-pro' ) . '</h3><ol>';
		foreach ( $dto->steps as $step ) {
			$html .= '<li>';
			$html .= esc_html( (string) ( $step['instruction'] ?? '' ) );
			if ( ! empty( $step['image_url'] ) ) {
				$html .= '<br><img src="' . esc_url( (string) $step['image_url'] ) . '" alt="" style="max-width:180px;height:auto;" />';
			}
			$html .= '</li>';
		}
		$html .= '</ol>';

		if ( $dto->equipment ) {
			$html .= '<h3>' . esc_html__( 'Equipment', 'recipe-seo-ai-pro' ) . '</h3><ul>';
			foreach ( $dto->equipment as $eq ) {
				$html .= '<li>' . esc_html( (string) $eq ) . '</li>';
			}
			$html .= '</ul>';
		}
		if ( $dto->tips !== '' ) {
			$html .= '<h3>' . esc_html__( 'Tips', 'recipe-seo-ai-pro' ) . '</h3><p>' . esc_html( $dto->tips ) . '</p>';
		}
		if ( $dto->notes !== '' ) {
			$html .= '<h3>' . esc_html__( 'Notes', 'recipe-seo-ai-pro' ) . '</h3><p>' . esc_html( $dto->notes ) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 */
	private function hydrate( array $row ): RecipeBuilderDTO {
		$dto              = new RecipeBuilderDTO();
		$dto->id          = (int) ( $row['id'] ?? 0 );
		$dto->post_id     = (int) ( $row['post_id'] ?? 0 );
		$dto->title       = (string) ( $row['title'] ?? '' );
		$dto->description = (string) ( $row['description'] ?? '' );
		$dto->servings    = (float) ( $row['servings'] ?? 4 );
		$dto->prep_time   = (int) ( $row['prep_time'] ?? 0 );
		$dto->cook_time   = (int) ( $row['cook_time'] ?? 0 );
		$dto->total_time  = (int) ( $row['total_time'] ?? 0 );
		$dto->notes       = (string) ( $row['notes'] ?? '' );
		$dto->tips        = (string) ( $row['tips'] ?? '' );
		$dto->unit_system = (string) ( $row['unit_system'] ?? 'metric' );
		$dto->status      = (string) ( $row['status'] ?? 'draft' );
		$dto->user_id     = (int) ( $row['user_id'] ?? 0 );
		$dto->created_at  = (string) ( $row['created_at'] ?? '' );
		$dto->updated_at  = (string) ( $row['updated_at'] ?? '' );

		$equip = json_decode( (string) ( $row['equipment'] ?? '[]' ), true );
		$dto->equipment = is_array( $equip ) ? array_values( array_map( 'strval', $equip ) ) : array();

		$dto->sections    = $this->sections->list_for( $dto->id );
		$dto->ingredients = $this->ingredients->list_for( $dto->id );
		$dto->steps       = $this->steps->list_for( $dto->id );
		return $dto;
	}
}
