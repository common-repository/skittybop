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
    <h1 class="wp-heading-inline"><?php echo esc_html__("History", "skittybop") ?></h1>

    <div id="skittybopHistory">
        <table id="skittybopHistoryTable" class="stripe hover order-column">
            <thead>
            <tr>
                <th></th>
                <?php if (current_user_can(SkittybopRole::ADMINISTRATOR)) { ?>
                    <th class="dt-head-center"></th>
                <?php } ?>
                <th class="dt-head-center"><?php echo esc_html__("#", "skittybop") ?></th>
                <th class="dt-head-left"><?php echo esc_html__("Operator", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Status", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Room", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Started", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Duration", "skittybop") ?></th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th></th>
                <?php if (current_user_can(SkittybopRole::ADMINISTRATOR)) { ?>
                    <th class="dt-head-center"></th>
                <?php } ?>
                <th class="dt-head-center"><?php echo esc_html__("#", "skittybop") ?></th>
                <th class="dt-head-left"><?php echo esc_html__("Operator", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Status", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Room", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Started", "skittybop") ?></th>
                <th class="dt-head-center"><?php echo esc_html__("Duration", "skittybop") ?></th>
            </tr>
            </tfoot>
        </table>
    </div>

    <?php wp_nonce_field('skittybop-fetch-calls', '_wpnonce_skittybop_fetch_calls') ?>
    <?php wp_nonce_field('skittybop-delete-calls', '_wpnonce_skittybop_delete_calls') ?>
    <input type="hidden" id="per_page" name="per_page" value="<?php echo intval($per_page)?>">
</div>
