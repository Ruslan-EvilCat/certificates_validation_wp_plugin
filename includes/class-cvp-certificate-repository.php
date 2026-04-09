<?php
/**
 * Certificate repository.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles certificate database reads for admin listings.
 */
class CVP_Certificate_Repository {

	/**
	 * Normalizes a certificate code.
	 *
	 * @param string $code Raw certificate code.
	 * @return string
	 */
	protected function normalize_code( $code ) {
		return strtoupper( trim( sanitize_text_field( $code ) ) );
	}

	/**
	 * Returns a certificate by ID.
	 *
	 * @param int $certificate_id Certificate ID.
	 * @return array|null
	 */
	public function get_certificate_by_id( $certificate_id ) {
		global $wpdb;

		$certificate_id = absint( $certificate_id );

		if ( $certificate_id < 1 ) {
			return null;
		}

		$table_name = CVP_Database::get_table_name();
		$sql        = "SELECT id, code, full_name, course, hours, ects_hours, issued_date, course_link, created_at, updated_at
			FROM {$table_name}
			WHERE id = %d";

		$certificate = $wpdb->get_row(
			$wpdb->prepare( $sql, $certificate_id ),
			ARRAY_A
		);

		return is_array( $certificate ) ? $certificate : null;
	}

	/**
	 * Returns a certificate by code.
	 *
	 * @param string $code Certificate code.
	 * @return array|null
	 */
	public function get_certificate_by_code( $code ) {
		global $wpdb;

		$code = $this->normalize_code( $code );

		if ( '' === $code ) {
			return null;
		}

		$table_name = CVP_Database::get_table_name();
		$sql        = "SELECT id, code, full_name, course, hours, ects_hours, issued_date, course_link, created_at, updated_at
			FROM {$table_name}
			WHERE code = %s";

		$certificate = $wpdb->get_row(
			$wpdb->prepare( $sql, $code ),
			ARRAY_A
		);

		return is_array( $certificate ) ? $certificate : null;
	}

	/**
	 * Determines whether a code already exists.
	 *
	 * @param string $code Certificate code.
	 * @param int    $exclude_id Certificate ID to exclude.
	 * @return bool
	 */
	public function code_exists( $code, $exclude_id = 0 ) {
		global $wpdb;

		$code       = $this->normalize_code( $code );
		$exclude_id = absint( $exclude_id );

		if ( '' === $code ) {
			return false;
		}

		$table_name = CVP_Database::get_table_name();
		$sql        = "SELECT COUNT(id) FROM {$table_name} WHERE code = %s";
		$params     = array( $code );

		if ( $exclude_id > 0 ) {
			$sql      .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) > 0;
	}

	/**
	 * Returns paginated certificates for the admin table.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_certificates( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'per_page' => 20,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$search   = sanitize_text_field( $args['search'] );
		$per_page = max( 1, absint( $args['per_page'] ) );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table_name = CVP_Database::get_table_name();
		$sql        = "SELECT id, code, full_name, course, issued_date, created_at, updated_at
			FROM {$table_name}";
		$params     = array();

		if ( '' !== $search ) {
			$sql      .= ' WHERE code LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$sql      .= ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Returns the total number of certificates for pagination.
	 *
	 * @param string $search Search term.
	 * @return int
	 */
	public function count_certificates( $search = '' ) {
		global $wpdb;

		$search     = sanitize_text_field( $search );
		$table_name = CVP_Database::get_table_name();
		$sql        = "SELECT COUNT(id) FROM {$table_name}";
		$params     = array();

		if ( '' !== $search ) {
			$sql      .= ' WHERE code LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( ! empty( $params ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Returns certificate rows for export.
	 *
	 * @param string $from_date Optional start date.
	 * @param string $to_date Optional end date.
	 * @return array
	 */
	public function get_certificates_for_export( $from_date = '', $to_date = '' ) {
		global $wpdb;

		$from_date  = sanitize_text_field( $from_date );
		$to_date    = sanitize_text_field( $to_date );
		$table_name = CVP_Database::get_table_name();
		$sql        = "SELECT code, full_name, course, hours, ects_hours, issued_date, course_link AS link
			FROM {$table_name}";
		$where      = array();
		$params     = array();

		if ( '' !== $from_date ) {
			$where[]  = 'issued_date >= %s';
			$params[] = $from_date;
		}

		if ( '' !== $to_date ) {
			$where[]  = 'issued_date <= %s';
			$params[] = $to_date;
		}

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY issued_date DESC, id DESC';

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Inserts a certificate.
	 *
	 * @param array $data Sanitized certificate data.
	 * @return int|false
	 */
	public function insert_certificate( $data ) {
		global $wpdb;

		$table_name = CVP_Database::get_table_name();
		$timestamp  = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'code'       => $data['code'],
				'full_name'  => $data['full_name'],
				'course'     => $data['course'],
				'hours'      => $data['hours'],
				'ects_hours' => $data['ects_hours'],
				'issued_date' => $data['issued_date'],
				'course_link' => $data['course_link'],
				'created_at' => $timestamp,
				'updated_at' => $timestamp,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a certificate.
	 *
	 * @param int   $certificate_id Certificate ID.
	 * @param array $data Sanitized certificate data.
	 * @return bool
	 */
	public function update_certificate( $certificate_id, $data ) {
		global $wpdb;

		$certificate_id = absint( $certificate_id );

		if ( $certificate_id < 1 ) {
			return false;
		}

		$table_name = CVP_Database::get_table_name();
		$updated    = $wpdb->update(
			$table_name,
			array(
				'code'       => $data['code'],
				'full_name'  => $data['full_name'],
				'course'     => $data['course'],
				'hours'      => $data['hours'],
				'ects_hours' => $data['ects_hours'],
				'issued_date' => $data['issued_date'],
				'course_link' => $data['course_link'],
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id' => $certificate_id,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			),
			array(
				'%d',
			)
		);

		return false !== $updated;
	}

	/**
	 * Deletes a certificate by ID.
	 *
	 * @param int $certificate_id Certificate ID.
	 * @return bool
	 */
	public function delete_certificate( $certificate_id ) {
		global $wpdb;

		$certificate_id = absint( $certificate_id );

		if ( $certificate_id < 1 ) {
			return false;
		}

		$table_name = CVP_Database::get_table_name();
		$deleted    = $wpdb->delete(
			$table_name,
			array(
				'id' => $certificate_id,
			),
			array(
				'%d',
			)
		);

		return $deleted > 0;
	}

	/**
	 * Returns existing codes from the database.
	 *
	 * @param array $codes Certificate codes.
	 * @return array
	 */
	public function get_existing_codes( $codes ) {
		global $wpdb;

		$codes = array_filter(
			array_unique(
				array_map(
					array( $this, 'normalize_code' ),
					(array) $codes
				)
			)
		);

		if ( empty( $codes ) ) {
			return array();
		}

		$table_name   = CVP_Database::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		$sql          = "SELECT code FROM {$table_name} WHERE code IN ({$placeholders})";
		$results      = $wpdb->get_col( $wpdb->prepare( $sql, $codes ) );
		$existing     = array();

		foreach ( (array) $results as $code ) {
			$existing[ strtoupper( (string) $code ) ] = true;
		}

		return $existing;
	}
}
