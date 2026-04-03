<?php
/**
 * Admin module placeholder.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CVP_PLUGIN_DIR . 'includes/class-cvp-certificate-repository.php';
require_once CVP_PLUGIN_DIR . 'includes/class-cvp-xlsx-importer.php';
require_once CVP_PLUGIN_DIR . 'admin/class-cvp-certificates-list-table.php';

/**
 * Admin bootstrap.
 */
class CVP_Admin {

	/**
	 * Certificate repository.
	 *
	 * @var CVP_Certificate_Repository
	 */
	protected $repository;

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->repository = new CVP_Certificate_Repository();

		add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
		add_action( 'admin_post_cvp_save_certificate', array( $this, 'handle_save_certificate' ) );
		add_action( 'admin_post_cvp_delete_certificate', array( $this, 'handle_delete_certificate' ) );
		add_action( 'admin_post_cvp_bulk_delete_certificates', array( $this, 'handle_bulk_delete_certificates' ) );
		add_action( 'admin_post_cvp_bulk_upload_certificates', array( $this, 'handle_bulk_upload_certificates' ) );
	}

	/**
	 * Registers admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu_pages() {
		$capability = 'manage_options';
		$slug       = 'cvp-certificates';

		add_menu_page(
			__( 'Certificates', 'certificate-validation-plugin' ),
			__( 'Certificates', 'certificate-validation-plugin' ),
			$capability,
			$slug,
			array( $this, 'render_certificates_page' ),
			'dashicons-awards',
			26
		);

		add_submenu_page(
			$slug,
			__( 'Certificates', 'certificate-validation-plugin' ),
			__( 'Certificates', 'certificate-validation-plugin' ),
			$capability,
			$slug,
			array( $this, 'render_certificates_page' )
		);

		add_submenu_page(
			$slug,
			__( 'Add Certificate', 'certificate-validation-plugin' ),
			__( 'Add Certificate', 'certificate-validation-plugin' ),
			$capability,
			'cvp-add-certificate',
			array( $this, 'render_add_certificate_page' )
		);

		add_submenu_page(
			$slug,
			__( 'Bulk Upload', 'certificate-validation-plugin' ),
			__( 'Bulk Upload', 'certificate-validation-plugin' ),
			$capability,
			'cvp-bulk-upload',
			array( $this, 'render_bulk_upload_page' )
		);

		add_submenu_page(
			$slug,
			__( 'Tools', 'certificate-validation-plugin' ),
			__( 'Tools', 'certificate-validation-plugin' ),
			$capability,
			'cvp-tools',
			array( $this, 'render_tools_page' )
		);
	}

	/**
	 * Renders the certificates list placeholder.
	 *
	 * @return void
	 */
	public function render_certificates_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'certificate-validation-plugin' ) );
		}

		$list_state = $this->consume_list_state();
		$return_args = $this->get_certificates_return_args( $_GET );
		$list_table = new CVP_Certificates_List_Table();
		$list_table->prepare_items();

		require CVP_PLUGIN_DIR . 'admin/views/page-certificates.php';
	}

	/**
	 * Renders the add certificate placeholder.
	 *
	 * @return void
	 */
	public function render_add_certificate_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'certificate-validation-plugin' ) );
		}

		$certificate_id = 0;
		$certificate    = $this->get_default_certificate_data();
		$form_state     = $this->consume_form_state();

		if ( isset( $_GET['certificate_id'] ) ) {
			$certificate_id = absint( $_GET['certificate_id'] );
		}

		if ( $certificate_id > 0 ) {
			$saved_certificate = $this->repository->get_certificate_by_id( $certificate_id );

			if ( ! is_array( $saved_certificate ) ) {
				wp_die( esc_html__( 'Certificate not found.', 'certificate-validation-plugin' ) );
			}

			$certificate = array_merge( $certificate, $saved_certificate );
		}

		if ( ! empty( $form_state['data'] ) && is_array( $form_state['data'] ) ) {
			$certificate = array_merge( $certificate, $form_state['data'] );
		}

		require CVP_PLUGIN_DIR . 'admin/views/page-add-certificate.php';
	}

	/**
	 * Renders the bulk upload placeholder.
	 *
	 * @return void
	 */
	public function render_bulk_upload_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'certificate-validation-plugin' ) );
		}

		$bulk_upload_state = $this->consume_bulk_upload_state();

		require CVP_PLUGIN_DIR . 'admin/views/page-bulk-upload.php';
	}

	/**
	 * Renders the tools placeholder.
	 *
	 * @return void
	 */
	public function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'certificate-validation-plugin' ) );
		}

		require CVP_PLUGIN_DIR . 'admin/views/page-tools.php';
	}

	/**
	 * Handles certificate create and update actions.
	 *
	 * @return void
	 */
	public function handle_save_certificate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'certificate-validation-plugin' ) );
		}

		check_admin_referer( 'cvp_save_certificate', 'cvp_save_certificate_nonce' );

		$certificate_id = isset( $_POST['certificate_id'] ) ? absint( wp_unslash( $_POST['certificate_id'] ) ) : 0;
		$is_update      = $certificate_id > 0;
		$form_data      = $this->sanitize_certificate_form_data( $_POST );

		if ( $is_update && ! $this->repository->get_certificate_by_id( $certificate_id ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'certificate-validation-plugin' ) );
		}

		$errors         = $this->validate_certificate_form_data( $form_data, $certificate_id );

		if ( ! empty( $errors ) ) {
			$this->set_form_state(
				array(
					'notice_type' => 'error',
					'message'     => __( 'Certificate could not be saved. Please review the errors below.', 'certificate-validation-plugin' ),
					'errors'      => $errors,
					'data'        => array_merge(
						$form_data,
						array(
							'id' => $certificate_id,
						)
					),
				)
			);

			wp_safe_redirect( $this->get_add_certificate_url( $certificate_id ) );
			exit;
		}

		if ( $certificate_id > 0 ) {
			$saved = $this->repository->update_certificate( $certificate_id, $form_data );
		} else {
			$certificate_id = $this->repository->insert_certificate( $form_data );
			$saved          = false !== $certificate_id;
		}

		if ( ! $saved ) {
			$errors = array();

			if ( $this->repository->code_exists( $form_data['code'], $certificate_id ) ) {
				$errors[] = __( 'Code must be unique.', 'certificate-validation-plugin' );
			}

			$this->set_form_state(
				array(
					'notice_type' => 'error',
					'message'     => __( 'A database error occurred while saving the certificate.', 'certificate-validation-plugin' ),
					'errors'      => $errors,
					'data'        => array_merge(
						$form_data,
						array(
							'id' => $certificate_id,
						)
					),
				)
			);

			wp_safe_redirect( $this->get_add_certificate_url( $certificate_id ) );
			exit;
		}

		$this->set_form_state(
			array(
				'notice_type' => 'success',
				'message'     => $is_update
					? __( 'Certificate updated successfully.', 'certificate-validation-plugin' )
					: __( 'Certificate saved successfully.', 'certificate-validation-plugin' ),
				'errors'      => array(),
				'data'        => array(),
			)
		);

		wp_safe_redirect( $this->get_add_certificate_url( $certificate_id ) );
		exit;
	}

	/**
	 * Handles a single certificate delete action.
	 *
	 * @return void
	 */
	public function handle_delete_certificate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'certificate-validation-plugin' ) );
		}

		$certificate_id = isset( $_GET['certificate_id'] ) ? absint( wp_unslash( $_GET['certificate_id'] ) ) : 0;

		if ( $certificate_id < 1 ) {
			wp_die( esc_html__( 'Certificate not found.', 'certificate-validation-plugin' ) );
		}

		check_admin_referer( 'cvp_delete_certificate_' . $certificate_id );

		if ( ! $this->repository->get_certificate_by_id( $certificate_id ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'certificate-validation-plugin' ) );
		}

		$deleted = $this->repository->delete_certificate( $certificate_id );

		$this->set_list_state(
			array(
				'notice_type' => $deleted ? 'success' : 'error',
				'message'     => $deleted
					? __( 'Certificate deleted successfully.', 'certificate-validation-plugin' )
					: __( 'The certificate could not be deleted.', 'certificate-validation-plugin' ),
			)
		);

		wp_safe_redirect( $this->get_certificates_page_url( $this->get_certificates_return_args( $_GET ) ) );
		exit;
	}

	/**
	 * Handles bulk certificate deletion.
	 *
	 * @return void
	 */
	public function handle_bulk_delete_certificates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'certificate-validation-plugin' ) );
		}

		check_admin_referer( 'cvp_bulk_delete_certificates', 'cvp_bulk_delete_certificates_nonce' );

		$action = '';

		if ( isset( $_POST['action'] ) && '-1' !== $_POST['action'] ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		} elseif ( isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			$action = sanitize_text_field( wp_unslash( $_POST['action2'] ) );
		}

		$return_args = $this->get_certificates_return_args( $_POST );

		if ( 'delete' !== $action ) {
			$this->set_list_state(
				array(
					'notice_type' => 'error',
					'message'     => __( 'No bulk action was selected.', 'certificate-validation-plugin' ),
				)
			);

			wp_safe_redirect( $this->get_certificates_page_url( $return_args ) );
			exit;
		}

		$certificate_ids = array();

		if ( isset( $_POST['certificate_ids'] ) && is_array( $_POST['certificate_ids'] ) ) {
			$certificate_ids = array_filter( array_map( 'absint', wp_unslash( $_POST['certificate_ids'] ) ) );
		}

		if ( empty( $certificate_ids ) ) {
			$this->set_list_state(
				array(
					'notice_type' => 'error',
					'message'     => __( 'Please select at least one certificate to delete.', 'certificate-validation-plugin' ),
				)
			);

			wp_safe_redirect( $this->get_certificates_page_url( $return_args ) );
			exit;
		}

		$deleted_count = 0;

		foreach ( $certificate_ids as $certificate_id ) {
			if ( $this->repository->delete_certificate( $certificate_id ) ) {
				++$deleted_count;
			}
		}

		$this->set_list_state(
			array(
				'notice_type' => $deleted_count > 0 ? 'success' : 'error',
				'message'     => $deleted_count > 0
					? sprintf(
						/* translators: %d: number of deleted certificates. */
						_n( '%d certificate deleted successfully.', '%d certificates deleted successfully.', $deleted_count, 'certificate-validation-plugin' ),
						$deleted_count
					)
					: __( 'No certificates were deleted.', 'certificate-validation-plugin' ),
			)
		);

		wp_safe_redirect( $this->get_certificates_page_url( $return_args ) );
		exit;
	}

	/**
	 * Handles bulk certificate import from XLSX.
	 *
	 * @return void
	 */
	public function handle_bulk_upload_certificates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'certificate-validation-plugin' ) );
		}

		check_admin_referer( 'cvp_bulk_upload_certificates', 'cvp_bulk_upload_certificates_nonce' );

		$upload = isset( $_FILES['cvp_bulk_file'] ) ? $_FILES['cvp_bulk_file'] : null;

		if ( ! is_array( $upload ) || empty( $upload['tmp_name'] ) || ! isset( $upload['error'] ) ) {
			$this->set_bulk_upload_state(
				array(
					'notice_type' => 'error',
					'report'      => array(
						'message' => __( 'Please select an .xlsx file to upload.', 'certificate-validation-plugin' ),
						'total'   => 0,
						'imported'=> 0,
						'skipped' => 0,
						'details' => array(),
					),
				)
			);

			wp_safe_redirect( $this->get_bulk_upload_page_url() );
			exit;
		}

		if ( UPLOAD_ERR_OK !== (int) $upload['error'] ) {
			$this->set_bulk_upload_state(
				array(
					'notice_type' => 'error',
					'report'      => array(
						'message' => __( 'The file upload failed.', 'certificate-validation-plugin' ),
						'total'   => 0,
						'imported'=> 0,
						'skipped' => 0,
						'details' => array(),
					),
				)
			);

			wp_safe_redirect( $this->get_bulk_upload_page_url() );
			exit;
		}

		if ( ! is_uploaded_file( $upload['tmp_name'] ) ) {
			$this->set_bulk_upload_state(
				array(
					'notice_type' => 'error',
					'report'      => array(
						'message' => __( 'The uploaded file is invalid.', 'certificate-validation-plugin' ),
						'total'   => 0,
						'imported'=> 0,
						'skipped' => 0,
						'details' => array(),
					),
				)
			);

			wp_safe_redirect( $this->get_bulk_upload_page_url() );
			exit;
		}

		$file_name = isset( $upload['name'] ) ? sanitize_file_name( wp_unslash( $upload['name'] ) ) : '';
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$file_type = wp_check_filetype_and_ext( $upload['tmp_name'], $file_name );

		if (
			'xlsx' !== $file_ext ||
			empty( $file_type['ext'] ) ||
			'xlsx' !== strtolower( (string) $file_type['ext'] )
		) {
			$this->set_bulk_upload_state(
				array(
					'notice_type' => 'error',
					'report'      => array_merge(
						$this->get_default_import_report(),
						array(
							'message' => __( 'Invalid file format. Please use the provided template.', 'certificate-validation-plugin' ),
						)
					),
				)
			);

			wp_safe_redirect( $this->get_bulk_upload_page_url() );
			exit;
		}

		$importer = new CVP_XLSX_Importer( $this->repository );
		$report   = $importer->import( $upload['tmp_name'] );

		$this->set_bulk_upload_state(
			array(
				'notice_type' => $report['success'] ? 'success' : 'error',
				'report'      => $report,
			)
		);

		wp_safe_redirect( $this->get_bulk_upload_page_url() );
		exit;
	}

	/**
	 * Returns the default certificate form data.
	 *
	 * @return array
	 */
	protected function get_default_certificate_data() {
		return array(
			'id'          => 0,
			'code'        => '',
			'name'        => '',
			'surname'     => '',
			'course'      => '',
			'hours'       => '',
			'ects_hours'  => '',
			'issued_date' => '',
			'course_link' => '',
		);
	}

	/**
	 * Sanitizes certificate form input.
	 *
	 * @param array $source Raw request data.
	 * @return array
	 */
	protected function sanitize_certificate_form_data( $source ) {
		$code = '';

		if ( isset( $source['code'] ) ) {
			$code = strtoupper( trim( sanitize_text_field( wp_unslash( $source['code'] ) ) ) );
		}

		return array(
			'code'        => $code,
			'name'        => isset( $source['name'] ) ? sanitize_text_field( wp_unslash( $source['name'] ) ) : '',
			'surname'     => isset( $source['surname'] ) ? sanitize_text_field( wp_unslash( $source['surname'] ) ) : '',
			'course'      => isset( $source['course'] ) ? sanitize_text_field( wp_unslash( $source['course'] ) ) : '',
			'hours'       => isset( $source['hours'] ) ? trim( sanitize_text_field( wp_unslash( $source['hours'] ) ) ) : '',
			'ects_hours'  => isset( $source['ects_hours'] ) ? trim( sanitize_text_field( wp_unslash( $source['ects_hours'] ) ) ) : '',
			'issued_date' => isset( $source['issued_date'] ) ? sanitize_text_field( wp_unslash( $source['issued_date'] ) ) : '',
			'course_link' => isset( $source['course_link'] ) ? esc_url_raw( trim( wp_unslash( $source['course_link'] ) ) ) : '',
		);
	}

	/**
	 * Validates sanitized certificate form data.
	 *
	 * @param array $data Certificate data.
	 * @param int   $certificate_id Certificate ID.
	 * @return array
	 */
	protected function validate_certificate_form_data( &$data, $certificate_id = 0 ) {
		$errors         = array();
		$certificate_id = absint( $certificate_id );

		if ( '' === $data['code'] ) {
			$errors[] = __( 'Code is required.', 'certificate-validation-plugin' );
		} elseif ( $this->repository->code_exists( $data['code'], $certificate_id ) ) {
			$errors[] = __( 'Code must be unique.', 'certificate-validation-plugin' );
		}

		if ( '' === $data['hours'] ) {
			$data['hours'] = 0;
		}

		if ( '' === $data['ects_hours'] ) {
			$data['ects_hours'] = 0;
		}

		if ( ! is_numeric( $data['hours'] ) ) {
			$errors[] = __( 'Hours must be numeric.', 'certificate-validation-plugin' );
		} else {
			$data['hours'] = (int) $data['hours'];
		}

		if ( ! is_numeric( $data['ects_hours'] ) ) {
			$errors[] = __( 'ECTS Hours must be numeric.', 'certificate-validation-plugin' );
		} else {
			$data['ects_hours'] = (int) $data['ects_hours'];
		}

		if ( '' === $data['issued_date'] || ! $this->is_valid_date( $data['issued_date'] ) ) {
			$errors[] = __( 'Issued Date must be a valid date in YYYY-MM-DD format.', 'certificate-validation-plugin' );
		}

		return $errors;
	}

	/**
	 * Validates a date string against the plugin storage format.
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	protected function is_valid_date( $date ) {
		$date_time = DateTime::createFromFormat( 'Y-m-d', $date );

		if ( ! $date_time instanceof DateTime ) {
			return false;
		}

		return $date_time->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Returns the add/edit page URL.
	 *
	 * @param int $certificate_id Certificate ID.
	 * @return string
	 */
	protected function get_add_certificate_url( $certificate_id = 0 ) {
		$args = array(
			'page' => 'cvp-add-certificate',
		);

		$certificate_id = absint( $certificate_id );

		if ( $certificate_id > 0 ) {
			$args['certificate_id'] = $certificate_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Returns the certificates list page URL with optional query args.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	protected function get_certificates_page_url( $args = array() ) {
		$args = wp_parse_args(
			(array) $args,
			array(
				'page' => 'cvp-certificates',
			)
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Returns the bulk upload page URL.
	 *
	 * @return string
	 */
	protected function get_bulk_upload_page_url() {
		return add_query_arg(
			array(
				'page' => 'cvp-bulk-upload',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Stores form state across redirects.
	 *
	 * @param array $state Form state.
	 * @return void
	 */
	protected function set_form_state( $state ) {
		set_transient( $this->get_form_state_key(), $this->sanitize_notice_state( $state ), MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Returns and clears stored form state.
	 *
	 * @return array
	 */
	protected function consume_form_state() {
		$form_state = get_transient( $this->get_form_state_key() );

		delete_transient( $this->get_form_state_key() );

		return is_array( $form_state ) ? $form_state : array();
	}

	/**
	 * Stores certificates list state across redirects.
	 *
	 * @param array $state List page state.
	 * @return void
	 */
	protected function set_list_state( $state ) {
		set_transient( $this->get_list_state_key(), $this->sanitize_notice_state( $state ), MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Returns and clears stored list state.
	 *
	 * @return array
	 */
	protected function consume_list_state() {
		$list_state = get_transient( $this->get_list_state_key() );

		delete_transient( $this->get_list_state_key() );

		return is_array( $list_state ) ? $list_state : array();
	}

	/**
	 * Stores bulk upload page state across redirects.
	 *
	 * @param array $state Bulk upload state.
	 * @return void
	 */
	protected function set_bulk_upload_state( $state ) {
		$report = $this->get_default_import_report();

		if ( isset( $state['report'] ) && is_array( $state['report'] ) ) {
			$report = array_merge( $report, $state['report'] );
			$report['message'] = sanitize_text_field( $report['message'] );
			$report['total']   = absint( $report['total'] );
			$report['imported'] = absint( $report['imported'] );
			$report['skipped'] = absint( $report['skipped'] );
			$report['details'] = array_map( 'sanitize_text_field', is_array( $report['details'] ) ? $report['details'] : array() );
		}

		set_transient(
			$this->get_bulk_upload_state_key(),
			array(
				'notice_type' => $this->sanitize_notice_type( $state['notice_type'] ?? '' ),
				'report'      => $report,
			),
			MINUTE_IN_SECONDS * 10
		);
	}

	/**
	 * Returns and clears stored bulk upload state.
	 *
	 * @return array
	 */
	protected function consume_bulk_upload_state() {
		$bulk_upload_state = get_transient( $this->get_bulk_upload_state_key() );

		delete_transient( $this->get_bulk_upload_state_key() );

		return is_array( $bulk_upload_state ) ? $bulk_upload_state : array();
	}

	/**
	 * Returns the current user's form state key.
	 *
	 * @return string
	 */
	protected function get_form_state_key() {
		return 'cvp_certificate_form_state_' . get_current_user_id();
	}

	/**
	 * Returns the current user's list state key.
	 *
	 * @return string
	 */
	protected function get_list_state_key() {
		return 'cvp_certificate_list_state_' . get_current_user_id();
	}

	/**
	 * Returns sanitized certificates page return args.
	 *
	 * @param array $source Request source.
	 * @return array
	 */
	protected function get_certificates_return_args( $source ) {
		$args = array();

		if ( isset( $source['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $source['s'] ) );
		}

		if ( isset( $source['paged'] ) ) {
			$paged = absint( wp_unslash( $source['paged'] ) );

			if ( $paged > 0 ) {
				$args['paged'] = $paged;
			}
		}

		return $args;
	}

	/**
	 * Sanitizes a notice state payload.
	 *
	 * @param array $state Notice state.
	 * @return array
	 */
	protected function sanitize_notice_state( $state ) {
		return array(
			'notice_type' => $this->sanitize_notice_type( $state['notice_type'] ?? '' ),
			'message'     => isset( $state['message'] ) ? sanitize_text_field( $state['message'] ) : '',
			'errors'      => array_map( 'sanitize_text_field', isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array() ),
			'data'        => isset( $state['data'] ) && is_array( $state['data'] ) ? $state['data'] : array(),
		);
	}

	/**
	 * Sanitizes a notice type.
	 *
	 * @param string $notice_type Notice type.
	 * @return string
	 */
	protected function sanitize_notice_type( $notice_type ) {
		$notice_type = sanitize_key( (string) $notice_type );

		return in_array( $notice_type, array( 'success', 'error', 'warning', 'info' ), true ) ? $notice_type : 'info';
	}

	/**
	 * Returns the default import report structure.
	 *
	 * @return array
	 */
	protected function get_default_import_report() {
		return array(
			'success'  => false,
			'message'  => '',
			'total'    => 0,
			'imported' => 0,
			'skipped'  => 0,
			'details'  => array(),
		);
	}

	/**
	 * Returns the current user's bulk upload state key.
	 *
	 * @return string
	 */
	protected function get_bulk_upload_state_key() {
		return 'cvp_certificate_bulk_upload_state_' . get_current_user_id();
	}
}
