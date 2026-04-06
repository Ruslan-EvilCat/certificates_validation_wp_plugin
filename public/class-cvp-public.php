<?php
/**
 * Public-facing shortcode placeholder.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CVP_PLUGIN_DIR . 'includes/class-cvp-certificate-repository.php';

/**
 * Public bootstrap.
 */
class CVP_Public {

	/**
	 * Certificate repository.
	 *
	 * @var CVP_Certificate_Repository
	 */
	protected $repository;

	/**
	 * Registers public hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->repository = new CVP_Certificate_Repository();

		add_shortcode( 'certificate_validation', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_cvp_validate_certificate', array( $this, 'handle_validate_certificate' ) );
		add_action( 'wp_ajax_nopriv_cvp_validate_certificate', array( $this, 'handle_validate_certificate' ) );
	}

	/**
	 * Renders the shortcode output.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		$this->enqueue_assets();
		$input_id     = 'cvp-certificate-code-' . wp_rand( 1000, 999999 );
		$view_strings = $this->get_shortcode_view_strings();

		ob_start();
		require CVP_PLUGIN_DIR . 'public/views/shortcode-certificate-validation.php';
		return ob_get_clean();
	}

	/**
	 * Enqueues frontend assets for the shortcode.
	 *
	 * @return void
	 */
	protected function enqueue_assets() {
		wp_enqueue_style(
			'cvp-public',
			CVP_PLUGIN_URL . 'assets/css/public.css',
			array(),
			CVP_VERSION
		);

		wp_enqueue_script(
			'cvp-public',
			CVP_PLUGIN_URL . 'assets/js/public.js',
			array(),
			CVP_VERSION,
			true
		);

		wp_localize_script(
			'cvp-public',
			'cvpPublicConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cvp_validate_certificate' ),
				'strings' => array(
					'empty'    => __( 'Please enter certificate number', 'certificate-validation-plugin' ),
					'loading'  => __( 'Searching...', 'certificate-validation-plugin' ),
					'notFound' => __( 'Certificate not found', 'certificate-validation-plugin' ),
					'error'    => __( 'An unexpected error occurred. Please try again.', 'certificate-validation-plugin' ),
				) + $this->get_frontend_label_strings(),
			)
		);
	}

	/**
	 * Returns the shortcode form strings for the configured language.
	 *
	 * @return array
	 */
	protected function get_shortcode_view_strings() {
		$language = $this->get_frontend_display_language();

		if ( 'uk' === $language ) {
			return array(
				'field_label' => 'Номер сертифікату',
				'placeholder' => 'Введіть номер сертифікату',
				'button'      => 'Пошук',
			);
		}

		return array(
			'field_label' => __( 'Certificate code', 'certificate-validation-plugin' ),
			'placeholder' => __( 'Enter certificate number', 'certificate-validation-plugin' ),
			'button'      => __( 'Search', 'certificate-validation-plugin' ),
		);
	}

	/**
	 * Returns the frontend result-card labels for the configured language.
	 *
	 * @return array
	 */
	protected function get_frontend_label_strings() {
		$language = $this->get_frontend_display_language();

		if ( 'uk' === $language ) {
			return array(
				'code'     => 'Номер сертифікату',
				'fullName' => "Ім'я",
				'course'   => 'Курс',
				'hours'    => 'Кількість годин',
				'ects'     => 'ЄКТС кредити',
				'date'     => 'Дата видачі',
				'link'     => 'Додаткова інформація',
			);
		}

		return array(
			'code'     => __( 'Certificate number (code)', 'certificate-validation-plugin' ),
			'fullName' => __( 'Full Name', 'certificate-validation-plugin' ),
			'course'   => __( 'Course', 'certificate-validation-plugin' ),
			'hours'    => __( 'Hours', 'certificate-validation-plugin' ),
			'ects'     => __( 'ECTS Hours', 'certificate-validation-plugin' ),
			'date'     => __( 'Issued date', 'certificate-validation-plugin' ),
			'link'     => __( 'Link to the course info (optional)', 'certificate-validation-plugin' ),
		);
	}

	/**
	 * Returns the configured frontend display language.
	 *
	 * @return string
	 */
	protected function get_frontend_display_language() {
		$language = sanitize_key( (string) get_option( CVP_OPTION_FRONTEND_DISPLAY_LANGUAGE, 'en' ) );

		return in_array( $language, array( 'en', 'uk' ), true ) ? $language : 'en';
	}

	/**
	 * Handles the frontend certificate validation request.
	 *
	 * @return void
	 */
	public function handle_validate_certificate() {
		if ( 'POST' !== strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request method.', 'certificate-validation-plugin' ),
				),
				405
			);
		}

		check_ajax_referer( 'cvp_validate_certificate', 'nonce' );

		$code = '';

		if ( isset( $_POST['code'] ) ) {
			$code = strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) );
		}

		if ( '' === $code ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter certificate number', 'certificate-validation-plugin' ),
				),
				400
			);
		}

		$certificate = $this->repository->get_certificate_by_code( $code );

		if ( ! is_array( $certificate ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Certificate not found', 'certificate-validation-plugin' ),
				),
				404
			);
		}

		wp_send_json_success(
			array(
				'certificate' => $this->prepare_certificate_response( $certificate ),
			)
		);
	}

	/**
	 * Prepares certificate data for the public response.
	 *
	 * @param array $certificate Certificate data.
	 * @return array
	 */
	protected function prepare_certificate_response( $certificate ) {
		$issued_date = '';

		if ( ! empty( $certificate['issued_date'] ) ) {
			$timestamp = strtotime( $certificate['issued_date'] );

			if ( false !== $timestamp ) {
				$issued_date = wp_date( get_option( 'date_format' ), $timestamp );
			}
		}

		return array(
			'code'        => sanitize_text_field( $certificate['code'] ),
			'full_name'   => sanitize_text_field( $certificate['full_name'] ),
			'course'      => sanitize_text_field( $certificate['course'] ),
			'hours'       => absint( $certificate['hours'] ),
			'ects_hours'  => absint( $certificate['ects_hours'] ),
			'issued_date' => $issued_date,
			'course_link' => ! empty( $certificate['course_link'] ) ? esc_url_raw( $certificate['course_link'] ) : '',
		);
	}
}
