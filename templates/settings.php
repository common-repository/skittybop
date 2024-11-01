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
settings_errors('skittybop_api_key');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__("Settings", "skittybop") ?></h1>
    <form method="post" action="options.php" id="skittybop-settings">
        <?php settings_fields( 'skittybop-settings' ); ?>
        <?php do_settings_sections( 'skittybop-settings' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__( 'API Key', "skittybop" ); ?></th>
                <td><input type="text" name="skittybop_api_key" value="<?php echo esc_attr(get_option('skittybop_api_key')); ?>"/></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>

</div>
