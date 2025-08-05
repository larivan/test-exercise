<?php
/**
 * Plugin Name: A/B Test Plugin
 * Description: Мини-плагин для A/B теста блока (варианты A и B).
 * Version: 1.0
 * Author: Artem I.
 */

if (!defined('ABSPATH')) exit;

$ab_test_option_key = 'ab_test_stats';

// подключение JS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'ab-test-js',
        plugin_dir_url(__FILE__) . 'assets/ab-test.js',
        ['jquery'],
        '1.0',
        true
    );
    wp_localize_script('ab-test-js', 'ABTest', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ab_test_nonce'),
    ]);
});

// шорткод для блока
add_shortcode('ab_test_block', function() use ($ab_test_option_key) {
    $variant = ab_test_get_user_variant();
    ab_test_increment_views($variant, $ab_test_option_key);

    ob_start();
    ?>
    <div class="ab-test-block" data-variant="<?php echo esc_attr($variant); ?>">
        <?php if ($variant === 'a'): ?>
            <h2>Вариант A: Лучший выбор для тебя!</h2>
            <button class="ab-test-btn" data-variant="a">Купить сейчас</button>
        <?php else: ?>
            <h2>Вариант B: Специальное предложение!</h2>
            <button class="ab-test-btn" data-variant="b">Узнать больше</button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

// AJAX обработчик кликов
add_action('wp_ajax_ab_test_click', 'ab_test_handle_click');
add_action('wp_ajax_nopriv_ab_test_click', 'ab_test_handle_click');

function ab_test_handle_click() {
    global $ab_test_option_key;
    check_ajax_referer('ab_test_nonce', 'nonce');

    $variant = sanitize_text_field($_POST['variant']);
    $stats = get_option($ab_test_option_key, [
        'a' => ['views' => 0, 'clicks' => 0],
        'b' => ['views' => 0, 'clicks' => 0],
    ]);

    if (isset($stats[$variant])) {
        $stats[$variant]['clicks']++;
        update_option($ab_test_option_key, $stats);
    }

    wp_send_json_success(['message' => 'Click recorded']);
}

// админка
add_action('admin_menu', function() {
    add_menu_page(
        'A/B Test Report',
        'A/B Test',
        'manage_options',
        'ab-test-report',
        'ab_test_render_admin_report',
        'dashicons-chart-bar'
    );
});

function ab_test_render_admin_report() {
    global $ab_test_option_key;
    $stats = get_option($ab_test_option_key, [
        'a' => ['views' => 0, 'clicks' => 0],
        'b' => ['views' => 0, 'clicks' => 0],
    ]);

    $report = [];
    foreach ($stats as $variant => $data) {
        $conversion = $data['views'] > 0 ? round(($data['clicks'] / $data['views']) * 100, 2) : 0;
        $report[$variant] = [
            'views' => $data['views'],
            'clicks' => $data['clicks'],
            'conversion' => $conversion,
        ];
    }

    $winner = ($report['a']['conversion'] > $report['b']['conversion']) ? 'A' : 'B';

    echo '<div class="wrap"><h1>A/B Test Report</h1>';
    echo '<table class="widefat fixed"><thead><tr><th>Вариант</th><th>Показы</th><th>Клики</th><th>Конверсия (%)</th></tr></thead><tbody>';
    foreach ($report as $variant => $data) {
        echo '<tr>';
        echo '<td>' . esc_html(strtoupper($variant)) . '</td>';
        echo '<td>' . esc_html($data['views']) . '</td>';
        echo '<td>' . esc_html($data['clicks']) . '</td>';
        echo '<td>' . esc_html($data['conversion']) . '%</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p><strong>Победитель:</strong> ' . esc_html($winner) . '</p>';
    echo '</div>';
}

function ab_test_get_user_variant() {
    if (isset($_COOKIE['ab_test_variant'])) {
        return sanitize_text_field($_COOKIE['ab_test_variant']);
    }
    $variant = (mt_rand(0, 1) === 0) ? 'a' : 'b';
    setcookie('ab_test_variant', $variant, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN);
    $_COOKIE['ab_test_variant'] = $variant;
    return $variant;
}

function ab_test_increment_views($variant, $option_key) {
    $stats = get_option($option_key, [
        'a' => ['views' => 0, 'clicks' => 0],
        'b' => ['views' => 0, 'clicks' => 0],
    ]);
    $stats[$variant]['views']++;
    update_option($option_key, $stats);
}
