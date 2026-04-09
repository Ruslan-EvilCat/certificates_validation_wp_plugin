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
	<?php
	$tools_notice_type = ! empty( $tools_state['notice_type'] ) ? $tools_state['notice_type'] : 'info';
	?>

	<?php if ( ! empty( $tools_state['message'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $tools_notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $tools_state['message'] ); ?></p>
		</div>
	<?php endif; ?>

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

	<hr />

	<h2><?php echo esc_html__( 'Export Certificates', 'certificate-validation-plugin' ); ?></h2>
	<p>
		<?php
		echo esc_html__(
			'Download all certificates or limit the export by issued date range.',
			'certificate-validation-plugin'
		);
		?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="cvp_export_certificates" />
		<?php wp_nonce_field( 'cvp_export_certificates', 'cvp_export_certificates_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cvp-export-from-date"><?php echo esc_html__( 'From date', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							id="cvp-export-from-date"
							name="from_date"
							value="<?php echo esc_attr( $export_filters['from_date'] ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-export-to-date"><?php echo esc_html__( 'To date', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							type="date"
							id="cvp-export-to-date"
							name="to_date"
							value="<?php echo esc_attr( $export_filters['to_date'] ); ?>"
						/>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" name="cvp_export_format" value="csv" class="button button-primary">
				<?php echo esc_html__( 'Export CSV', 'certificate-validation-plugin' ); ?>
			</button>
			<button type="submit" name="cvp_export_format" value="xlsx" class="button">
				<?php echo esc_html__( 'Export XLSX', 'certificate-validation-plugin' ); ?>
			</button>
		</p>
	</form>
</div>
