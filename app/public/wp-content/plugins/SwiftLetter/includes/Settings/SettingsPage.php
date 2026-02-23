<?php

namespace SwiftLetter\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsPage {

	private const PAGE_SLUG    = 'swiftletter-settings';
	private const OPTION_GROUP = 'swl_settings';

	public function register_menu(): void {
		add_submenu_page(
			'swiftletter',
			__( 'SwiftLetter Settings', 'swiftletter' ),
			__( 'Settings', 'swiftletter' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		// AI Provider section.
		add_settings_section(
			'swl_ai_section',
			__( 'AI Provider', 'swiftletter' ),
			[ $this, 'render_ai_section' ],
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'swl_active_ai', [
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'sanitize_active_ai' ],
			'default'           => 'openai',
		] );

		add_settings_field(
			'swl_active_ai',
			__( 'Active AI Provider', 'swiftletter' ),
			[ $this, 'render_active_ai_field' ],
			self::PAGE_SLUG,
			'swl_ai_section'
		);

		// API Keys section.
		add_settings_section(
			'swl_keys_section',
			__( 'API Keys', 'swiftletter' ),
			[ $this, 'render_keys_section' ],
			self::PAGE_SLUG
		);

		$key_fields = [
			'swl_openai_key' => __( 'OpenAI API Key', 'swiftletter' ),
			'swl_claude_key' => __( 'Claude API Key', 'swiftletter' ),
		];

		foreach ( $key_fields as $option_name => $label ) {
			register_setting( self::OPTION_GROUP, $option_name, [
				'type'              => 'string',
				'sanitize_callback' => function( $value ) use ( $option_name ) {
					return $this->sanitize_api_key( $value, $option_name );
				},
				'default'           => '',
			] );

			add_settings_field(
				$option_name,
				$label,
				[ $this, 'render_api_key_field' ],
				self::PAGE_SLUG,
				'swl_keys_section',
				[ 'option_name' => $option_name, 'label' => $label ]
			);
		}

		// TTS section.
		add_settings_section(
			'swl_tts_section',
			__( 'Text-to-Speech', 'swiftletter' ),
			[ $this, 'render_tts_section' ],
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'swl_tts_voice', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'coral',
		] );

		add_settings_field(
			'swl_tts_voice',
			__( 'Default Voice', 'swiftletter' ),
			[ $this, 'render_tts_voice_field' ],
			self::PAGE_SLUG,
			'swl_tts_section'
		);

		// Typography section.
		add_settings_section(
			'swl_typo_section',
			__( 'Typography', 'swiftletter' ),
			[ $this, 'render_typo_section' ],
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'swl_typography', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_typography' ],
			'default'           => \SwiftLetter\Activator::default_typography(),
		] );

		$typo_fields = [
			'font_family' => __( 'Font Family', 'swiftletter' ),
			'text_color'  => __( 'Text Color', 'swiftletter' ),
			'bg_color'    => __( 'Background Color', 'swiftletter' ),
			'link_color'  => __( 'Link Color', 'swiftletter' ),
			'h1_size'     => __( 'H1 Size (pt)', 'swiftletter' ),
			'h2_size'     => __( 'H2 Size (pt)', 'swiftletter' ),
			'h3_size'     => __( 'H3 Size (pt)', 'swiftletter' ),
			'h4_size'     => __( 'H4 Size (pt)', 'swiftletter' ),
			'body_size'   => __( 'Body Size (pt)', 'swiftletter' ),
		];

		foreach ( $typo_fields as $field_key => $label ) {
			add_settings_field(
				'swl_typo_' . $field_key,
				$label,
				[ $this, 'render_typography_field' ],
				self::PAGE_SLUG,
				'swl_typo_section',
				[ 'field_key' => $field_key, 'label' => $label ]
			);
		}
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	// Section descriptions.
	public function render_ai_section(): void {
		echo '<p>' . esc_html__( 'Select which AI provider to use for text refinement.', 'swiftletter' ) . '</p>';
	}

	public function render_keys_section(): void {
		echo '<p>' . esc_html__( 'API keys are stored encrypted and are never exposed to the browser.', 'swiftletter' ) . '</p>';
	}

	public function render_tts_section(): void {
		echo '<p>' . esc_html__( 'Configure text-to-speech settings for audio generation.', 'swiftletter' ) . '</p>';
	}

	public function render_typo_section(): void {
		echo '<p>' . esc_html__( 'Customize typography defaults for generated newsletters. Changes apply to new exports only.', 'swiftletter' ) . '</p>';
	}

	// Field renderers.
	public function render_active_ai_field(): void {
		$value = get_option( 'swl_active_ai', 'openai' );
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Active AI Provider', 'swiftletter' ); ?></legend>
			<label>
				<input type="radio" name="swl_active_ai" value="openai" <?php checked( $value, 'openai' ); ?> />
				<?php esc_html_e( 'OpenAI', 'swiftletter' ); ?>
			</label>
			<br />
			<label>
				<input type="radio" name="swl_active_ai" value="claude" <?php checked( $value, 'claude' ); ?> />
				<?php esc_html_e( 'Claude', 'swiftletter' ); ?>
			</label>
		</fieldset>
		<?php
	}

	public function render_api_key_field( array $args ): void {
		$option_name = $args['option_name'];
		$stored      = get_option( $option_name, '' );
		$masked      = '';

		if ( ! empty( $stored ) ) {
			$decrypted = Encryption::decrypt( $stored );
			if ( $decrypted !== false && strlen( $decrypted ) > 4 ) {
				$masked = str_repeat( '•', 12 ) . substr( $decrypted, -4 );
			} elseif ( $decrypted !== false ) {
				$masked = str_repeat( '•', 12 );
			}
		}

		$placeholder = ! empty( $masked ) ? $masked : __( 'Enter API key', 'swiftletter' );
		$field_id    = esc_attr( $option_name );
		?>
		<input
			type="password"
			id="<?php echo $field_id; ?>"
			name="<?php echo $field_id; ?>"
			value=""
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
			autocomplete="off"
			aria-describedby="<?php echo $field_id; ?>-desc"
		/>
		<p class="description" id="<?php echo $field_id; ?>-desc">
			<?php
			if ( ! empty( $masked ) ) {
				/* translators: %s: masked API key */
				printf( esc_html__( 'Currently set: %s. Leave blank to keep current key.', 'swiftletter' ), esc_html( $masked ) );
			} else {
				esc_html_e( 'No key stored. Enter your API key.', 'swiftletter' );
			}
			?>
		</p>
		<?php
		if ( ! empty( $stored ) ) :
			$provider = ( $option_name === 'swl_claude_key' ) ? 'claude' : 'openai';
			?>
		<p>
			<button
				type="button"
				class="button swl-test-api-btn"
				data-provider="<?php echo esc_attr( $provider ); ?>"
			>
				<?php esc_html_e( 'Test Connection', 'swiftletter' ); ?>
			</button>
			<span class="swl-test-api-result" aria-live="polite"></span>
		</p>
		<?php endif; ?>
		<?php
	}

	public function render_tts_voice_field(): void {
		$value  = get_option( 'swl_tts_voice', 'coral' );
		$voices = [
			'alloy', 'ash', 'ballad', 'cedar', 'coral',
			'echo', 'fable', 'marin', 'nova', 'onyx',
			'sage', 'shimmer', 'verse',
		];
		?>
		<label for="swl_tts_voice" class="screen-reader-text">
			<?php esc_html_e( 'Default Voice', 'swiftletter' ); ?>
		</label>
		<select id="swl_tts_voice" name="swl_tts_voice">
			<?php foreach ( $voices as $voice ) : ?>
				<option value="<?php echo esc_attr( $voice ); ?>" <?php selected( $value, $voice ); ?>>
					<?php echo esc_html( ucfirst( $voice ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_typography_field( array $args ): void {
		$typography = get_option( 'swl_typography', \SwiftLetter\Activator::default_typography() );
		$key        = $args['field_key'];
		$value      = $typography[ $key ] ?? '';
		$field_id   = 'swl_typo_' . esc_attr( $key );

		$is_color = in_array( $key, [ 'text_color', 'bg_color', 'link_color' ], true );
		$is_size  = str_ends_with( $key, '_size' );
		$type     = $is_color ? 'color' : ( $is_size ? 'number' : 'text' );

		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			id="<?php echo $field_id; ?>"
			name="swl_typography[<?php echo esc_attr( $key ); ?>]"
			value="<?php echo esc_attr( $value ); ?>"
			<?php if ( $is_size ) : ?>
				min="8" max="72" step="1"
			<?php endif; ?>
			class="<?php echo $is_color ? 'swl-color-picker' : 'regular-text'; ?>"
		/>
		<?php
	}

	public function enqueue_settings_assets( string $hook ): void {
		if ( $hook !== 'swiftletter_page_swiftletter-settings' ) {
			return;
		}

		wp_enqueue_script(
			'swiftletter-settings',
			SWIFTLETTER_URL . 'admin/js/settings.js',
			[],
			SWIFTLETTER_VERSION,
			true
		);

		wp_localize_script( 'swiftletter-settings', 'swlSettings', [
			'restUrl' => esc_url_raw( rest_url( 'swiftletter/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => [
				'testing'         => __( 'Testing…', 'swiftletter' ),
				'requestFailed'   => __( 'Request failed. Check your connection.', 'swiftletter' ),
			],
		] );
	}

	// Sanitization callbacks.
	public function sanitize_active_ai( $value ): string {
		return in_array( $value, [ 'openai', 'claude' ], true ) ? $value : 'openai';
	}

	public function sanitize_api_key( $value, string $option_name = '' ): string {
		$value = sanitize_text_field( $value );

		if ( empty( $value ) ) {
			return $option_name ? get_option( $option_name, '' ) : '';
		}

		return Encryption::encrypt( $value );
	}

	public function sanitize_typography( $value ): array {
		$defaults = \SwiftLetter\Activator::default_typography();

		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$sanitized = [];
		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $value[ $key ] ) ) {
				$sanitized[ $key ] = $default;
				continue;
			}

			if ( str_ends_with( $key, '_size' ) ) {
				$sanitized[ $key ] = max( 8, min( 72, absint( $value[ $key ] ) ) );
			} elseif ( in_array( $key, [ 'text_color', 'bg_color', 'link_color' ], true ) ) {
				$sanitized[ $key ] = sanitize_hex_color( $value[ $key ] ) ?: $default;
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value[ $key ] );
			}
		}

		return $sanitized;
	}
}
