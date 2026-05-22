<?php
/**
 * Plugin Name:  MoneyPath Game
 * Plugin URI:   https://littleexplorers.pl/moneypath
 * Description:  Osadza grę edukacyjną MoneyPath (Cashflow) na dowolnej stronie WordPress. Zawiera pełne meta-tagi SEO, Open Graph i Schema.org.
 * Version:      2.0.0
 * Author:       Jakub Krawczyk / Little Explorers sp. z o.o.
 * Author URI:   https://littleexplorers.pl
 * License:      MIT
 * Text Domain:  moneypath-game
 * Copyright:    (c) 2025 Little Explorers sp. z o.o. — Jakub Krawczyk
 *
 * ─────────────────────────────────────────────────────────────
 * UŻYCIE / USAGE
 * ─────────────────────────────────────────────────────────────
 * 1. Wgraj folder do /wp-content/plugins/moneypath-game/
 * 2. Aktywuj w WP Admin → Wtyczki
 * 3. Ustaw URL serwera: WP Admin → Ustawienia → MoneyPath
 * 4. Dodaj shortcode na dowolnej stronie:
 *
 *      [moneypath]
 *
 *    Pełny ekran (ukrywa nagłówek/stopkę WP):
 *      [moneypath fullscreen="yes"]
 *
 *    Nadpisanie URL lub wysokości:
 *      [moneypath url="https://game.littleexplorers.pl" height="95vh"]
 * ─────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;

// ── Stałe ────────────────────────────────────────────────────

define('MONEYPATH_VERSION', '2.0.0');
define('MONEYPATH_SLUG',    'moneypath-settings');

// ── Admin: menu ustawień ──────────────────────────────────────

add_action('admin_menu', function () {
    add_options_page(
        'MoneyPath — Ustawienia',
        'MoneyPath',
        'manage_options',
        MONEYPATH_SLUG,
        'moneypath_settings_page'
    );
});

add_action('admin_init', function () {
    $fields = [
        'moneypath_server_url'    => ['type' => 'string',  'sanitize' => 'esc_url_raw',        'default' => ''],
        'moneypath_seo_title'     => ['type' => 'string',  'sanitize' => 'sanitize_text_field', 'default' => 'MoneyPath – Gra Planszowa o Finansach | Little Explorers'],
        'moneypath_seo_desc'      => ['type' => 'string',  'sanitize' => 'sanitize_textarea_field', 'default' => 'Zagraj w MoneyPath – darmową, wieloosobową grę edukacyjną o finansach inspirowaną Cashflow. Naucz się inwestowania, pasywnego dochodu i wolności finansowej. Gra od Little Explorers.'],
        'moneypath_seo_keywords'  => ['type' => 'string',  'sanitize' => 'sanitize_text_field', 'default' => 'gra cashflow, moneypath, gra planszowa finanse, edukacja finansowa, little explorers, gra o pieniądzach, wolność finansowa, pasywny dochód, inwestowanie gra, finanse dla dzieci'],
        'moneypath_seo_image'     => ['type' => 'string',  'sanitize' => 'esc_url_raw',        'default' => 'https://littleexplorers.pl/wp-content/uploads/2023/06/logo.png'],
        'moneypath_schema_enable' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => true],
    ];
    foreach ($fields as $key => $cfg) {
        register_setting('moneypath_options', $key, [
            'type'              => $cfg['type'],
            'sanitize_callback' => $cfg['sanitize'],
            'default'           => $cfg['default'],
        ]);
    }
});

// ── Strona ustawień ───────────────────────────────────────────

function moneypath_settings_page() { ?>
<div class="wrap">
    <h1>🎮 MoneyPath — Ustawienia</h1>

    <form method="post" action="options.php">
        <?php settings_fields('moneypath_options'); ?>

        <h2>Serwer gry</h2>
        <table class="form-table">
            <tr>
                <th><label for="moneypath_server_url">URL serwera gry</label></th>
                <td>
                    <input type="url" id="moneypath_server_url" name="moneypath_server_url"
                           value="<?php echo esc_attr(get_option('moneypath_server_url')); ?>"
                           class="regular-text" placeholder="https://game.littleexplorers.pl">
                    <p class="description">Pełny adres URL serwera Node.js, np. <code>https://game.littleexplorers.pl</code></p>
                </td>
            </tr>
        </table>

        <h2>SEO — Meta tagi</h2>
        <table class="form-table">
            <tr>
                <th><label for="moneypath_seo_title">Tytuł strony (title)</label></th>
                <td>
                    <input type="text" id="moneypath_seo_title" name="moneypath_seo_title"
                           value="<?php echo esc_attr(get_option('moneypath_seo_title')); ?>"
                           class="large-text">
                    <p class="description">Tytuł widoczny w Google (maks. 60 znaków). Zawrzyj: MoneyPath, Cashflow, Little Explorers.</p>
                </td>
            </tr>
            <tr>
                <th><label for="moneypath_seo_desc">Opis strony (description)</label></th>
                <td>
                    <textarea id="moneypath_seo_desc" name="moneypath_seo_desc"
                              class="large-text" rows="3"><?php echo esc_textarea(get_option('moneypath_seo_desc')); ?></textarea>
                    <p class="description">Opis widoczny pod tytułem w Google (maks. 155 znaków). Użyj słów kluczowych naturalnie.</p>
                </td>
            </tr>
            <tr>
                <th><label for="moneypath_seo_keywords">Słowa kluczowe</label></th>
                <td>
                    <input type="text" id="moneypath_seo_keywords" name="moneypath_seo_keywords"
                           value="<?php echo esc_attr(get_option('moneypath_seo_keywords')); ?>"
                           class="large-text">
                    <p class="description">Oddzielone przecinkami. Główne: <em>gra cashflow, moneypath, edukacja finansowa, little explorers</em></p>
                </td>
            </tr>
            <tr>
                <th><label for="moneypath_seo_image">Obraz Open Graph (URL)</label></th>
                <td>
                    <input type="url" id="moneypath_seo_image" name="moneypath_seo_image"
                           value="<?php echo esc_attr(get_option('moneypath_seo_image')); ?>"
                           class="large-text" placeholder="https://littleexplorers.pl/wp-content/uploads/...">
                    <p class="description">Obraz wyświetlany przy udostępnianiu w mediach społecznościowych. Zalecany rozmiar: 1200×630 px.</p>
                </td>
            </tr>
            <tr>
                <th>Schema.org</th>
                <td>
                    <label>
                        <input type="checkbox" name="moneypath_schema_enable" value="1"
                               <?php checked(1, get_option('moneypath_schema_enable', 1)); ?>>
                        Dodaj strukturalne dane JSON-LD (WebApplication + Organization)
                    </label>
                    <p class="description">Pomaga Google zrozumieć, że strona zawiera grę. Zalecane włączenie.</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Zapisz ustawienia'); ?>
    </form>

    <hr>
    <h2>Shortcodes</h2>
    <table class="widefat" style="max-width:700px;">
        <thead><tr><th>Shortcode</th><th>Opis</th></tr></thead>
        <tbody>
            <tr><td><code>[moneypath]</code></td><td>Standardowe osadzenie (92vh)</td></tr>
            <tr><td><code>[moneypath fullscreen="yes"]</code></td><td>Pełny ekran — ukrywa nagłówek i stopkę WP</td></tr>
            <tr><td><code>[moneypath height="85vh"]</code></td><td>Własna wysokość</td></tr>
            <tr><td><code>[moneypath url="https://..."]</code></td><td>Własny URL serwera (nadpisuje ustawienia)</td></tr>
        </tbody>
    </table>
</div>
<?php }

// ── SEO: meta tagi w <head> ───────────────────────────────────

add_action('wp_head', function () {
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'moneypath')) return;

    $title    = get_option('moneypath_seo_title',    '');
    $desc     = get_option('moneypath_seo_desc',     '');
    $keywords = get_option('moneypath_seo_keywords', '');
    $image    = get_option('moneypath_seo_image',    '');
    $url      = get_permalink($post->ID);

    // Canonical + robots
    echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";

    // Standard meta
    if ($title)    echo '<meta name="title"       content="' . esc_attr($title)    . '">' . "\n";
    if ($desc)     echo '<meta name="description" content="' . esc_attr($desc)     . '">' . "\n";
    if ($keywords) echo '<meta name="keywords"    content="' . esc_attr($keywords) . '">' . "\n";
    echo '<meta name="author" content="Little Explorers sp. z o.o.">' . "\n";
    echo '<meta name="language" content="pl">' . "\n";

    // Open Graph
    echo '<meta property="og:type"        content="website">' . "\n";
    echo '<meta property="og:url"         content="' . esc_attr($url)   . '">' . "\n";
    echo '<meta property="og:site_name"   content="Little Explorers">' . "\n";
    if ($title) echo '<meta property="og:title"       content="' . esc_attr($title) . '">' . "\n";
    if ($desc)  echo '<meta property="og:description" content="' . esc_attr($desc)  . '">' . "\n";
    if ($image) {
        echo '<meta property="og:image"       content="' . esc_attr($image) . '">' . "\n";
        echo '<meta property="og:image:width" content="1200">' . "\n";
        echo '<meta property="og:image:height" content="630">' . "\n";
        echo '<meta property="og:image:alt"   content="MoneyPath – gra edukacyjna o finansach">' . "\n";
    }
    echo '<meta property="og:locale"      content="pl_PL">' . "\n";

    // Twitter Card
    echo '<meta name="twitter:card"        content="summary_large_image">' . "\n";
    echo '<meta name="twitter:site"        content="@LittleExplorers">' . "\n";
    if ($title) echo '<meta name="twitter:title"       content="' . esc_attr($title) . '">' . "\n";
    if ($desc)  echo '<meta name="twitter:description" content="' . esc_attr($desc)  . '">' . "\n";
    if ($image) echo '<meta name="twitter:image"       content="' . esc_attr($image) . '">' . "\n";

    // Schema.org JSON-LD
    if (get_option('moneypath_schema_enable', 1)) {
        $schema = [
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type'           => 'WebApplication',
                    'name'            => 'MoneyPath',
                    'alternateName'   => ['Gra Cashflow', 'MoneyPath Little Explorers'],
                    'description'     => $desc ?: 'Wieloosobowa gra edukacyjna o finansach inspirowana Cashflow. Naucz się inwestowania i wolności finansowej.',
                    'url'             => esc_url($url),
                    'applicationCategory' => 'Game',
                    'operatingSystem' => 'Any',
                    'offers'          => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'PLN'],
                    'inLanguage'      => 'pl',
                    'genre'           => ['Educational', 'Board Game', 'Finance'],
                    'keywords'        => $keywords,
                    'image'           => $image,
                    'publisher'       => [
                        '@type' => 'Organization',
                        'name'  => 'Little Explorers sp. z o.o.',
                        'url'   => 'https://littleexplorers.pl',
                        'logo'  => ['@type' => 'ImageObject', 'url' => 'https://littleexplorers.pl/wp-content/uploads/2023/06/logo.png'],
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Strona główna', 'item' => home_url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'MoneyPath', 'item' => esc_url($url)],
                    ],
                ],
            ],
        ];
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}, 1); // priority 1 — przed innymi wtyczkami SEO

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
                 . '⚠️ <strong>MoneyPath:</strong> Ustaw URL serwera w '
                 . '<a href="' . admin_url('options-general.php?page=' . MONEYPATH_SLUG) . '">Ustawienia → MoneyPath</a>.'
                 . '</div>';
        }
        return '';
    }

    if (!wp_style_is('moneypath-game', 'enqueued')) {
        wp_enqueue_style('moneypath-game', plugin_dir_url(__FILE__) . 'moneypath-game.css', [], MONEYPATH_VERSION);
    }

    $fs_class = '';
    if ($fullscreen) {
        $fs_class = 'moneypath-fullscreen';
        add_action('wp_head', 'moneypath_fullscreen_styles');
    }

    $iframe_id = 'moneypath-iframe-' . wp_unique_id();

    return sprintf(
        '<div class="moneypath-wrapper %s" style="--mp-height:%s;" itemscope itemtype="https://schema.org/Game">
            <meta itemprop="name" content="MoneyPath">
            <meta itemprop="description" content="Gra edukacyjna o finansach i wolności finansowej">
            <iframe
                id="%s"
                class="moneypath-iframe"
                src="%s"
                allow="autoplay; fullscreen; orientation-lock"
                allowfullscreen
                loading="lazy"
                title="MoneyPath — Gra Edukacyjna o Finansach | Little Explorers"
                aria-label="MoneyPath — gra planszowa o finansach"
            ></iframe>
        </div>',
        esc_attr($fs_class),
        esc_attr($height),
        esc_attr($iframe_id),
        $url
    );
}

// ── Fullscreen: ukryj nagłówek/stopkę WP ─────────────────────

function moneypath_fullscreen_styles() {
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <style id="moneypath-fullscreen-css">
        .site-header, header.site-header, #masthead,
        .site-footer, footer.site-footer, #colophon,
        .widget-area, #secondary,
        .entry-header, .page-header,
        #wpadminbar,
        .wp-block-post-title,
        .navigation, .nav-links,
        .breadcrumbs, .breadcrumb { display: none !important; }

        body { margin-top: 0 !important; padding-top: 0 !important; }

        .site-main, main, #main, #primary,
        .wp-site-blocks { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }

        .moneypath-fullscreen .moneypath-iframe { height: 100dvh !important; border-radius: 0 !important; }
    </style>
    <?php
}

// ── Enqueue stylesheet ────────────────────────────────────────

add_action('wp_enqueue_scripts', function () {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'moneypath')) {
        wp_enqueue_style('moneypath-game', plugin_dir_url(__FILE__) . 'moneypath-game.css', [], MONEYPATH_VERSION);
    }
});

// ── Preconnect do serwera gry (wydajność) ─────────────────────

add_action('wp_head', function () {
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'moneypath')) return;
    $server_url = get_option('moneypath_server_url', '');
    if (!$server_url) return;
    $parsed = wp_parse_url($server_url);
    if (!empty($parsed['host'])) {
        $origin = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
        echo '<link rel="preconnect" href="' . esc_attr($origin) . '">' . "\n";
        echo '<link rel="dns-prefetch" href="' . esc_attr($origin) . '">' . "\n";
    }
}, 2);
