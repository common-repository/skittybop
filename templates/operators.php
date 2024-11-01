<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

$user = get_current_user_id();
$screen = get_current_screen();
$screen_option = $screen->get_option('per_page', 'option');
$per_page = get_user_meta($user, $screen_option, true);
if (empty($per_page) || $per_page < 1) {
    $per_page = $screen->get_option( 'per_page', 'default' );
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__("Operators", "skittybop") ?></h1>

    <div id="skittybopOperators">
        <table id="skittybopOperatorsTable" class="stripe hover order-column">
            <thead>
            <tr>
                <th></th>
                <th class="dt-head-left"><?php echo esc_html__("Operator", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Status", "skittybop") ?></th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th></th>
                <th class="dt-head-left"><?php echo esc_html__("Operator", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Status", "skittybop") ?></th>
            </tr>
            </tfoot>
        </table>
    </div>

    <?php wp_nonce_field('skittybop-fetch-operators', '_wpnonce_skittybop_fetch_operators') ?>
    <input type="hidden" id="per_page" name="per_page" value="<?php echo intval($per_page)?>">
</div>
