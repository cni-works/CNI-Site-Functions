<?php
/**
 * Administrative editor for the site-specific code.
 *
 * @package CniSiteFunctions
 */

namespace CniWorks\CniSiteFunctions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the single-code settings page.
 */
final class Admin_Page {

	/** Settings page slug. */
	const PAGE_SLUG = 'cni-site-functions';

	/** Per-user transient prefix. */
	const NOTICE_PREFIX = 'cni_site_functions_notice_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_cni_site_functions_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_cni_site_functions_restore', array( __CLASS__, 'handle_restore' ) );
		add_action( 'admin_post_cni_site_functions_clear_error', array( __CLASS__, 'handle_clear_error' ) );
	}

	/**
	 * Check both administrative capabilities required for executable code.
	 *
	 * @return bool
	 */
	private static function can_manage() {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_plugins' );
	}

	/**
	 * Whether editing has been disabled specifically for this plugin.
	 *
	 * @return bool
	 */
	private static function is_editor_disabled() {
		return defined( 'CNI_SITE_FUNCTIONS_DISABLE_EDITOR' ) && CNI_SITE_FUNCTIONS_DISABLE_EDITOR;
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public static function register_page() {
		add_options_page(
			__( 'CNI Site Functions', 'cni-site-functions' ),
			__( 'CNI Site Functions', 'cni-site-functions' ),
			'edit_plugins',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Load the WordPress code editor only on this plugin's page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix || ! self::can_manage() ) {
			return;
		}

		$editor_settings = false;
		if ( ! self::is_editor_disabled() ) {
			$editor_settings = wp_enqueue_code_editor(
				array(
					'type'       => 'text/x-php',
					'codemirror' => array(
						'autoCloseBrackets' => true,
						'indentUnit'        => 4,
						'indentWithTabs'    => true,
						'lineNumbers'       => true,
						'matchBrackets'     => true,
						'styleActiveLine' => true,
						'tabSize'           => 4,
					),
				)
			);
		}

		wp_enqueue_style(
			'cni-site-functions-admin',
			CNI_SITE_FUNCTIONS_URL . 'assets/admin.css',
			array(),
			CNI_SITE_FUNCTIONS_VERSION
		);
		wp_enqueue_script(
			'cni-site-functions-admin',
			CNI_SITE_FUNCTIONS_URL . 'assets/admin.js',
			false === $editor_settings ? array() : array( 'code-editor' ),
			CNI_SITE_FUNCTIONS_VERSION,
			true
		);
		wp_localize_script(
			'cni-site-functions-admin',
			'cniSiteFunctionsEditor',
			array(
				'textareaId' => 'cni-site-functions-code',
				'settings'   => false === $editor_settings ? null : $editor_settings,
			)
		);
	}

	/**
	 * Stop an unauthorized request.
	 *
	 * @return void
	 */
	private static function require_permission() {
		if ( ! self::can_manage() ) {
			wp_die(
				esc_html__( 'このページを操作する権限がありません。', 'cni-site-functions' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Store a short-lived result for the current administrator.
	 *
	 * @param array<string,mixed> $notice Notice data.
	 * @return void
	 */
	private static function set_notice( $notice ) {
		set_transient( self::NOTICE_PREFIX . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Return to the settings page.
	 *
	 * @return void
	 */
	private static function redirect_back() {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Save code only after server-side validation.
	 *
	 * @return void
	 */
	public static function handle_save() {
		self::require_permission();
		check_admin_referer( 'cni_site_functions_save' );

		if ( self::is_editor_disabled() ) {
			self::set_notice(
				array(
					'type'    => 'error',
					'title'   => __( '保存できませんでした。', 'cni-site-functions' ),
					'message' => __( 'CNI Site Functionsのコード編集は定数で無効化されています。', 'cni-site-functions' ),
				)
			);
			self::redirect_back();
		}

		$code = isset( $_POST['cni_site_functions_code'] ) && is_string( $_POST['cni_site_functions_code'] )
			? wp_unslash( $_POST['cni_site_functions_code'] )
			: '';
		$code = str_replace( array( "\r\n", "\r" ), "\n", $code );
		$code = preg_replace( '/\A\xEF\xBB\xBF/', '', $code );
		$code = is_string( $code ) ? $code : '';

		$enabled    = ! empty( $_POST['cni_site_functions_enabled'] );
		$validation = Code_Validator::validate( $code );

		if ( is_wp_error( $validation ) ) {
			$error_code = $validation->get_error_code();
			$error_data = $validation->get_error_data();
			if ( in_array( $error_code, array( 'forbidden_php_token', 'php_tag_not_allowed' ), true ) ) {
				$title = __( '使用できないPHP構文が含まれています。', 'cni-site-functions' );
			} elseif ( 'php_syntax_error' === $error_code ) {
				$title = __( 'PHPコードに構文エラーがあります。', 'cni-site-functions' );
			} else {
				$title = __( 'PHPコードを保存できません。', 'cni-site-functions' );
			}

			self::set_notice(
				array(
					'type'    => 'error',
					'title'   => $title,
					'message' => $validation->get_error_message(),
					'line'    => is_array( $error_data ) && isset( $error_data['line'] ) ? absint( $error_data['line'] ) : 0,
					'unchanged' => true,
					'draft'   => $code,
				)
			);
			self::redirect_back();
		}

		Code_Repository::save( $code, $enabled );
		self::set_notice(
			array(
				'type'    => 'success',
				'title'   => __( 'PHPコードに構文上の問題はありません。', 'cni-site-functions' ),
				'message' => $enabled
					? __( '変更を保存し、サイト固有PHPを有効にしました。次のリクエストから実行します。', 'cni-site-functions' )
					: __( '変更を保存しました。サイト固有PHPは無効です。', 'cni-site-functions' ),
			)
		);
		self::redirect_back();
	}

	/**
	 * Restore the previous syntax-validated saved version.
	 *
	 * @return void
	 */
	public static function handle_restore() {
		self::require_permission();
		check_admin_referer( 'cni_site_functions_restore' );

		if ( self::is_editor_disabled() ) {
			self::set_notice(
				array(
					'type'    => 'error',
					'title'   => __( '復元できませんでした。', 'cni-site-functions' ),
					'message' => __( 'CNI Site Functionsのコード編集は定数で無効化されています。', 'cni-site-functions' ),
				)
			);
			self::redirect_back();
		}

		$state      = Code_Repository::get_state();
		$validation = $state['has_previous'] ? Code_Validator::validate( $state['previous_code'] ) : true;

		if ( is_wp_error( $validation ) ) {
			self::set_notice(
				array(
					'type'    => 'error',
					'title'   => __( '前回保存版を復元できませんでした。', 'cni-site-functions' ),
					'message' => __( '前回保存版の検証に失敗したため、復元しませんでした。', 'cni-site-functions' ),
				)
			);
			self::redirect_back();
		}

		$restored = Code_Repository::restore_previous();
		self::set_notice(
			array(
				'type'    => $restored ? 'success' : 'warning',
				'title'   => $restored
					? __( '前回保存版へ戻しました。', 'cni-site-functions' )
					: __( '復元できるコードがありません。', 'cni-site-functions' ),
				'message' => $restored
					? __( '復元したコードは構文検証済みです。', 'cni-site-functions' )
					: __( '復元できる前回保存版がありません。', 'cni-site-functions' ),
			)
		);
		self::redirect_back();
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function handle_clear_error() {
		self::require_permission();
		check_admin_referer( 'cni_site_functions_clear_error' );
		Code_Repository::clear_execution_error();
		self::set_notice( array(
			'type' => 'success',
			'title' => __( 'エラー記録を削除しました。', 'cni-site-functions' ),
			'message' => __( '保存コードと有効状態は変更していません。監視中に再検知した場合は再び記録します。', 'cni-site-functions' ),
		) );
		self::redirect_back();
	}

	/** Describe recency only; absence of detection does not prove recovery. */
	public static function error_recency( $last_seen_at ) {
		$timestamp = strtotime( $last_seen_at . ' UTC' );
		if ( '' === $last_seen_at || false === $timestamp || $timestamp > time() ) {
			return __( '検知日時不明', 'cni-site-functions' );
		}
		return time() - $timestamp <= DAY_IN_SECONDS
			? __( '最近検知（24時間以内）', 'cni-site-functions' )
			: __( '過去の記録（最終検知から24時間超）', 'cni-site-functions' );
	}

	/** Render the settings page. */
	public static function render_page() {
		self::require_permission();

		$state      = Code_Repository::get_state();
		$notice_key = self::NOTICE_PREFIX . get_current_user_id();
		$notice     = get_transient( $notice_key );
		delete_transient( $notice_key );

		$editor_code      = $state['active_code'];
		$is_unsaved_draft = false;
		if ( is_array( $notice ) && isset( $notice['draft'] ) && is_string( $notice['draft'] ) ) {
			$editor_code      = $notice['draft'];
			$is_unsaved_draft = true;
		}

		if ( ! is_array( $notice ) ) {
			$notice = array(
				'type'    => 'info',
				'title'   => __( '検証結果はまだありません。', 'cni-site-functions' ),
				'message' => __( '「構文を検査して保存」を押すと、ここに結果を表示します。', 'cni-site-functions' ),
			);
		}

		$notice_type        = in_array( $notice['type'] ?? '', array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
		$editor_disabled    = self::is_editor_disabled();
		$safe_mode_reason   = Executor::get_safe_mode_reason();
		$readonly_attribute = wp_readonly( $editor_disabled, true, false );
		$escaped_editor_code = esc_textarea( $editor_code );
		?>
		<div class="wrap cni-site-functions-admin">
			<h1><?php esc_html_e( 'CNI Site Functions', 'cni-site-functions' ); ?></h1>

			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'このサイト固有のPHPコードを管理します。', 'cni-site-functions' ); ?></strong> <?php esc_html_e( 'テーマや子テーマを更新しても、ここに保存したコードは保持されます。', 'cni-site-functions' ); ?></p>
				<p><?php esc_html_e( '有効な保存コードは、テーマ読み込み後のafter_setup_themeで実行します。', 'cni-site-functions' ); ?></p>
				<p><?php esc_html_e( 'functions.phpとの完全互換ではありません。ショートコード、init、および一般的なfilter/action登録を主対象とし、after_setup_theme自体へ後から登録する処理は実行されません。', 'cni-site-functions' ); ?></p>
			</div>

			<?php if ( 'constant' === $safe_mode_reason ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'セーフモード中です。', 'cni-site-functions' ); ?></strong> <?php esc_html_e( 'CNI_SITE_FUNCTIONS_SAFE_MODE が有効なため、Executorは保存コードを読み込みません。', 'cni-site-functions' ); ?></p></div>
			<?php elseif ( 'stop_file' === $safe_mode_reason ) : ?>
				<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'セーフモード中です。', 'cni-site-functions' ); ?></strong> <?php echo esc_html( sprintf( __( '停止ファイル %s が存在するため、Executorは保存コードを読み込みません。', 'cni-site-functions' ), Executor::get_stop_file_path() ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $state['execution_error'] ) ) : ?>
				<?php $error = $state['execution_error']; $was_auto_disabled = $error['auto_disabled']; ?>
				<div class="notice <?php echo $was_auto_disabled ? 'notice-error' : 'notice-warning'; ?> inline">
					<p><strong><?php echo esc_html( in_array( $error['type'], array( 'fatal_error', 'unconfirmed_fatal_error' ), true ) ? __( 'Fatal Errorの記録があります。', 'cni-site-functions' ) : __( '実行エラーの記録があります。', 'cni-site-functions' ) ); ?></strong> <?php echo esc_html( self::error_recency( $error['last_seen_at'] ) ); ?></p>
					<p><?php esc_html_e( 'この表示は現在も発生中であることを意味しません。再検知がないことも、原因の解消を保証しません。', 'cni-site-functions' ); ?></p>
					<p><?php echo esc_html( sprintf( __( '初回検知（UTC）: %1$s ／ 最終検知（UTC）: %2$s ／ 検知回数: %3$d', 'cni-site-functions' ), $error['first_seen_at'] ?: __( '不明', 'cni-site-functions' ), $error['last_seen_at'] ?: __( '不明', 'cni-site-functions' ), $error['count'] ) ); ?></p>
					<p><?php echo esc_html( $error['attributable'] ? __( '由来: CNI Site Functionsの保存コード', 'cni-site-functions' ) : __( '由来: 未確認（外部プラグイン・テーマ等の可能性）', 'cni-site-functions' ) ); ?></p>
					<?php if ( $was_auto_disabled ) : ?>
						<p><?php esc_html_e( '検知時にサイト固有PHPを自動停止した記録です。現在の有効状態は下のチェック欄を確認してください。', 'cni-site-functions' ); ?></p>
					<?php endif; ?>
					<p><?php echo esc_html( $state['execution_error']['message'] ); ?></p>
					<?php if ( ! empty( $state['execution_error']['file'] ) ) : ?>
						<p><code><?php echo esc_html( $state['execution_error']['file'] ); ?><?php echo ! empty( $state['execution_error']['line'] ) ? ':' . esc_html( $state['execution_error']['line'] ) : ''; ?></code></p>
					<?php endif; ?>
					<?php if ( ! $was_auto_disabled ) : ?>
						<p><?php esc_html_e( 'CNI Site Functions由来と確認できなかったため、自動停止していません。問題が続く場合はセーフモード定数または停止ファイルを使用してください。', 'cni-site-functions' ); ?></p>
					<?php endif; ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="cni_site_functions_clear_error">
						<?php wp_nonce_field( 'cni_site_functions_clear_error' ); ?>
						<?php submit_button( __( 'この記録を削除', 'cni-site-functions' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'WordPress標準のファイル編集は禁止されています。このプラグインは独自定数で別管理されるため、現在は編集可能です。', 'cni-site-functions' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $editor_disabled ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'CNI_SITE_FUNCTIONS_DISABLE_EDITOR が有効なため、コードを編集できません。', 'cni-site-functions' ); ?></p></div>
			<?php endif; ?>

			<p class="cni-site-functions-admin__status"><strong><?php esc_html_e( 'サイト固有PHPを編集中', 'cni-site-functions' ); ?></strong></p>
			<p><?php esc_html_e( 'コード:', 'cni-site-functions' ); ?> <code>site-functions.php</code></p>
			<h2><?php esc_html_e( 'サイト固有PHPの内容:', 'cni-site-functions' ); ?></h2>
			<p><?php esc_html_e( '入力欄先頭のPHP開始タグと末尾の終了タグは不要です。関数内部でHTMLを出力するための ?> ... <?php は使用できます。', 'cni-site-functions' ); ?></p>
			<?php if ( $is_unsaved_draft ) : ?>
				<p class="description"><strong><?php esc_html_e( '現在の入力欄には、保存されなかった修正内容を再表示しています。', 'cni-site-functions' ); ?></strong></p>
			<?php endif; ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="cni_site_functions_save">
				<?php wp_nonce_field( 'cni_site_functions_save' ); ?>

				<p>
					<label>
						<input type="checkbox" name="cni_site_functions_enabled" value="1"<?php checked( $state['enabled'] ); ?><?php echo $editor_disabled ? ' disabled' : ''; ?>>
						<?php esc_html_e( 'サイト固有PHPを有効にする', 'cni-site-functions' ); ?>
					</label>
				</p>

				<label class="screen-reader-text" for="cni-site-functions-code"><?php esc_html_e( 'サイト固有PHPコード', 'cni-site-functions' ); ?></label>
				<textarea id="cni-site-functions-code" name="cni_site_functions_code" rows="28" spellcheck="false"<?php echo $readonly_attribute; ?>><?php echo $escaped_editor_code; ?></textarea>

				<?php submit_button( __( '構文を検査して保存', 'cni-site-functions' ), 'primary', 'submit', true, $editor_disabled ? array( 'disabled' => 'disabled' ) : array() ); ?>
			</form>

			<section class="cni-site-functions-validation cni-site-functions-validation--<?php echo esc_attr( $notice_type ); ?>" aria-live="polite">
				<h2><?php esc_html_e( '検証結果', 'cni-site-functions' ); ?></h2>
				<div class="cni-site-functions-validation__content">
					<h3><?php echo esc_html( $notice['title'] ?? '' ); ?></h3>
					<?php if ( ! empty( $notice['line'] ) ) : ?>
						<p><strong><?php echo esc_html( sprintf( __( 'Line %d', 'cni-site-functions' ), absint( $notice['line'] ) ) ); ?></strong></p>
					<?php endif; ?>
					<?php if ( ! empty( $notice['message'] ) ) : ?>
						<p class="cni-site-functions-validation__message"><?php echo esc_html( $notice['message'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $notice['unchanged'] ) ) : ?>
						<p><?php esc_html_e( '変更は保存されていません。現在保存されているコードは維持されています。', 'cni-site-functions' ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<?php if ( $state['has_previous'] ) : ?>
				<hr>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="cni_site_functions_restore">
					<?php wp_nonce_field( 'cni_site_functions_restore' ); ?>
					<?php submit_button( __( '前回保存版に戻す', 'cni-site-functions' ), 'secondary', 'submit', false, $editor_disabled ? array( 'disabled' => 'disabled' ) : array() ); ?>
				</form>
			<?php endif; ?>

			<?php if ( $state['updated_at'] ) : ?>
				<p class="description"><?php echo esc_html( sprintf( __( '最終保存（UTC）: %s', 'cni-site-functions' ), $state['updated_at'] ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
