<?php
/**
 * Plugin Name:  MoneyPath Game
 * Plugin URI:   https://github.com/kubu5/moneypath
 * Description:  Embeds the MoneyPath educational financial board game with SEO, Open Graph and Schema.org structured data.
 * Version:      2.0.0
 * Author:       Jakub Krawczyk / Little Explorers sp. z o.o.
 * Author URI:   https://littleexplorers.pl
 * License:      MIT
 * Text Domain:  moneypath-game
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
    register_setting('moneypath_options', 'moneypath_seo_title', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'MoneyPath — Gra Edukacyjna o Finansach | Little Explorers',
    ]);
    register_setting('moneypath_options', 'moneypath_seo_description', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => 'Zagraj w MoneyPath – edukacyjną grę planszową online o finansach dla dzieci i rodzin. Naucz się zarządzać budżetem, inwestować i osiągać wolność finansową. Graj online lub lokalnie!',
    ]);
    register_setting('moneypath_options', 'moneypath_og_image', [
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ]);
    register_setting('moneypath_options', 'moneypath_page_id', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ]);
});

function moneypath_settings_page() {
    ?>
    <div class="wrap">
        <h1>🎮 MoneyPath Game — Ustawienia</h1>

        <form method="post" action="options.php">
            <?php settings_fields('moneypath_options'); ?>

            <h2>Serwer gry</h2>
            <table class="form-table">
                <tr>
                    <th><label for="moneypath_server_url">URL serwera gry</label></th>
                    <td>
                        <input type="url"
                               id="moneypath_server_url"
                               name="moneypath_server_url"
                               value="<?php echo esc_attr(get_option('moneypath_server_url')); ?>"
                               class="regular-text"
                               placeholder="https://moneypath.twoja-domena.pl">
                        <p class="description">Pełny URL serwera Node.js (musi być HTTPS).</p>
                    </td>
                </tr>
            </table>

            <h2>SEO — strona z grą</h2>
            <table class="form-table">
                <tr>
                    <th><label for="moneypath_page_id">Strona z grą (ID)</label></th>
                    <td>
                        <?php
                        wp_dropdown_pages([
                            'name'             => 'moneypath_page_id',
                            'id'               => 'moneypath_page_id',
                            'selected'         => get_option('moneypath_page_id', 0),
                            'show_option_none' => '— wybierz stronę —',
                            'option_none_value'=> '0',
                        ]);
                        ?>
                        <p class="description">Wybierz stronę, na której jest shortcode [moneypath]. Na niej zostaną zastosowane meta tagi SEO.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="moneypath_seo_title">Tytuł SEO</label></th>
                    <td>
                        <input type="text"
                               id="moneypath_seo_title"
                               name="moneypath_seo_title"
                               value="<?php echo esc_attr(get_option('moneypath_seo_title')); ?>"
                               class="large-text">
                        <p class="description">Tytuł strony w wynikach Google i przy udostępnianiu w social media.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="moneypath_seo_description">Opis SEO</label></th>
                    <td>
                        <textarea id="moneypath_seo_description"
                                  name="moneypath_seo_description"
                                  class="large-text"
                                  rows="3"><?php echo esc_textarea(get_option('moneypath_seo_description')); ?></textarea>
                        <p class="description">Opis widoczny w wynikach wyszukiwania (max 160 znaków).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="moneypath_og_image">Obraz Open Graph (URL)</label></th>
                    <td>
                        <input type="url"
                               id="moneypath_og_image"
                               name="moneypath_og_image"
                               value="<?php echo esc_attr(get_option('moneypath_og_image')); ?>"
                               class="large-text"
                               placeholder="https://littleexplorers.pl/wp-content/uploads/moneypath-cover.jpg">
                        <p class="description">Obrazek wyświetlany przy udostępnianiu linku (Facebook, WhatsApp itp.). Zalecany rozmiar: 1200×630 px.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Zapisz ustawienia'); ?>
        </form>

        <hr>
        <h2>Jak używać</h2>
        <p>Wstaw na stronie z grą (blok Shortcode w Gutenbergu):</p>
        <code>[moneypath fullscreen="yes"]</code>
        <p>Nadpisanie URL lub wysokości dla konkretnego osadzenia:</p>
        <code>[moneypath url="https://inny-serwer.pl" height="85vh"]</code>
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
                 . '⚠️ <strong>MoneyPath:</strong> Ustaw URL serwera w '
                 . '<a href="' . admin_url('options-general.php?page=moneypath-settings') . '">Ustawienia → MoneyPath</a>.'
                 . '</div>';
        }
        return '';
    }

    if (!wp_style_is('moneypath-game', 'enqueued')) {
        wp_enqueue_style('moneypath-game', plugin_dir_url(__FILE__) . 'moneypath-game.css', [], '2.0.0');
    }

    if ($fullscreen) {
        add_action('wp_head', 'moneypath_fullscreen_styles');
        add_filter('body_class', function ($classes) {
            $classes[] = 'moneypath-page';
            return $classes;
        });
    }

    $iframe_id = 'moneypath-iframe-' . uniqid();
    $fs_class  = $fullscreen ? 'moneypath-fullscreen' : '';

    return sprintf(
        '<div class="moneypath-wrapper %s" style="--mp-height:%s;">
            <iframe
                id="%s"
                class="moneypath-iframe"
                src="%s"
                allow="autoplay; fullscreen"
                allowfullscreen
                loading="lazy"
                title="MoneyPath — Edukacyjna Gra Finansowa"
            ></iframe>
        </div>',
        esc_attr($fs_class),
        esc_attr($height),
        esc_attr($iframe_id),
        $url
    );
}

// ── SEO meta tags ─────────────────────────────────────────────

add_action('wp_head', 'moneypath_inject_seo_meta', 1);

function moneypath_inject_seo_meta() {
    $page_id = (int) get_option('moneypath_page_id', 0);
    if (!$page_id || !is_page($page_id)) return;

    $title       = get_option('moneypath_seo_title',       'MoneyPath — Gra Edukacyjna o Finansach | Little Explorers');
    $description = get_option('moneypath_seo_description', 'Zagraj w MoneyPath – edukacyjną grę planszową online o finansach dla dzieci i rodzin.');
    $og_image    = get_option('moneypath_og_image', '');
    $server_url  = get_option('moneypath_server_url', '');
    $canonical   = get_permalink($page_id);
    $site_name   = get_bloginfo('name');

    // Disable Yoast/RankMath on this page to avoid duplicates
    add_filter('wpseo_canonical', '__return_false');
    add_filter('rank_math/frontend/disable_seo', '__return_true');
    ?>
<!-- MoneyPath SEO -->
<title><?php echo esc_html($title); ?></title>
<meta name="description"        content="<?php echo esc_attr($description); ?>">
<meta name="keywords"           content="gra edukacyjna finanse, nauka finansów dzieci, gra planszowa online, wolność finansowa, MoneyPath, Little Explorers, edukacja finansowa">
<meta name="author"             content="Little Explorers sp. z o.o.">
<meta name="robots"             content="index, follow">
<link rel="canonical"           href="<?php echo esc_url($canonical); ?>">

<!-- Open Graph (Facebook, LinkedIn, WhatsApp) -->
<meta property="og:type"        content="website">
<meta property="og:url"         content="<?php echo esc_url($canonical); ?>">
<meta property="og:title"       content="<?php echo esc_attr($title); ?>">
<meta property="og:description" content="<?php echo esc_attr($description); ?>">
<meta property="og:site_name"   content="<?php echo esc_attr($site_name); ?>">
<meta property="og:locale"      content="pl_PL">
<?php if ($og_image) : ?>
<meta property="og:image"       content="<?php echo esc_url($og_image); ?>">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt"   content="MoneyPath — edukacyjna gra finansowa">
<?php endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?php echo esc_attr($title); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
<?php if ($og_image) : ?>
<meta name="twitter:image"       content="<?php echo esc_url($og_image); ?>">
<?php endif; ?>

<!-- Schema.org — SoftwareApplication (gra) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "MoneyPath",
  "applicationCategory": "Game",
  "operatingSystem": "Web",
  "description": <?php echo json_encode($description); ?>,
  "url": <?php echo json_encode($canonical); ?>,
  "author": {
    "@type": "Organization",
    "name": "Little Explorers sp. z o.o.",
    "url": "https://littleexplorers.pl"
  },
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "PLN"
  },
  "genre": "Educational",
  "inLanguage": "pl",
  "isAccessibleForFree": true,
  "audience": {
    "@type": "PeopleAudience",
    "suggestedMinAge": "10"
  }
}
</script>
<!-- /MoneyPath SEO -->
    <?php
}

// ── Fullscreen styles ─────────────────────────────────────────

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

        body.moneypath-page { margin-top: 0 !important; padding-top: 0 !important; }

        .site-main, main, #main, #primary,
        .wp-site-blocks { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }

        .moneypath-fullscreen .moneypath-iframe { height: 100dvh !important; border-radius: 0 !important; }
    </style>
    <?php
}

// ── Enqueue styles ────────────────────────────────────────────

add_action('wp_enqueue_scripts', function () {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'moneypath')) {
        wp_enqueue_style('moneypath-game', plugin_dir_url(__FILE__) . 'moneypath-game.css', [], '2.0.0');
    }
});

// ── SSL check — warn admin if server URL is HTTP ──────────────

add_action('admin_notices', function () {
    $url = get_option('moneypath_server_url', '');
    if ($url && strpos($url, 'http://') === 0) {
        echo '<div class="notice notice-error"><p>'
           . '⚠️ <strong>MoneyPath:</strong> URL serwera gry używa <code>http://</code>. '
           . 'Twoja strona WordPress działa na HTTPS, dlatego przeglądarka zablokuje iframe. '
           . '<a href="' . admin_url('options-general.php?page=moneypath-settings') . '">Zmień URL na https://</a>.'
           . '</p></div>';
    }
});
