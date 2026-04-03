<?php
/**
 * Certificates admin page placeholder.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Certificates', 'certificate-validation-plugin' ); ?></h1>

	<?php
	$list_notice_type = ! empty( $list_state['notice_type'] ) ? $list_state['notice_type'] : 'info';
	$search_value     = ! empty( $return_args['s'] ) ? $return_args['s'] : '';
	$paged_value      = ! empty( $return_args['paged'] ) ? absint( $return_args['paged'] ) : 0;
	?>

	<?php if ( ! empty( $list_state['message'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $list_notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $list_state['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="cvp-certificates" />
		<?php $list_table->search_box( esc_html__( 'Search by code', 'certificate-validation-plugin' ), 'cvp-certificate-search' ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php?action=cvp_bulk_delete_certificates' ) ); ?>" id="cvp-certificates-bulk-form">
		<?php wp_nonce_field( 'cvp_bulk_delete_certificates', 'cvp_bulk_delete_certificates_nonce' ); ?>
		<?php if ( '' !== $search_value ) : ?>
			<input type="hidden" name="s" value="<?php echo esc_attr( $search_value ); ?>" />
		<?php endif; ?>
		<?php if ( $paged_value > 0 ) : ?>
			<input type="hidden" name="paged" value="<?php echo esc_attr( $paged_value ); ?>" />
		<?php endif; ?>
		<?php $list_table->display(); ?>
	</form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
	var bulkForm = document.getElementById('cvp-certificates-bulk-form');

	if (!bulkForm) {
		return;
	}

	bulkForm.addEventListener('submit', function (event) {
		var actionTop = bulkForm.querySelector('select[name="action"]');
		var actionBottom = bulkForm.querySelector('select[name="action2"]');
		var selectedAction = '';

		if (actionTop && actionTop.value && '-1' !== actionTop.value) {
			selectedAction = actionTop.value;
		} else if (actionBottom && actionBottom.value && '-1' !== actionBottom.value) {
			selectedAction = actionBottom.value;
		}

		if ('delete' === selectedAction && !window.confirm('<?php echo esc_js( __( 'Are you sure?', 'certificate-validation-plugin' ) ); ?>')) {
			event.preventDefault();
		}
	});
});
</script>
