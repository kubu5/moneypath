<?php
/**
 * Plugin Name:  MoneyPath Game
 * Plugin URI:   https://github.com/kubu5/moneypath
 * Description:  Embeds the MoneyPath educational financial board game on any page or post using a shortcode.
 * Version:      1.0.0
 * Author:       Jakub Krawczyk / Little Explorers sp. z o.o.
 * Author URI:   https://github.com/kubu5
 * License:      MIT
 * Copyright:    (c) 2025 Little Explorers sp. z o.o. — Jakub Krawczyk
 *
 * ─────────────────────────────────────────────────────────────
 * USAGE
 * ─────────────────────────────────────────────────────────────
 * 1. Upload this folder to /wp-content/plugins/moneypath-game/
 * 2. Activate the plugin in WP Admin → Plugins
 * 3. Go to WP Admin → Settings → MoneyPath to set the server URL
 * 4. Add shortcode to any page:
 *
 *      [moneypath]
 *
 *    Optional override:
 *      [moneypath url="https://your-server.railway.app" height="95vh"]
 *
 * 5. (Recommended) Create a dedicated page, enable "Full-Screen Mode"
 *    in the shortcode to hide WordPress header/footer:
 *      [moneypath fullscreen="yes"]
 * ─────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;

// ── Admin settings page ───────────────────────────────────────

add_action('admin_menu', function () {
    add_options_page(
        'MoneyPath Settings',
        'MoneyPath',
        'manage_options',
        'moneypath-settings',
        'moneypath_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('moneypath_options', 'moneypath_server_url', [
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ]);
});

function moneypath_settings_page() {
    ?>
    <div class="wrap">
        <h1>🎮 MoneyPath Game Settings</h1>
        <p>Deploy the Node.js game server (see README) and paste its URL below.</p>
        <form method="post" action="options.php">
            <?php settings_fields('moneypath_options'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="moneypath_server_url">Game Server URL</label></th>
                    <td>
                        <input type="url"
                               id="moneypath_server_url"
                               name="moneypath_server_url"
                               value="<?php echo esc_attr(get_option('moneypath_server_url')); ?>"
                               class="regular-text"
                               placeholder="https://game.littleexplorers.pl">
                        <p class="description">
                            Full URL of your deployed Node.js server, e.g.
                            <code>https://game.littleexplorers.pl</code>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>

        <hr>
        <h2>Shortcode Usage</h2>
        <p>Paste this into any page or post (use the "Shortcode" block in Gutenberg):</p>
        <code>[moneypath]</code>
        <p>Full-screen (hides WP header &amp; footer on that page):</p>
        <code>[moneypath fullscreen="yes"]</code>
        <p>Override URL or height for a specific embed:</p>
        <code>[moneypath url="https://other-server.com" height="85vh"]</code>
    </div>
    <?php
}

// ── Shortcode [moneypath] ─────────────────────────────────────

add_shortcode('moneypath', 'moneypath_shortcode');

function moneypath_shortcode($atts) {
    $atts = shortcode_atts([
        'url'        => get_option('moneypath_server_url', ''),
        'height'     => '92vh',
        'fullscreen' => 'no',
    ], $atts, 'moneypath');

    $url        = esc_url($atts['url']);
    $height     = esc_attr($atts['height']);
    $fullscreen = ($atts['fullscreen'] === 'yes');

    if (empty($url)) {
        if (current_user_can('manage_options')) {
            return '<div style="padding:20px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;">'
                 . '⚠️ <strong>MoneyPath:</strong> Set the server URL in '
                 . '<a href="' . admin_url('options-general.php?page=moneypath-settings') . '">Settings → MoneyPath</a>.'
                 . '</div>';
        }
        return ''; // Hide from visitors if not configured
    }

    // Enqueue our CSS (once per page)
    if (!wp_style_is('moneypath-game', 'enqueued')) {
        wp_enqueue_style('moneypath-game', plugin_dir_url(__FILE__) . 'moneypath-game.css', [], '1.0.0');
    }

    // Full-screen mode: output inline <style> to suppress theme chrome
    $fs_class = '';
    if ($fullscreen) {
        $fs_class = 'moneypath-fullscreen';
        add_action('wp_head', 'moneypath_fullscreen_styles');
    }

    $iframe_id = 'moneypath-iframe-' . uniqid();

    return sprintf(
        '<div class="moneypath-wrapper %s" style="--mp-height:%s;">
            <iframe
                id="%s"
                class="moneypath-iframe"
                src="%s"
                allow="autoplay; fullscreen"
                allowfullscreen
                loading="lazy"
                title="MoneyPath — Financial Board Game"
            ></iframe>
        </div>',
        esc_attr($fs_class),
        esc_attr($height),
        esc_attr($iframe_id),
        $url
    );
}

// ── Fullscreen: suppress WP header/footer/sidebar ─────────────

function moneypath_fullscreen_styles() {
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <style id="moneypath-fullscreen-css">
        /* Hide common WordPress theme elements when game is in fullscreen mode */
        .site-header, header.site-header, #masthead,
        .site-footer, footer.site-footer, #colophon,
        .widget-area, #secondary,
        .entry-header, .page-header,
        #wpadminbar,
        .wp-block-post-title,
        .navigation, .nav-links,
        .breadcrumbs, .breadcrumb { display: none !important; }

        body.moneypath-page { margin-top: 0 !important; padding-top: 0 !important; }

        /* Stretch content area to full width */
        .moneypath-fullscreen .moneypath-wrapper,
        .site-main, main, #main, #primary,
        .wp-site-blocks { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }

        .moneypath-fullscreen .moneypath-iframe { height: 100dvh !important; border-radius: 0 !important; }
    </style>
    <?php
}

// ── Enqueue stylesheet ────────────────────────────────────────

add_action('wp_enqueue_scripts', function () {
    // Only enqueue on pages that use the shortcode
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'moneypath')) {
        wp_enqueue_style('moneypath-game', plugin_dir_url(__FILE__) . 'moneypath-game.css', [], '1.0.0');
    }
});
