<?php
/**
 * Tools admin page placeholder.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Tools', 'certificate-validation-plugin' ); ?></h1>
	<p><?php echo esc_html__( 'Use shortcode:', 'certificate-validation-plugin' ); ?> <code>[certificate_validation]</code></p>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'cvp_tools_settings' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cvp-frontend-display-language"><?php echo esc_html__( 'Frontend certificate display language', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<select id="cvp-frontend-display-language" name="<?php echo esc_attr( CVP_OPTION_FRONTEND_DISPLAY_LANGUAGE ); ?>">
							<option value="en" <?php selected( $frontend_language, 'en' ); ?>><?php echo esc_html__( 'English', 'certificate-validation-plugin' ); ?></option>
							<option value="uk" <?php selected( $frontend_language, 'uk' ); ?>><?php echo esc_html__( 'Ukrainian', 'certificate-validation-plugin' ); ?></option>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Changes', 'certificate-validation-plugin' ) ); ?>
	</form>
</div>
