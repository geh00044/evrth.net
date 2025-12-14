<?php
/**
 * X-T9 functions
 *
 * @package vektor-inc/x-t9
 */

// Composer autoload.
require_once __DIR__ . '/vendor/autoload.php';

$theme_opt = wp_get_theme( get_template() );

define( 'XT9_THEME_VERSION', $theme_opt->Version ); // phpcs:ignore

if ( ! function_exists( 'xt9_support' ) ) :
	function xt9_support() {

		// Adding support for core block visual styles.
		add_theme_support( 'wp-block-styles' );

		// Enqueue editor styles.
		add_editor_style( 'assets/css/style.css' );

		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '6.6', '>=' ) ) {
			// WordPress over 6.6
			add_editor_style( 'assets/css/editor.css' );
		} else {
			// WordPress under 6.5
			add_editor_style( 'assets/css/editor-wp65.css' );
		}
	}
	add_action( 'after_setup_theme', 'xt9_support' );
endif;

/**
 * Enqueue scripts and styles.
 */
function xt9_scripts() {
	// Enqueue theme stylesheet.
	wp_enqueue_style( 'x-t9-style', get_template_directory_uri() . '/assets/css/style.css', array(), wp_get_theme()->get( 'Version' ) );
}

add_action( 'wp_enqueue_scripts', 'xt9_scripts' );

/**
 * Load JavaScript
 *
 * @return void
 */
function xt9_add_script() {
	wp_register_script( 'xt9-js', get_template_directory_uri() . '/assets/js/main.js', array(), XT9_THEME_VERSION, true );
	$options = array(
		'header_scrool' => true,
	);
	wp_localize_script( 'xt9-js', 'xt9Opt', apply_filters( 'xt9_localize_options', $options ) );
	wp_enqueue_script( 'xt9-js' );
}
add_action( 'wp_enqueue_scripts', 'xt9_add_script' );


// Layout helpers.
require get_template_directory() . '/inc/layout-helpers.php';
// Add block patterns.
require get_template_directory() . '/inc/block-patterns.php';
// Add Block Styles.
require get_template_directory() . '/inc/block-styles.php';
// Load TGM.
require get_template_directory() . '/inc/tgm-plugin-activation/tgm-config.php';

/**
 * Archive title
 *
 * @return string archive title
 */
function xt9_get_the_archive_title() {
	$title = '';
	if ( is_category() ) {
		$title = single_cat_title( '', false );
	} elseif ( is_tag() ) {
		$title = single_tag_title( '', false );
	} elseif ( is_author() ) {
		$title = get_the_author();
	} elseif ( is_year() ) {
		$title = get_the_date( _x( 'Y', 'yearly archives date format', 'x-t9' ) );
	} elseif ( is_month() ) {
		$title = get_the_date( _x( 'F Y', 'monthly archives date format', 'x-t9' ) );
	} elseif ( is_day() ) {
		$title = get_the_date( _x( 'F j, Y', 'daily archives date format', 'x-t9' ) );
	} elseif ( is_post_type_archive() ) {
		$title = post_type_archive_title( '', false );
	} elseif ( is_tax() ) {
		$title = single_term_title( '', false );
	} elseif ( is_home() && ! is_front_page() ) {
		// Get post top page by setting display page.
		$post_top_id = get_option( 'page_for_posts' );
		if ( $post_top_id ) {
			$title = get_the_title( $post_top_id );
		}
	} else {
		global $wp_query;
		// get post type.
		if ( ! empty( $wp_query->query_vars['post_type'] ) ) {
			$post_type = $wp_query->query_vars['post_type'];
			$title     = get_post_type_object( $post_type )->labels->name;
		} else {
			$title = __( 'Archives', 'x-t9' );
		}
	}
	return apply_filters( 'xt9_get_the_archive_title', $title );
}
add_filter( 'get_the_archive_title', 'xt9_get_the_archive_title' );

/**
 * Year Artchive list 'year' and count insert to inner </a>
 *
 * @param string $html link html.
 * @return string $html added string html
 */
function xt9_archives_link( $html ) {
	return preg_replace( '@</a>(.+?)</li>@', '\1</a></li>', $html );
}
add_filter( 'get_archives_link', 'xt9_archives_link' );

/**
 * Category list count insert to inner </a>
 *
 * @param string $output : output html.
 * @param array  $args : list categories args.
 * @return string $output : return string
 */
function xt9_list_categories( $output, $args ) {
	$output = preg_replace( '/<\/a>\s*\((\d+)\)/', ' ($1)</a>', $output );
	return $output;
}
add_filter( 'wp_list_categories', 'xt9_list_categories', 10, 2 );

// WooCommerce が有効な場合のみ WooCommerce 用の CSS を読み込む
function x_t9_enqueue_woocommerce_css() {
	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style( 'x-t9-woo-style', get_template_directory_uri() . '/plugin-support/woocommerce/css/woo.css', array( 'x-t9-style' ), '1.0.0' );    }
}
add_action( 'wp_enqueue_scripts', 'x_t9_enqueue_woocommerce_css' );

/**
 * Navigation Submenu block do render menu item description
 * 6.8がリリースされたら削除する
 */
// Navigation Link ブロックとは異なり、Navigation Submenu ブロックはメニュー項目の説明 HTML をレンダリングしないため追加。
// Navigation Submenu block does not render menu item description #52505
function xt9_add_description_to_navigation_items( $block_content, $block ) {
	if ( 'core/navigation-submenu' === $block['blockName'] && ! empty( $block['attrs']['description'] ) ) {
		$description = esc_attr( $block['attrs']['description'] );
		// 説明用のspanタグを作成
		$description_span = '<span class="wp-block-navigation-item__description">' . $description . '</span>';
		// aタグ内の最後に説明を挿入
		// 正規表現を用いて、aタグの終了直前に挿入
		$block_content = preg_replace( '/<\/a>/', $description_span . '</a>', $block_content, 1 );
	}
	return $block_content;
}
$version = get_bloginfo('version');
if ( version_compare( preg_replace('/[^0-9.]/', '', $version), '6.8', '<' ) ) {
	// 6.8 未満で実行（ 6.8 RC版やBeta版も除外 ）
	// Run with a version earlier than 6.8 (excluding 6.8 RC and Beta versions)
    add_filter( 'render_block', 'xt9_add_description_to_navigation_items', 10, 2 );
}

/**
 * =========================================================
 * Google Cloud Translation API (v2) - APIキー版
 * =========================================================
 */

/**
 * 1. APIキーの定義 (キーをサーバーサイドに隠蔽)
 * ※二重定義でFatalにならないようガード
 */
if ( ! defined( 'EVRTH_TRANSLATION_API_KEY' ) ) {
	define( 'EVRTH_TRANSLATION_API_KEY', 'AIzaSyAX_zTn2VczSAvFwL6AvqNAaSCZLtAoxws' );
}

/**
 * 2. 翻訳リクエストをサーバー側で処理するAjaxハンドラー (v2)
 */
function evrth_translate_ajax_handler() {
	// Nonceチェック
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'evrth_translation_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Nonce認証エラー' ), 403 );
	}

	// 必須パラメーターのチェック
	if ( ! isset( $_POST['texts'] ) || ! isset( $_POST['targetLang'] ) ) {
		wp_send_json_error( array( 'message' => '必要なパラメーターが不足しています。' ), 400 );
	}

	// JSON文字列として渡されたtextsをデコード（WP流儀: wp_unslash）
	$texts_json = wp_unslash( $_POST['texts'] );
	$texts      = json_decode( $texts_json, true );

	if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $texts ) ) {
		wp_send_json_error( array( 'message' => 'Textsパラメーターのデコードエラー' ), 400 );
	}

	// データサニタイズ
	$texts      = array_map( 'sanitize_text_field', $texts );
	$targetLang = sanitize_text_field( $_POST['targetLang'] );

	$apiUrl = 'https://translation.googleapis.com/language/translate/v2?key=' . EVRTH_TRANSLATION_API_KEY;

	// 翻訳リクエストの構築と送信 (v2形式)
	$body = wp_json_encode(
		array(
			'q'      => $texts,
			'target' => $targetLang,
			'source' => 'ja',
			'format' => 'text',
		)
	);

	$response = wp_remote_post(
		$apiUrl,
		array(
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8',
			),
			'body'    => $body,
			'timeout' => 45,
		)
	);

	// 接続エラーの処理
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'APIへの接続エラー: ' . $response->get_error_message() ), 500 );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

	// API側からのエラーの処理
	if ( 200 !== (int) $response_code || isset( $response_body['error'] ) ) {
		$error_message = $response_body['error']['message'] ?? 'APIエラーが発生しました。';
		$status_code   = $response_body['error']['code'] ?? 500;
		wp_send_json_error( array( 'message' => $error_message ), (int) $status_code );
	}

	// 成功レスポンス
	// v2は response_body['data']['translations'] に配列が入る想定
	wp_send_json_success( $response_body['data']['translations'] );
}

add_action( 'wp_ajax_evrth_translate', 'evrth_translate_ajax_handler' );
add_action( 'wp_ajax_nopriv_evrth_translate', 'evrth_translate_ajax_handler' );

/**
 * 3. JavaScriptファイルの読み込みとAjax情報の埋め込み
 */
function evrth_enqueue_translation_script() {
	// PCだけでOKの場合は有効化（スマホでは読み込まない）
	// ※スマホも必要なら、この if ブロックを削除
	if ( wp_is_mobile() ) {
		return;
	}

	// translator.js をフッターで読み込む
	// 例: /wp-content/themes/x-t9/js/translator.js
	wp_enqueue_script(
		'evrth-translator',
		get_template_directory_uri() . '/js/translator.js',
		array(),
		'1.1',
		true
	);

	// JSへ Ajax URL と nonce を渡す
	wp_localize_script(
		'evrth-translator',
		'evrthAjax',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'evrth_translation_nonce' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'evrth_enqueue_translation_script' );
