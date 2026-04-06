<?php
/**
 * Add certificate admin page placeholder.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$certificate_id = isset( $certificate['id'] ) ? absint( $certificate['id'] ) : 0;
$form_notice_type = ! empty( $form_state['notice_type'] ) ? $form_state['notice_type'] : 'info';
?>
<div class="wrap">
	<h1>
		<?php
		echo esc_html(
			$certificate_id > 0
				? __( 'Edit Certificate', 'certificate-validation-plugin' )
				: __( 'Add Certificate', 'certificate-validation-plugin' )
		);
		?>
	</h1>

	<?php if ( ! empty( $form_state['message'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $form_notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $form_state['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $form_state['errors'] ) && is_array( $form_state['errors'] ) ) : ?>
		<div class="notice notice-error">
			<ul>
				<?php foreach ( $form_state['errors'] as $error_message ) : ?>
					<li><?php echo esc_html( $error_message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="cvp_save_certificate" />
		<input type="hidden" name="certificate_id" value="<?php echo esc_attr( $certificate_id ); ?>" />
		<?php wp_nonce_field( 'cvp_save_certificate', 'cvp_save_certificate_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="cvp-code"><?php echo esc_html__( 'Code', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="code"
							type="text"
							id="cvp-code"
							class="regular-text"
							value="<?php echo esc_attr( $certificate['code'] ); ?>"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-full-name"><?php echo esc_html__( 'Full name', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="full_name"
							type="text"
							id="cvp-full-name"
							class="regular-text"
							value="<?php echo esc_attr( $certificate['full_name'] ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-course"><?php echo esc_html__( 'Course', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="course"
							type="text"
							id="cvp-course"
							class="regular-text"
							value="<?php echo esc_attr( $certificate['course'] ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-hours"><?php echo esc_html__( 'Hours', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="hours"
							type="number"
							id="cvp-hours"
							class="small-text"
							step="1"
							value="<?php echo esc_attr( (string) $certificate['hours'] ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-ects-hours"><?php echo esc_html__( 'ECTS Hours', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="ects_hours"
							type="number"
							id="cvp-ects-hours"
							class="small-text"
							step="1"
							value="<?php echo esc_attr( (string) $certificate['ects_hours'] ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-issued-date"><?php echo esc_html__( 'Issued Date', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="issued_date"
							type="date"
							id="cvp-issued-date"
							class="regular-text"
							value="<?php echo esc_attr( $certificate['issued_date'] ); ?>"
							required
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cvp-course-link"><?php echo esc_html__( 'Course Link', 'certificate-validation-plugin' ); ?></label>
					</th>
					<td>
						<input
							name="course_link"
							type="url"
							id="cvp-course-link"
							class="regular-text"
							value="<?php echo esc_attr( $certificate['course_link'] ); ?>"
						/>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( $certificate_id > 0 ? __( 'Update Certificate', 'certificate-validation-plugin' ) : __( 'Save Certificate', 'certificate-validation-plugin' ) ); ?>
	</form>
</div>
