<?php
/**
 * Shortcode placeholder template.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cvp-certificate-validation">
	<form class="cvp-validation-form" novalidate>
		<label class="cvp-screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
			<?php echo esc_html( $view_strings['field_label'] ); ?>
		</label>
		<div class="cvp-form-row">
			<input
				type="text"
				id="<?php echo esc_attr( $input_id ); ?>"
				class="cvp-input"
				name="code"
				placeholder="<?php echo esc_attr( $view_strings['placeholder'] ); ?>"
			/>
			<button type="submit" class="cvp-button">
				<?php echo esc_html( $view_strings['button'] ); ?>
			</button>
		</div>
	</form>

	<div class="cvp-status" aria-live="polite"></div>
	<div class="cvp-result" hidden></div>
</div>
