<?php

class TL_LearnPress_Lesson_Extension {
	private $lti_metadata_repository = null;
	private $section_repository = null;

	private function metadata_repository() {
		if (is_null($this->lti_metadata_repository)) {
			$this->lti_metadata_repository = new TL_LTI_Metadata_Repository();
		}

		return $this->lti_metadata_repository;
	}

	private function section_repository() {
		if (is_null($this->section_repository)) {
			$this->section_repository = new TL_LearnPress_Section_Repository();
		}

		return $this->section_repository;
	}

	private function resolve_course_id_for_lesson($lesson_id = 0, $fallback_course_id = 0) {
		$lesson_id = absint($lesson_id);
		$fallback_course_id = absint($fallback_course_id);

		if ($lesson_id > 0) {
			$course_id = $this->section_repository()->get_course_id_by_item_id($lesson_id);
			if ($course_id > 0) {
				return $course_id;
			}
		}

		return $fallback_course_id;
	}

	private function add_meta_box($args = array()) {
		if (is_array($args) && !empty($args)) {
			call_user_func_array('add_meta_box', $args);
		}
	}

	public function add_meta_boxes() {
		$this->options_metabox();
		$this->lesson_tagline_metabox();
		$this->ai_content_metabox();
		$this->policy_document_metabox();
	}

	public function policy_document_metabox() {
		$this->add_meta_box( array(
			'lxp-policy-document',
			esc_html__( 'Policy Document', 'tiny-lxp-platform' ),
			array( $this, 'policy_document_metabox_html' ),
			TL_LESSON_CPT,
			'side',
			'default',
		) );
	}

	public function policy_document_metabox_html( $post = null ) {
		if ( empty( $post ) || ! isset( $post->ID ) ) {
			return;
		}
		$value = get_post_meta( $post->ID, 'lxp_lesson_include_in_policy', true );
		wp_nonce_field( 'save_lxp_lesson_policy', 'lxp_lesson_policy_nonce' );
		?>
		<p>
			<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
				<input type="checkbox" name="lxp_lesson_include_in_policy" value="1"
					<?php checked( '1', $value ); ?> />
				<?php esc_html_e( 'Include in Policy Document', 'tiny-lxp-platform' ); ?>
			</label>
		</p>
		<?php
	}

	public function save_lesson_policy_meta( $post_id = null, $post = null ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $post ) || ! isset( $post->post_type ) || $post->post_type !== TL_LESSON_CPT ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['lxp_lesson_policy_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lxp_lesson_policy_nonce'] ) ), 'save_lxp_lesson_policy' ) ) {
			return;
		}
		if ( ! empty( $_POST['lxp_lesson_include_in_policy'] ) ) {
			update_post_meta( $post_id, 'lxp_lesson_include_in_policy', '1' );
		} else {
			delete_post_meta( $post_id, 'lxp_lesson_include_in_policy' );
		}
	}

	public function ai_content_metabox() {
		$this->add_meta_box( array(
			'lxp-ai-content-gen',
			esc_html__( 'AI Content Gen', 'tiny-lxp-platform' ),
			array( $this, 'ai_content_metabox_html' ),
			TL_LESSON_CPT,
			'side',
			'high',
		) );
	}

	public function ai_content_metabox_html( $post = null ) {
		if ( empty( $post ) || ! isset( $post->ID ) ) {
			return;
		}
		$block_types = $this->get_ai_block_types();
		$block_reference_url = admin_url( 'admin.php?page=curriki-learn-block-reference' );
		?>
		<input type="hidden" id="lxp-ai-gen-post-id" value="<?php echo esc_attr( $post->ID ); ?>" />
		<input type="hidden" id="lxp-ai-video-post-title" value="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>" />
		<div class="lxp-ai-action-group lxp-ai-action-group-primary">
			<h4 class="lxp-ai-action-title"><?php echo esc_html__( 'Generate Full Lesson Layout', 'tiny-lxp-platform' ); ?></h4>
			<p class="lxp-ai-action-help"><?php echo esc_html__( 'Use this when you want AI to transform the current lesson content into a complete lesson page in one pass.', 'tiny-lxp-platform' ); ?></p>
			<p class="lxp-ai-action-row">
				<button type="button" id="lxp-ai-content-gen-btn" class="button button-primary">
					<?php echo esc_html__( 'Generate Full Lesson', 'tiny-lxp-platform' ); ?>
				</button>
			</p>
		</div>
		<div class="lxp-ai-action-group lxp-ai-action-group-blocks">
			<h4 class="lxp-ai-action-title"><?php echo esc_html__( 'Build Lesson with Block Markers', 'tiny-lxp-platform' ); ?></h4>
			<p class="lxp-ai-action-help"><?php echo esc_html__( 'Use markers when you want to control the layout section by section before generating the final lesson blocks.', 'tiny-lxp-platform' ); ?></p>
			<div class="lxp-ai-block-picker-wrap">
				<div class="lxp-ai-action-row lxp-ai-picker-row">
					<button type="button" id="lxp-ai-block-picker-btn" class="button">
						<?php echo esc_html__( 'Insert Block Marker', 'tiny-lxp-platform' ); ?>
					</button>
					<a class="lxp-ai-doc-help-link" href="<?php echo esc_url( $block_reference_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr__( 'Open Block Reference', 'tiny-lxp-platform' ); ?>">
						<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html__( 'Open Block Reference', 'tiny-lxp-platform' ); ?></span>
					</a>
				</div>
				<div id="lxp-ai-block-picker-list" class="lxp-ai-block-picker-list">
					<?php foreach ( $block_types as $block_type ) : ?>
						<button type="button" class="lxp-block-picker-item" data-block-type="<?php echo esc_attr( $block_type['type'] ); ?>">
							<span class="lxp-block-picker-label"><?php echo esc_html( $block_type['label'] ); ?></span>
							<span class="lxp-block-picker-marker"><?php echo esc_html( ':::' . $block_type['type'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
			<p class="lxp-ai-action-row">
				<button type="button" id="lxp-ai-blocks-gen-btn" class="button button-primary">
					<?php echo esc_html__( 'Generate Marked Blocks', 'tiny-lxp-platform' ); ?>
				</button>
			</p>
		</div>
		<div class="lxp-ai-action-group lxp-ai-action-group-reset">
			<p class="lxp-ai-action-row">
				<button type="button" id="lxp-ai-content-reset-btn" class="button">
					<?php echo esc_html__( 'Restore Last Pre-AI Content', 'tiny-lxp-platform' ); ?>
				</button>
			</p>
		</div>
		<div class="lxp-ai-action-group lxp-ai-action-group-video">
			<h4 class="lxp-ai-action-title"><?php echo esc_html__( 'Generate Lesson Video', 'tiny-lxp-platform' ); ?></h4>
			<p class="lxp-ai-action-help"><?php echo esc_html__( 'Use AI to generate a 60-second animated lesson video via Remotion Lambda.', 'tiny-lxp-platform' ); ?></p>
			<p class="lxp-ai-action-row">
				<button type="button" id="lxp-ai-video-open-modal-btn" class="button button-primary">
					<?php echo esc_html__( 'Generate Video', 'tiny-lxp-platform' ); ?>
				</button>
			</p>
			<div id="lxp-ai-video-status">
				<?php
				$video_url = get_post_meta( $post->ID, 'lxp_lesson_video_url', true );
				if ( $video_url ) {
					?>
					<a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener" class="lxp-ai-video-link">&#9654; <?php echo esc_html__( 'Play Last Generated Video', 'tiny-lxp-platform' ); ?></a>
					<div class="lxp-ai-video-actions">
						<button type="button" class="button lxp-ai-video-copy-btn" data-video-url="<?php echo esc_attr( $video_url ); ?>">
							<?php echo esc_html__( 'Copy Link', 'tiny-lxp-platform' ); ?>
						</button>
						<button type="button" class="button lxp-ai-video-insert-btn" data-video-url="<?php echo esc_attr( $video_url ); ?>">
							<?php echo esc_html__( 'Insert Into Editor', 'tiny-lxp-platform' ); ?>
						</button>
					</div>
					<?php
				}
				?>
				<p id="lxp-ai-video-action-status" class="lxp-ai-video-action-status"></p>
			</div>
		</div>

		<div id="lxp-ai-content-status" style="margin-top: 8px; font-size: 12px; line-height: 1.5;"></div>

		<!-- AI Video Modal — 2-step wizard -->
		<div id="lxp-ai-video-modal" style="display:none;position:fixed;inset:0;z-index:100000;" role="dialog" aria-modal="true" aria-labelledby="lxp-ai-video-modal-title">
			<div class="lxp-ai-video-modal-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.55);"></div>
			<div class="lxp-ai-video-modal-panel" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:28px;width:520px;max-width:95vw;box-shadow:0 8px 40px rgba(0,0,0,0.22);">
				<button type="button" id="lxp-ai-video-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'tiny-lxp-platform' ); ?>" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#50575e;line-height:1;">&times;</button>

				<!-- Step indicator -->
				<div class="lxp-video-wizard-steps" aria-hidden="true">
					<div class="lxp-video-wizard-step active" id="lxp-vws-1">
						<span class="lxp-vws-num">1</span>
						<span class="lxp-vws-label"><?php echo esc_html__( 'Paste Content', 'tiny-lxp-platform' ); ?></span>
					</div>
					<div class="lxp-vws-arrow">›</div>
					<div class="lxp-video-wizard-step" id="lxp-vws-2">
						<span class="lxp-vws-num">2</span>
						<span class="lxp-vws-label"><?php echo esc_html__( 'Edit &amp; Generate', 'tiny-lxp-platform' ); ?></span>
					</div>
				</div>

				<!-- ── Step 1: Raw lesson text ─────────────────────────────── -->
				<div id="lxp-video-step-1" class="lxp-video-step-panel">
					<h3 id="lxp-ai-video-modal-title" style="margin:0 0 6px;font-size:15px;"><?php echo esc_html__( 'Paste Your Lesson Content', 'tiny-lxp-platform' ); ?></h3>
					<p style="font-size:12px;color:#50575e;margin:0 0 10px;"><?php echo esc_html__( 'Paste the full lesson text below. Formatting, bullet points and markup are cleaned automatically before AI processing.', 'tiny-lxp-platform' ); ?></p>
					<textarea id="lxp-ai-video-raw-text" rows="9" placeholder="<?php echo esc_attr__( 'Paste your lesson content here…', 'tiny-lxp-platform' ); ?>" style="width:100%;box-sizing:border-box;font-size:13px;resize:vertical;line-height:1.6;"></textarea>
					<div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
						<label for="lxp-video-duration" style="font-size:12px;font-weight:600;white-space:nowrap;"><?php echo esc_html__( 'Video length:', 'tiny-lxp-platform' ); ?></label>
						<input type="text" id="lxp-video-duration" value="1:00" maxlength="5" placeholder="M:SS" style="width:60px;font-size:12px;text-align:center;" />
						<span style="font-size:11px;color:#646970;"><?php echo esc_html__( 'M:SS &mdash; e.g. 0:30 · 1:30 · 3:00 (max 5:00)', 'tiny-lxp-platform' ); ?></span>
					</div>
					<div class="lxp-video-step-actions">
						<button type="button" id="lxp-ai-video-script-btn" class="button button-primary lxp-video-step-btn-primary"><?php echo esc_html__( 'Process with AI →', 'tiny-lxp-platform' ); ?></button>
						<button type="button" id="lxp-video-restore-raw-btn" class="button"><?php echo esc_html__( 'Restore Last Input', 'tiny-lxp-platform' ); ?></button>
					</div>
					<div id="lxp-video-step1-status" class="lxp-video-step-status" style="margin-top:8px;font-size:12px;line-height:1.5;min-height:1.5em;"></div>
				</div>

				<!-- ── Step 2: Block-marker script editor ──────────────────── -->
				<div id="lxp-video-step-2" class="lxp-video-step-panel" style="display:none;">
					<h3 style="margin:0 0 6px;font-size:15px;"><?php echo esc_html__( 'Review &amp; Edit Video Scenes', 'tiny-lxp-platform' ); ?></h3>
					<p style="font-size:12px;color:#50575e;margin:0 0 10px;"><?php echo esc_html__( 'Each :::layout-name block becomes one scene. Edit freely, reorder, or add blocks with the picker below.', 'tiny-lxp-platform' ); ?></p>
					<textarea id="lxp-ai-video-prompt" rows="8" style="width:100%;box-sizing:border-box;font-size:13px;resize:vertical;font-family:monospace;"></textarea>
					<div style="display:flex;gap:8px;align-items:center;margin-top:8px;">
						<select id="lxp-video-layout-picker" style="flex:1;font-size:12px;">
							<option value=""><?php echo esc_html__( '— Insert a layout block —', 'tiny-lxp-platform' ); ?></option>
							<option value="intro"><?php echo esc_html__( 'Intro', 'tiny-lxp-platform' ); ?></option>
							<option value="problem"><?php echo esc_html__( 'Problem', 'tiny-lxp-platform' ); ?></option>
							<option value="framework"><?php echo esc_html__( 'Framework', 'tiny-lxp-platform' ); ?></option>
							<option value="process"><?php echo esc_html__( 'Process', 'tiny-lxp-platform' ); ?></option>
							<option value="contrast"><?php echo esc_html__( 'Contrast', 'tiny-lxp-platform' ); ?></option>
							<option value="evaluation"><?php echo esc_html__( 'Evaluation', 'tiny-lxp-platform' ); ?></option>
							<option value="options"><?php echo esc_html__( 'Options', 'tiny-lxp-platform' ); ?></option>
							<option value="conclusion"><?php echo esc_html__( 'Conclusion', 'tiny-lxp-platform' ); ?></option>
							<option value="card-list"><?php echo esc_html__( 'Card List', 'tiny-lxp-platform' ); ?></option>
							<option value="branching-flow"><?php echo esc_html__( 'Branching Flow', 'tiny-lxp-platform' ); ?></option>
							<option value="before-after"><?php echo esc_html__( 'Before &amp; After', 'tiny-lxp-platform' ); ?></option>
							<option value="quad-grid"><?php echo esc_html__( 'Quad Grid', 'tiny-lxp-platform' ); ?></option>
							<option value="three-step-flow"><?php echo esc_html__( 'Three Step Flow', 'tiny-lxp-platform' ); ?></option>
							<option value="cycle-loop"><?php echo esc_html__( 'Cycle Loop', 'tiny-lxp-platform' ); ?></option>
							<option value="split-blueprint"><?php echo esc_html__( 'Split Blueprint', 'tiny-lxp-platform' ); ?></option>
							<option value="fuel-engine"><?php echo esc_html__( 'Fuel Engine', 'tiny-lxp-platform' ); ?></option>
							<option value="checklist-reveal"><?php echo esc_html__( 'Checklist Reveal', 'tiny-lxp-platform' ); ?></option>
							<option value="deployment-circles"><?php echo esc_html__( 'Deployment Circles', 'tiny-lxp-platform' ); ?></option>
							<option value="editorial"><?php echo esc_html__( 'Editorial', 'tiny-lxp-platform' ); ?></option>
							<option value="comparison"><?php echo esc_html__( 'Comparison (A vs B)', 'tiny-lxp-platform' ); ?></option>
							<option value="gate"><?php echo esc_html__( 'Gate (Clarify / Confirm)', 'tiny-lxp-platform' ); ?></option>
							<option value="routing"><?php echo esc_html__( 'Routing (Sort to Buckets)', 'tiny-lxp-platform' ); ?></option>
							<option value="stat-highlight"><?php echo esc_html__( 'Stat Highlight (Metric)', 'tiny-lxp-platform' ); ?></option>
							<option value="transform-text"><?php echo esc_html__( 'Transform Text (Rewrite)', 'tiny-lxp-platform' ); ?></option>
						</select>
						<button type="button" id="lxp-video-insert-block-btn" class="button"><?php echo esc_html__( 'Insert', 'tiny-lxp-platform' ); ?></button>
					</div>
					<p style="margin:5px 0 10px;font-size:11px;color:#646970;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=curriki-learn-video-layouts' ) ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:5px;">
							<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
							<span><?php echo esc_html__( 'View Layout Reference', 'tiny-lxp-platform' ); ?></span>
						</a>
					</p>
					<!-- ── Optional full-screen background video clip ──────────── -->
					<div style="margin:12px 0 4px;padding-top:10px;border-top:1px solid #e0e0e0;">
						<label for="lxp-video-bg-clip-url" style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;"><?php echo esc_html__( 'Background Video Clip (optional)', 'tiny-lxp-platform' ); ?></label>
						<div style="display:flex;gap:6px;align-items:center;">
							<input type="url" id="lxp-video-bg-clip-url" placeholder="https://… .mp4" style="flex:1;font-size:12px;" />
							<button type="button" id="lxp-video-bg-clip-browse-btn" class="button"><?php echo esc_html__( 'Browse Media', 'tiny-lxp-platform' ); ?></button>
							<button type="button" id="lxp-video-bg-clip-clear-btn" class="button" title="<?php echo esc_attr__( 'Clear', 'tiny-lxp-platform' ); ?>">&times;</button>
						</div>
						<p style="margin:5px 0 0;font-size:11px;color:#646970;"><?php echo esc_html__( 'Plays full-screen behind the whole lesson and is trimmed to the video length set above. Must be a public .mp4/.webm/.mov URL reachable by the renderer.', 'tiny-lxp-platform' ); ?></p>
					</div>

					<div class="lxp-video-step-actions">
						<button type="button" id="lxp-video-back-btn" class="button"><?php echo esc_html__( '← Back', 'tiny-lxp-platform' ); ?></button>
						<button type="button" id="lxp-video-restore-script-btn" class="button"><?php echo esc_html__( 'Restore Last Script', 'tiny-lxp-platform' ); ?></button>
						<button type="button" id="lxp-ai-video-generate-btn" class="button button-primary lxp-video-step-btn-primary"><?php echo esc_html__( 'Generate Video', 'tiny-lxp-platform' ); ?></button>
					</div>
					<div id="lxp-ai-video-modal-status" style="margin-top:8px;font-size:12px;line-height:1.5;color:#50575e;min-height:1.5em;"></div>
				</div>

			</div>
		</div>
		<?php
	}

	private function get_ai_block_types() {
		return array(
			array( 'type' => 'hero', 'label' => 'Hero' ),
			array( 'type' => 'learning-outcomes', 'label' => 'Learning Outcomes' ),
			array( 'type' => 'opening-hook', 'label' => 'Opening Hook' ),
			array( 'type' => 'capstone', 'label' => 'Capstone' ),
			array( 'type' => 'stats-grid', 'label' => 'Stats Grid' ),
			array( 'type' => 'cards-grid', 'label' => 'Cards Grid' ),
			array( 'type' => 'tier-cards', 'label' => 'Tier Cards' ),
			array( 'type' => 'numbered-grid', 'label' => 'Numbered Grid' ),
			array( 'type' => 'two-col-table', 'label' => 'Two-Column Table' ),
			array( 'type' => 'three-col-table', 'label' => 'Three-Column Table' ),
			array( 'type' => 'contrast-panel', 'label' => 'Contrast Panel' ),
			array( 'type' => 'callout', 'label' => 'Callout' ),
			array( 'type' => 'dark-block', 'label' => 'Dark Block' ),
			array( 'type' => 'definition-block', 'label' => 'Definition Block' ),
			array( 'type' => 'role-split', 'label' => 'Role Split' ),
			array( 'type' => 'option-cards', 'label' => 'Option Cards' ),
			array( 'type' => 'checklist', 'label' => 'Checklist' ),
			array( 'type' => 'cycle', 'label' => 'Cycle' ),
			array( 'type' => 'myth-reality', 'label' => 'Myth vs Reality' ),
			array( 'type' => 'blockquote', 'label' => 'Blockquote' ),
		);
	}

	public function lesson_tagline_metabox() {
		$this->add_meta_box( array(
			'lxp-lesson-tagline',
			esc_html__( 'Lesson Tagline', 'tiny-lxp-platform' ),
			array( $this, 'lesson_tagline_metabox_html' ),
			TL_LESSON_CPT,
			'side',
			'default',
		) );
	}

	public function lesson_tagline_metabox_html( $post = null ) {
		if ( empty( $post ) || ! isset( $post->ID ) ) {
			return;
		}
		$tagline = get_post_meta( $post->ID, 'lxp_lesson_tagline', true );
		wp_nonce_field( 'save_lxp_lesson_tagline', 'lxp_lesson_tagline_nonce' );
		?>
		<p>
			<label for="lxp_lesson_tagline_field" style="display:block;margin-bottom:4px;">
				<?php echo esc_html__( 'Short tagline or subtitle shown in lesson hero sections.', 'tiny-lxp-platform' ); ?>
			</label>
			<textarea id="lxp_lesson_tagline_field" name="lxp_lesson_tagline"
				rows="3" style="width:100%;"><?php echo esc_textarea( $tagline ?: '' ); ?></textarea>
		</p>
		<?php
	}

	public function save_lesson_tagline_meta( $post_id = null, $post = null ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $post ) || ! isset( $post->post_type ) || $post->post_type !== TL_LESSON_CPT ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['lxp_lesson_tagline_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lxp_lesson_tagline_nonce'] ) ), 'save_lxp_lesson_tagline' ) ) {
			return;
		}
		$value = isset( $_POST['lxp_lesson_tagline'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lxp_lesson_tagline'] ) ) : '';
		if ( $value !== '' ) {
			update_post_meta( $post_id, 'lxp_lesson_tagline', $value );
		} else {
			delete_post_meta( $post_id, 'lxp_lesson_tagline' );
		}
	}

	public function options_metabox() {
		$this->add_meta_box(array(
			'lesson-options-class',
			esc_html__('CurrikiStudio Content', 'lesson-options'),
			array($this, 'options_metabox_html'),
			TL_LESSON_CPT,
			'side',
			'default',
		));
	}

	public function post_meta_request_params($args, $request) {
		$args += array(
			'meta_key' => $request['meta_key'],
			'meta_value' => $request['meta_value'],
			'meta_query' => $request['meta_query'],
		);

		return $args;
	}

	public function options_metabox_html($post = null) {
		$metadata = $this->metadata_repository()->get($post->ID);
		$fallback_course_id = isset($_GET['courseid']) ? absint(wp_unslash($_GET['courseid'])) : absint(get_post_meta($post->ID, 'tl_course_id', true));
		$resolved_course_id = $this->resolve_course_id_for_lesson($post->ID, $fallback_course_id);
		$resolved_course = $resolved_course_id > 0 ? get_post($resolved_course_id) : null;
		$shortcode_preview = '';
		if (!empty($metadata->lti_tool_url)) {
			$shortcode_preview = '[currikistudio url=' . esc_url_raw($metadata->lti_tool_url) . ']';
		}

		echo '<h4>Course</h4>';
		if (!empty($resolved_course) && isset($resolved_course->post_title)) {
			echo '<p>' . esc_html($resolved_course->post_title) . '</p>';
		} else {
			echo '<p>' . esc_html__('No linked course found', 'lesson-options') . '</p>';
		}
		echo '<input type="hidden" name="tl_course_id" value="' . esc_attr($resolved_course_id) . '" />';
		wp_nonce_field( 'save_lesson_lti_options', 'lesson_lti_nonce' );
		?>
		<h4>CurrikiStudio Content</h4>
		<div style="width: 100%;margin-top:-10px">
		 <input type="hidden" id="lti_tool_url" name="lti_tool_url" value="<?php echo esc_attr($metadata->lti_tool_url); ?>" style="width: 100%;" />
		 <input type="hidden" id="lti_tool_code" name="lti_tool_code" value="<?php echo esc_attr($metadata->lti_tool_code); ?>" style="width: 100%;" />
		 <input type="hidden" id="lti_content_title" name="lti_content_title" value="<?php echo esc_attr($metadata->lti_content_title); ?>" style="width: 100%;" />
		 <input type="hidden" id="lti_custom_attr" name="lti_custom_attr" value="<?php echo esc_attr($metadata->lti_custom_attr); ?>" style="width: 100%;" />
		 <input type="hidden" id="lti_post_attr_id" name="lti_post_attr_id" value="<?php echo esc_attr($metadata->lti_post_attr_id); ?>" style="width: 100%;" />
		</div>
		<div id="currikistudio-selection-preview" style="width:100%;margin-top:8px;">
			<p><strong><?php echo esc_html__('Selected title', 'lesson-options'); ?>:</strong> <span id="currikistudio-selected-title"><?php echo esc_html($metadata->lti_content_title); ?></span></p>
			<p><strong><?php echo esc_html__('Shortcode', 'lesson-options'); ?>:</strong></p>
			<textarea id="currikistudio-shortcode-preview" readonly style="width:100%;min-height:56px;"><?php echo esc_textarea($shortcode_preview); ?></textarea>
			<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
				<button type="button" class="button" id="currikistudio-copy-title"><?php echo esc_html__('Copy Title', 'lesson-options'); ?></button>
				<button type="button" class="button" id="currikistudio-copy-shortcode"><?php echo esc_html__('Copy Shortcode', 'lesson-options'); ?></button>
			</div>
			<p id="currikistudio-copy-status" style="margin:8px 0 0;font-size:12px;"></p>
		</div>
		<div id="preview_lit_connections" style="width: 100%;display: inline-block;margin-top: 10px;">
			<div class="preview button" href="#">Select Content<span class="screen-reader-text"> (opens in a new tab)</span></div>
		</div>
		<?php
	}

	public function save_tl_post( $post_id = null, $post = null ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $post ) || ! isset( $post->post_type ) || $post->post_type !== TL_LESSON_CPT ) {
			return;
		}
		if ( ! isset( $_POST['lesson_lti_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lesson_lti_nonce'] ) ), 'save_lesson_lti_options' ) ) {
			return;
		}
		$posted_course_id = isset( $_POST['tl_course_id'] ) ? absint( wp_unslash( $_POST['tl_course_id'] ) ) : 0;
		$resolved_course_id = $this->resolve_course_id_for_lesson( $post_id, $posted_course_id );
		$metadata_values = array(
			'lti_tool_url'      => isset( $_POST['lti_tool_url'] )      ? sanitize_text_field( wp_unslash( $_POST['lti_tool_url'] ) )      : '',
			'lti_tool_code'     => isset( $_POST['lti_tool_code'] )     ? sanitize_text_field( wp_unslash( $_POST['lti_tool_code'] ) )     : '',
			'lti_content_title' => ( isset( $_POST['lti_content_title'] ) && $_POST['lti_content_title'] !== '' ) ? sanitize_text_field( trim( wp_unslash( $_POST['lti_content_title'] ) ) ) : 'Section',
			'lti_custom_attr'   => isset( $_POST['lti_custom_attr'] )   ? sanitize_text_field( wp_unslash( $_POST['lti_custom_attr'] ) )   : '',
			'lti_post_attr_id'  => isset( $_POST['lti_post_attr_id'] )  ? sanitize_text_field( wp_unslash( $_POST['lti_post_attr_id'] ) )  : '',
		);
		$this->metadata_repository()->update_from_array( $post_id, $metadata_values );
		if ( $resolved_course_id != get_post_meta( $post_id, 'tl_course_id', true ) ) {
			$this->metadata_repository()->update_from_array( $post_id, array( 'lti_course_id' => '' ) );
		}
		update_post_meta( $post_id, 'tl_course_id', $resolved_course_id );
	}

	public function insert_post_api( $post, $request ) {
		if ( ! isset( $request['meta'] ) || ! is_array( $request['meta'] ) ) {
			return;
		}
		$meta = $request['meta'];
		$requested_course_id = isset( $meta['tl_course_id'] ) ? absint( $meta['tl_course_id'] ) : 0;
		$course_id = $this->resolve_course_id_for_lesson( $post->ID, $requested_course_id );
		update_post_meta( $post->ID, 'tl_course_id', $course_id );
		$this->metadata_repository()->update_from_array( $post->ID, array(
			'lti_content_id'    => isset( $meta['lti_content_id'] )    ? sanitize_text_field( $meta['lti_content_id'] )    : '',
			'lti_tool_url'      => isset( $meta['lti_tool_url'] )      ? sanitize_text_field( $meta['lti_tool_url'] )      : '',
			'lti_tool_code'     => isset( $meta['lti_tool_code'] )     ? sanitize_text_field( $meta['lti_tool_code'] )     : '',
			'lti_custom_attr'   => isset( $meta['lti_custom_attr'] )   ? sanitize_text_field( $meta['lti_custom_attr'] )   : '',
			'lti_content_title' => isset( $meta['lti_content_title'] ) ? sanitize_text_field( $meta['lti_content_title'] ) : '',
			'lti_post_attr_id'  => isset( $meta['lti_post_attr_id'] )  ? sanitize_text_field( $meta['lti_post_attr_id'] )  : '',
			'lti_course_id'     => isset( $meta['lti_course_id'] )     ? sanitize_text_field( $meta['lti_course_id'] )     : '',
		) );
	}

	public function provide_lti_launch_metadata($launch_metadata, $post, $deeplink, $ok, $reason) {
		if ($deeplink || $ok || empty($post) || !isset($post->post_type) || $post->post_type !== LP_LESSON_CPT) {
			return $launch_metadata;
		}

		$metadata = $this->metadata_repository()->get($post->ID);
		if (empty($metadata->lti_tool_code) || empty($metadata->lti_post_attr_id)) {
			return $launch_metadata;
		}

		return array_merge(array(
			'tool' => $metadata->lti_tool_code,
			'title' => $metadata->lti_content_title,
			'url' => $metadata->lti_tool_url,
			'custom' => $metadata->lti_custom_attr,
			'id' => $metadata->lti_post_attr_id,
			'target' => 'embed',
		), is_array($launch_metadata) ? $launch_metadata : array());
	}

	private function assignment_context_for_lesson($lesson_id = 0) {
		$lesson_id = absint($lesson_id);
		$assignment_id = isset($_GET['assignment_id']) ? absint(wp_unslash($_GET['assignment_id'])) : 0;
		$text_domain = Tiny_LXP_Platform::get_plugin_name();

		if ($lesson_id <= 0 || $assignment_id <= 0 || !function_exists('lxp_get_assignment')) {
			return null;
		}

		$assignment = lxp_get_assignment($assignment_id);
		if (empty($assignment) || empty($assignment->ID)) {
			return null;
		}

		$assignment_lesson_id = absint(get_post_meta($assignment->ID, 'lxp_lesson_id', true));
		if ($assignment_lesson_id !== $lesson_id) {
			return null;
		}

		$course_id = absint(get_post_meta($assignment->ID, 'course_id', true));
		$course = $course_id > 0 ? get_post($course_id) : null;
		$section_name = $this->section_repository()->get_section_name_by_item_id($lesson_id);
		$assets_src = TL_PLUGIN_URL . 'lms/templates/tinyLxpTheme/treks-src/';

		return array(
			'assignment' => $assignment,
			'course' => $course,
			'section_name' => $section_name ? $section_name : esc_html__('Uncategorized', $text_domain),
			'assets_src' => $assets_src,
		);
	}

	private function render_assignment_lesson_context($context, $lesson_post) {
		if (!is_array($context) || empty($context['assignment']) || empty($lesson_post) || !isset($lesson_post->post_title)) {
			return;
		}

		$assignment = $context['assignment'];
		$text_domain = Tiny_LXP_Platform::get_plugin_name();
		$course = isset($context['course']) ? $context['course'] : null;
		$course_title = !empty($course) && isset($course->post_title) ? $course->post_title : esc_html__('Course', $text_domain);
		$section_name = isset($context['section_name']) ? $context['section_name'] : esc_html__('Uncategorized', $text_domain);
		$assets_src = isset($context['assets_src']) ? $context['assets_src'] : '';
		$start_date = get_post_meta($assignment->ID, 'start_date', true);
		$start_time = get_post_meta($assignment->ID, 'start_time', true);
		$end_date = get_post_meta($assignment->ID, 'end_date', true);
		$end_time = get_post_meta($assignment->ID, 'end_time', true);

		echo '<style>';
		echo '.tinylxp-assignment-lesson-context{margin:0 0 16px;}.tinylxp-assignment-lesson-context .course_nav_path{display:flex;align-items:center;flex-wrap:wrap;gap:10px;}.tinylxp-assignment-lesson-context .practice_flx{display:flex;gap:16px;color:#979797;align-items:center;}.tinylxp-assignment-lesson-context .practice_flx img{width:23px;height:20px;}.tinylxp-assignment-lesson-context .time-date-box{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;}.tinylxp-assignment-lesson-context .date-time{font-family:Arial,sans-serif;font-style:normal;font-weight:400;font-size:16px;padding:4px 8px;line-height:24px;background:rgba(31,165,212,0.16);border-radius:8px;color:#0b5d7a;margin:0;}.tinylxp-assignment-lesson-context .to-text{color:#757575;background:none;}';
		echo '</style>';
		echo '<div class="tinylxp-assignment-lesson-context">';
		echo '<div class="course_nav_path">';
		echo '<div class="practice_flx"><img src="' . esc_url($assets_src . '/assets/img/nav_Treks.svg') . '" alt="" /><p class="practice_text">' . esc_html__('My Course', $text_domain) . '</p></div>';
		echo '<div class="practice_flx"><img src="' . esc_url($assets_src . '/assets/img/bc_arrow_right.svg') . '" alt="" /><p class="practice_text">' . esc_html($course_title) . '</p></div>';
		echo '<div class="practice_flx"><img src="' . esc_url($assets_src . '/assets/img/bc_arrow_right.svg') . '" alt="" /><p class="practice_text">' . esc_html($section_name) . '</p></div>';
		echo '<div class="practice_flx"><img src="' . esc_url($assets_src . '/assets/img/bc_arrow_right.svg') . '" alt="" /><p class="practice_text">' . esc_html($lesson_post->post_title) . '</p></div>';
		echo '</div>';

		if (!empty($start_date) && !empty($start_time) && !empty($end_date) && !empty($end_time)) {
			echo '<div class="time-date-box">';
			echo '<p class="date-time">' . esc_html(date_i18n('l, M d, Y h:i A', strtotime($start_date . ' ' . $start_time))) . '</p>';
			echo '<p class="date-time to-text">' . esc_html__('To', $text_domain) . '</p>';
			echo '<p class="date-time">' . esc_html(date_i18n('l, M d, Y h:i A', strtotime($end_date . ' ' . $end_time))) . '</p>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Resolve the lp_lesson post currently being viewed inside a course context.
	 *
	 * On LearnPress lesson URLs the main WP query resolves to the parent lp_course
	 * post, not the lesson. This helper extracts the lesson being viewed so that
	 * metadata lookups and the LTI launch URL use the correct post ID.
	 *
	 * Resolution order:
	 *  1. $course_post is already LP_LESSON_CPT – pass straight through.
	 *  2. LP_Global::course_item() – the authoritative LP way to get the active item.
	 *  3. URL-path parsing – find the segment after 'lessons/' and look up by slug.
	 *
	 * @param WP_Post $course_post The post returned by get_post() (usually the course).
	 * @return WP_Post|null The lp_lesson post, or null when none can be determined.
	 */
	private function resolve_current_lesson_post( $course_post ) {
		// 1. Already a lesson (e.g. direct post preview).
		if ( ! empty( $course_post ) && isset( $course_post->post_type ) && $course_post->post_type === LP_LESSON_CPT ) {
			return $course_post;
		}

		// 2. LP_Global::course_item() (LearnPress ≥ 4.x, class_exists guard).
		if ( class_exists( 'LP_Global' ) ) {
			$item = LP_Global::course_item();
			if ( ! empty( $item ) && is_object( $item ) && method_exists( $item, 'get_id' ) ) {
				$lesson_id = absint( $item->get_id() );
				if ( $lesson_id > 0 ) {
					$lesson = get_post( $lesson_id );
					if ( ! empty( $lesson ) && $lesson->post_type === LP_LESSON_CPT ) {
						return $lesson;
					}
				}
			}
		}

		// 3. Parse the current request URL for the lesson slug.
		//    LearnPress lesson permalinks are /{course-base}/{course-slug}/lessons/{lesson-slug}/
		$url        = home_url( add_query_arg( array() ) );
		$url_path   = parse_url( $url, PHP_URL_PATH );
		$path       = ( null !== $url_path ) ? $url_path : '';
		$segments   = explode( '/', trim( $path, '/' ) );
		$lessons_idx = array_search( 'lessons', $segments, true );
		if ( $lessons_idx !== false && isset( $segments[ $lessons_idx + 1 ] ) ) {
			$lesson_slug = sanitize_title( $segments[ $lessons_idx + 1 ] );
			if ( ! empty( $lesson_slug ) ) {
				$matches = get_posts( array(
					'name'        => $lesson_slug,
					'post_type'   => LP_LESSON_CPT,
					'post_status' => array( 'publish', 'private', 'draft' ),
					'numberposts' => 1,
				) );
				if ( ! empty( $matches ) && $matches[0]->post_type === LP_LESSON_CPT ) {
					return $matches[0];
				}
			}
		}

		return null;
	}

	public function render_lti_lesson_embed() {
		if (!is_singular(LP_COURSE_CPT)) {
			return;
		}

		$post = get_post();
		if (empty($post) || !isset($post->ID)) {
			return;
		}

		// Resolve the actual lp_lesson being viewed – get_post() returns the course
		// post on all LearnPress lesson URLs because the main WP query runs on lp_course.
		$lesson_post = $this->resolve_current_lesson_post( $post );
		if ( empty( $lesson_post ) ) {
			// Visiting the course overview page (no active lesson) – nothing to embed.
			return;
		}

		$assignment_context = $this->assignment_context_for_lesson($lesson_post->ID);
		if (!empty($assignment_context)) {
			$this->render_assignment_lesson_context($assignment_context, $lesson_post);
		}

		$metadata = $this->metadata_repository()->get($lesson_post->ID);
		if (empty($metadata->lti_tool_code) || empty($metadata->lti_post_attr_id)) {
			return;
		}

		$launch_url = add_query_arg(
			array(
				Tiny_LXP_Platform::get_plugin_name() => '',
				'post' => $lesson_post->ID,
				'id' => $metadata->lti_post_attr_id,
			),
			site_url()
		);

		echo '<div class="tinylxp-lp-lesson-embed" style="margin-top:16px;margin-bottom:16px;">';
		echo '<iframe style="border:none;width:100%;height:706px;" src="' . esc_url($launch_url) . '" allowfullscreen></iframe>';
		echo '</div>';
	}

	public function render_js_debug_panel() {
		if ( ! is_singular( 'lp_course' ) ) {
			return;
		}
		if ( sanitize_key( isset( $_GET['debug'] ) ? $_GET['debug'] : '' ) !== '1' ) {
			return;
		}

		global $wp_scripts;

		$queue = isset( $wp_scripts->queue ) ? (array) $wp_scripts->queue : array();
		$queue = array_filter( $queue, function( $handle ) use ( $wp_scripts ) {
			$src = isset( $wp_scripts->registered[ $handle ] ) ? $wp_scripts->registered[ $handle ]->src : '';
			return $src && strpos( $src, 'TinyLxp-wp-plugin' ) !== false;
		} );
		sort( $queue );
		$count = count( $queue );
		?>
<style id="tinylxp-js-debug-styles">
#tinylxp-js-debug {
	box-sizing: border-box;
	width: 100%;
	margin: 0;
	padding: 20px 24px;
	background: #1a1a2e;
	color: #e0e0e0;
	font-family: 'Courier New', Courier, monospace;
	font-size: 13px;
	line-height: 1.5;
	border-top: 4px solid #e94560;
	position: relative;
	z-index: 99999;
}
#tinylxp-js-debug h2 {
	margin: 0 0 16px;
	color: #e94560;
	font-size: 16px;
	letter-spacing: 0.05em;
	border-bottom: 1px solid #333;
	padding-bottom: 8px;
}
#tinylxp-js-debug .tlxp-notice {
	background: #2a2a40;
	border-left: 3px solid #f5a623;
	padding: 8px 12px;
	margin-bottom: 16px;
	color: #f5a623;
	font-size: 12px;
}
#tinylxp-js-debug details {
	background: #16213e;
	border: 1px solid #2d2d4a;
	border-radius: 4px;
	margin-bottom: 8px;
	overflow: hidden;
}
#tinylxp-js-debug details summary {
	padding: 10px 14px;
	cursor: pointer;
	user-select: none;
	outline: none;
	display: flex;
	align-items: baseline;
	gap: 10px;
	list-style: none;
}
#tinylxp-js-debug details summary::-webkit-details-marker { display: none; }
#tinylxp-js-debug details summary::before {
	content: '▶';
	color: #e94560;
	font-size: 10px;
	transition: transform 0.15s;
	flex-shrink: 0;
}
#tinylxp-js-debug details[open] summary::before { transform: rotate(90deg); }
#tinylxp-js-debug .tlxp-handle {
	color: #7fdbff;
	font-weight: bold;
}
#tinylxp-js-debug .tlxp-src-short {
	color: #aaa;
	font-size: 11px;
}
#tinylxp-js-debug .tlxp-body {
	padding: 10px 14px 14px;
	border-top: 1px solid #2d2d4a;
}
#tinylxp-js-debug .tlxp-row {
	display: flex;
	gap: 8px;
	margin-bottom: 6px;
	align-items: flex-start;
}
#tinylxp-js-debug .tlxp-label {
	min-width: 90px;
	color: #f5a623;
	flex-shrink: 0;
}
#tinylxp-js-debug .tlxp-val {
	color: #e0e0e0;
	word-break: break-all;
}
#tinylxp-js-debug .tlxp-val a {
	color: #7fdbff;
}
#tinylxp-js-debug .tlxp-code {
	background: #0f0f1a;
	border: 1px solid #2d2d4a;
	border-radius: 3px;
	padding: 6px 10px;
	margin-top: 4px;
	max-height: 200px;
	overflow-y: auto;
	white-space: pre-wrap;
	word-break: break-all;
	font-size: 12px;
	color: #b8f5b0;
}
#tinylxp-js-debug .tlxp-deps span {
	display: inline-block;
	background: #2d2d4a;
	color: #ccc;
	border-radius: 3px;
	padding: 1px 6px;
	margin: 2px 2px 2px 0;
	font-size: 11px;
}
</style>
<div id="tinylxp-js-debug">
	<h2>&#x1F527; TinyLxp JS Debug Panel &mdash; <?php echo esc_html( $count ); ?> enqueued script<?php echo $count !== 1 ? 's' : ''; ?></h2>
	<div class="tlxp-notice">&#9888; Only scripts enqueued by <strong>TinyLxp-wp-plugin</strong> are listed here. WordPress core, theme, and other plugin scripts are excluded. Hardcoded <code>&lt;script src=...&gt;</code> tags inside tinyLxpTheme templates are NOT shown &mdash; check browser DevTools &rsaquo; Sources or View Page Source for those.</div>
	<?php foreach ( $queue as $handle ) :
		$registered = isset( $wp_scripts->registered[ $handle ] ) ? $wp_scripts->registered[ $handle ] : null;
		$src         = $registered ? $registered->src : '';
		$deps        = ( $registered && ! empty( $registered->deps ) ) ? (array) $registered->deps : array();
		$ver         = $registered ? $registered->ver : '';
		$data_inline = $wp_scripts->get_data( $handle, 'data' );
		$before      = $wp_scripts->get_data( $handle, 'before' );
		$after       = $wp_scripts->get_data( $handle, 'after' );
		$src_short   = $src ? basename( parse_url( $src, PHP_URL_PATH ) ) : '(dynamic/no src)';
	?>
	<details>
		<summary>
			<span class="tlxp-handle"><?php echo esc_html( $handle ); ?></span>
			<span class="tlxp-src-short"><?php echo esc_html( $src_short ); ?></span>
		</summary>
		<div class="tlxp-body">
			<div class="tlxp-row">
				<span class="tlxp-label">src:</span>
				<span class="tlxp-val"><?php if ( $src ) : ?><a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $src ); ?></a><?php else : ?><em>(inline / no src)</em><?php endif; ?></span>
			</div>
			<div class="tlxp-row">
				<span class="tlxp-label">version:</span>
				<span class="tlxp-val"><?php echo esc_html( $ver ? $ver : '—' ); ?></span>
			</div>
			<?php if ( ! empty( $deps ) ) : ?>
			<div class="tlxp-row">
				<span class="tlxp-label">deps:</span>
				<span class="tlxp-val tlxp-deps"><?php foreach ( $deps as $dep ) : ?><span><?php echo esc_html( $dep ); ?></span><?php endforeach; ?></span>
			</div>
			<?php endif; ?>
			<?php if ( $data_inline ) : ?>
			<div class="tlxp-row">
				<span class="tlxp-label">localized:</span>
				<span class="tlxp-val"><div class="tlxp-code"><?php echo esc_html( $data_inline ); ?></div></span>
			</div>
			<?php endif; ?>
			<?php if ( ! empty( $before ) ) : ?>
			<div class="tlxp-row">
				<span class="tlxp-label">before:</span>
				<span class="tlxp-val"><div class="tlxp-code"><?php echo esc_html( implode( "\n", (array) $before ) ); ?></div></span>
			</div>
			<?php endif; ?>
			<?php if ( ! empty( $after ) ) : ?>
			<div class="tlxp-row">
				<span class="tlxp-label">after:</span>
				<span class="tlxp-val"><div class="tlxp-code"><?php echo esc_html( implode( "\n", (array) $after ) ); ?></div></span>
			</div>
			<?php endif; ?>
		</div>
	</details>
	<?php endforeach; ?>
</div>
		<?php
	}

	// Lesson content with raw <style>/<script> blocks pasted into the editor gets mangled by
	// wpautop's <br />/<p> insertion. LearnPress core's LP_Abstract_Object_Data::get_content()
	// (inc/abstracts/abstract-post-data.php) swaps global $post to the lesson before calling
	// apply_filters('the_content', ...), so get_post_type() here reliably resolves to the lesson
	// even though the page's queried object is the course (see LP4 URL gotcha in CLAUDE.md).
	// wpautop is removed only for the one the_content pass rendering lesson content, then restored
	// immediately after so every other post type keeps normal auto-paragraphing.
	public function remove_wpautop_for_lesson_content( $content ) {
		if ( TL_LESSON_CPT === get_post_type() ) {
			remove_filter( 'the_content', 'wpautop' );
		}

		return $content;
	}

	public function restore_wpautop_after_lesson_content( $content ) {
		if ( ! has_filter( 'the_content', 'wpautop' ) ) {
			add_filter( 'the_content', 'wpautop' );
		}

		return $content;
	}

	// Classic Editor's TinyMCE re-runs its own wpautop-equivalent HTML reformatting every time
	// the lesson content editor switches between Visual and Text mode, independent of the
	// render-side the_content filter above. Scoped to the lesson's main "content" editor only —
	// other post types and other editor instances (excerpt, meta-box WYSIWYG fields) are untouched.
	public function preserve_lesson_editor_html( $mce_init, $editor_id ) {
		if ( 'content' !== $editor_id ) {
			return $mce_init;
		}

		$screen = get_current_screen();
		if ( ! $screen || TL_LESSON_CPT !== $screen->post_type ) {
			return $mce_init;
		}

		$mce_init['wpautop']     = false;
		$mce_init['verify_html'] = false;

		// TinyMCE's default schema doesn't allow <style> as a child of <body>, so it strips or
		// re-encodes it on Text->Visual switch. Explicitly whitelisting it here lets the Visual
		// tab keep the tag as a live DOM node — the browser then applies the CSS in the editor
		// iframe itself, so authors get a real preview instead of dead/escaped markup.
		$mce_init['valid_children'] = isset( $mce_init['valid_children'] )
			? $mce_init['valid_children'] . ',+body[style]'
			: '+body[style]';

		$mce_init['extended_valid_elements'] = isset( $mce_init['extended_valid_elements'] )
			? $mce_init['extended_valid_elements'] . ',style[type]'
			: 'style[type]';

		return $mce_init;
	}
}