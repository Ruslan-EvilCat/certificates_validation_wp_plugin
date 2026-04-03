<?php
/**
 * Bulk upload admin page placeholder.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Bulk Upload', 'certificate-validation-plugin' ); ?></h1>

	<?php
	$bulk_notice_type = ! empty( $bulk_upload_state['notice_type'] ) ? $bulk_upload_state['notice_type'] : 'info';
	?>

	<p>
		<?php
		echo esc_html__(
			'Upload an .xlsx file with these exact headers: code | name | surname | course | hours | ects_hours | issued_date | link',
			'certificate-validation-plugin'
		);
		?>
	</p>

	<?php if ( ! empty( $bulk_upload_state['report']['message'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $bulk_notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $bulk_upload_state['report']['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=cvp_bulk_upload_certificates' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'cvp_bulk_upload_certificates', 'cvp_bulk_upload_certificates_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cvp-bulk-file"><?php echo esc_html__( 'XLSX File', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input type="file" id="cvp-bulk-file" name="cvp_bulk_file" accept=".xlsx" required />
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Upload and Import', 'certificate-validation-plugin' ) ); ?>
	</form>

	<?php if ( ! empty( $bulk_upload_state['report'] ) ) : ?>
		<h2><?php echo esc_html__( 'Import Report', 'certificate-validation-plugin' ); ?></h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: total rows, 2: imported rows, 3: skipped rows. */
					__( 'Total rows processed: %1$d | Imported: %2$d | Skipped: %3$d', 'certificate-validation-plugin' ),
					absint( $bulk_upload_state['report']['total'] ),
					absint( $bulk_upload_state['report']['imported'] ),
					absint( $bulk_upload_state['report']['skipped'] )
				)
			);
			?>
		</p>

		<?php if ( ! empty( $bulk_upload_state['report']['details'] ) && is_array( $bulk_upload_state['report']['details'] ) ) : ?>
			<h3><?php echo esc_html__( 'Skipped Details', 'certificate-validation-plugin' ); ?></h3>
			<ul>
				<?php foreach ( $bulk_upload_state['report']['details'] as $detail ) : ?>
					<li><?php echo esc_html( $detail ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</div>
