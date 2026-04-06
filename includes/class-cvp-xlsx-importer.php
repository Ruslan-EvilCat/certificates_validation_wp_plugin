<?php
/**
 * XLSX certificate importer.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles XLSX parsing and certificate import.
 */
class CVP_XLSX_Importer {

	/**
	 * Expected header row.
	 *
	 * @var array
	 */
	const EXPECTED_HEADERS = array(
		'code',
		'full_name',
		'course',
		'hours',
		'ects_hours',
		'issued_date',
		'link',
	);

	/**
	 * Certificate repository.
	 *
	 * @var CVP_Certificate_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param CVP_Certificate_Repository $repository Certificate repository.
	 */
	public function __construct( $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Imports certificates from an XLSX file.
	 *
	 * @param string $file_path Uploaded file path.
	 * @return array
	 */
	public function import( $file_path ) {
		$report = array(
			'success' => false,
			'message' => '',
			'total'   => 0,
			'imported'=> 0,
			'skipped' => 0,
			'details' => array(),
		);

		$file_path = (string) $file_path;

		if ( '' === $file_path || ! is_readable( $file_path ) ) {
			$report['message'] = __( 'The uploaded file could not be read.', 'certificate-validation-plugin' );
			return $report;
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$report['message'] = __( 'The server does not support .xlsx imports because ZipArchive is unavailable.', 'certificate-validation-plugin' );
			return $report;
		}

		$zip = new ZipArchive();
		$opened = $zip->open( $file_path );

		if ( true !== $opened ) {
			$report['message'] = __( 'The uploaded file could not be opened.', 'certificate-validation-plugin' );
			return $report;
		}

		$worksheet_path = $this->get_first_worksheet_path( $zip );
		$shared_strings = $this->get_shared_strings( $zip );
		$worksheet_xml  = $this->get_xml( $zip, $worksheet_path );

		$zip->close();

		if ( ! $worksheet_xml instanceof SimpleXMLElement ) {
			$report['message'] = __( 'The uploaded file could not be read.', 'certificate-validation-plugin' );
			return $report;
		}

		$rows = $this->get_worksheet_rows( $worksheet_xml, $shared_strings );

		if ( empty( $rows ) ) {
			$report['message'] = __( 'The uploaded file is empty.', 'certificate-validation-plugin' );
			return $report;
		}

		$header_row = array_shift( $rows );

		if ( self::EXPECTED_HEADERS !== $header_row['values'] ) {
			$report['message'] = __( 'Invalid file format. Please use the provided template.', 'certificate-validation-plugin' );
			return $report;
		}

		$report = $this->process_rows( $rows );

		if ( $report['skipped'] > 0 ) {
			$report['message'] = __( 'Import completed with some skipped rows.', 'certificate-validation-plugin' );
		} else {
			$report['message'] = __( 'Import completed successfully.', 'certificate-validation-plugin' );
		}

		$report['success'] = true;

		return $report;
	}

	/**
	 * Processes worksheet rows after the header.
	 *
	 * @param array $rows Parsed worksheet rows.
	 * @return array
	 */
	protected function process_rows( $rows ) {
		$report = array(
			'success' => false,
			'message' => '',
			'total'   => 0,
			'imported'=> 0,
			'skipped' => 0,
			'details' => array(),
		);
		$file_codes = array();
		$db_codes   = $this->get_existing_database_codes( $rows );

		foreach ( $rows as $row ) {
			++$report['total'];

			$row_number = absint( $row['row_number'] );
			$row_data   = $this->map_row_values_to_data( $row['values'] );
			$validation = $this->validate_row_data( $row_data, $row_number, $file_codes, $db_codes );

			if ( ! empty( $validation['errors'] ) ) {
				++$report['skipped'];
				$report['details'] = array_merge( $report['details'], $validation['errors'] );
				continue;
			}

			$inserted = $this->repository->insert_certificate( $validation['data'] );

			if ( false === $inserted ) {
				++$report['skipped'];
				$report['details'][] = sprintf(
					/* translators: 1: row number, 2: code. */
					__( 'Row %1$d: code already exists in DB (%2$s)', 'certificate-validation-plugin' ),
					$row_number,
					$validation['data']['code']
				);
				$db_codes[ $validation['data']['code'] ] = true;
				continue;
			}

			$db_codes[ $validation['data']['code'] ] = true;
			++$report['imported'];
		}

		return $report;
	}

	/**
	 * Validates and normalizes a row.
	 *
	 * @param array $row_data Row data.
	 * @param int   $row_number Worksheet row number.
	 * @param array $file_codes Seen file codes.
	 * @param array $db_codes Existing database codes.
	 * @return array
	 */
	protected function validate_row_data( $row_data, $row_number, &$file_codes, $db_codes ) {
		$errors   = array();
		$row_data = $this->sanitize_row_data( $row_data );

		$required_fields = array(
			'code'        => __( 'missing code', 'certificate-validation-plugin' ),
			'full_name'   => __( 'missing full name', 'certificate-validation-plugin' ),
			'course'      => __( 'missing course', 'certificate-validation-plugin' ),
			'hours'       => __( 'missing hours', 'certificate-validation-plugin' ),
			'ects_hours'  => __( 'missing ECTS hours', 'certificate-validation-plugin' ),
			'issued_date' => __( 'missing issued date', 'certificate-validation-plugin' ),
		);

		foreach ( $required_fields as $field_key => $message ) {
			if ( '' === $row_data[ $field_key ] ) {
				$errors[] = sprintf(
					/* translators: 1: row number, 2: validation error. */
					__( 'Row %1$d: %2$s', 'certificate-validation-plugin' ),
					$row_number,
					$message
				);
			}
		}

		if ( '' !== $row_data['code'] ) {
			if ( isset( $file_codes[ $row_data['code'] ] ) ) {
				$errors[] = sprintf(
					/* translators: 1: row number, 2: duplicate code. */
					__( 'Row %1$d: duplicate code in file (%2$s)', 'certificate-validation-plugin' ),
					$row_number,
					$row_data['code']
				);
			} else {
				$file_codes[ $row_data['code'] ] = true;
			}

			if ( isset( $db_codes[ $row_data['code'] ] ) ) {
				$errors[] = sprintf(
					/* translators: 1: row number, 2: duplicate code. */
					__( 'Row %1$d: code already exists in DB (%2$s)', 'certificate-validation-plugin' ),
					$row_number,
					$row_data['code']
				);
			}
		}

		if ( '' !== $row_data['hours'] && ! is_numeric( $row_data['hours'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: hours must be numeric', 'certificate-validation-plugin' ),
				$row_number
			);
		}

		if ( '' !== $row_data['ects_hours'] && ! is_numeric( $row_data['ects_hours'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: row number. */
				__( 'Row %d: ects_hours must be numeric', 'certificate-validation-plugin' ),
				$row_number
			);
		}

		if ( '' !== $row_data['issued_date'] ) {
			$row_data['issued_date'] = $this->normalize_date( $row_data['issued_date'] );

			if ( '' === $row_data['issued_date'] ) {
				$errors[] = sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: invalid issued_date', 'certificate-validation-plugin' ),
					$row_number
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'errors' => $errors,
				'data'   => $row_data,
			);
		}

		$row_data['hours']      = (int) $row_data['hours'];
		$row_data['ects_hours'] = (int) $row_data['ects_hours'];

		return array(
			'errors' => array(),
			'data'   => $row_data,
		);
	}

	/**
	 * Sanitizes row values.
	 *
	 * @param array $row_data Row data.
	 * @return array
	 */
	protected function sanitize_row_data( $row_data ) {
		return array(
			'code'        => strtoupper( trim( sanitize_text_field( $row_data['code'] ) ) ),
			'full_name'   => sanitize_text_field( $row_data['full_name'] ),
			'course'      => sanitize_text_field( $row_data['course'] ),
			'hours'       => trim( sanitize_text_field( $row_data['hours'] ) ),
			'ects_hours'  => trim( sanitize_text_field( $row_data['ects_hours'] ) ),
			'issued_date' => trim( sanitize_text_field( $row_data['issued_date'] ) ),
			'course_link' => '' !== trim( $row_data['link'] ) ? esc_url_raw( trim( $row_data['link'] ) ) : '',
		);
	}

	/**
	 * Maps worksheet values to row keys.
	 *
	 * @param array $values Row values.
	 * @return array
	 */
	protected function map_row_values_to_data( $values ) {
		$mapped = array_fill_keys( self::EXPECTED_HEADERS, '' );

		foreach ( self::EXPECTED_HEADERS as $index => $header ) {
			if ( isset( $values[ $index ] ) ) {
				$mapped[ $header ] = (string) $values[ $index ];
			}
		}

		return $mapped;
	}

	/**
	 * Returns existing database codes for the uploaded rows.
	 *
	 * @param array $rows Parsed rows.
	 * @return array
	 */
	protected function get_existing_database_codes( $rows ) {
		$codes = array();

		foreach ( $rows as $row ) {
			if ( ! isset( $row['values'][0] ) ) {
				continue;
			}

			$code = strtoupper( trim( sanitize_text_field( $row['values'][0] ) ) );

			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}

		return $this->repository->get_existing_codes( array_unique( $codes ) );
	}

	/**
	 * Normalizes a date value to Y-m-d when possible.
	 *
	 * @param string $value Date value.
	 * @return string
	 */
	protected function normalize_date( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$date = DateTime::createFromFormat( 'Y-m-d', $value );

			return $date instanceof DateTime && $date->format( 'Y-m-d' ) === $value ? $value : '';
		}

		if ( is_numeric( $value ) ) {
			$serial = (float) $value;

			if ( $serial > 0 ) {
				$date = new DateTime( '1899-12-30' );
				$date->modify( '+' . (int) floor( $serial ) . ' days' );
				return $date->format( 'Y-m-d' );
			}
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Returns shared strings from the XLSX archive.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @return array
	 */
	protected function get_shared_strings( $zip ) {
		$shared_strings = array();
		$xml            = $this->get_xml( $zip, 'xl/sharedStrings.xml' );

		if ( ! $xml instanceof SimpleXMLElement ) {
			return $shared_strings;
		}

		$string_nodes = $xml->xpath( '//*[local-name()="si"]' );

		if ( ! is_array( $string_nodes ) ) {
			return $shared_strings;
		}

		foreach ( $string_nodes as $string_node ) {
			$shared_strings[] = $this->extract_text_from_node( $string_node );
		}

		return $shared_strings;
	}

	/**
	 * Returns the first worksheet path inside the XLSX file.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @return string
	 */
	protected function get_first_worksheet_path( $zip ) {
		$workbook_xml = $this->get_xml( $zip, 'xl/workbook.xml' );
		$rels_xml     = $this->get_xml( $zip, 'xl/_rels/workbook.xml.rels' );

		if ( ! $workbook_xml instanceof SimpleXMLElement || ! $rels_xml instanceof SimpleXMLElement ) {
			return 'xl/worksheets/sheet1.xml';
		}

		$sheet_nodes = $workbook_xml->xpath( '//*[local-name()="sheets"]/*[local-name()="sheet"]' );

		if ( ! is_array( $sheet_nodes ) || empty( $sheet_nodes[0] ) ) {
			return 'xl/worksheets/sheet1.xml';
		}

		$relationship_id = (string) $sheet_nodes[0]->attributes( 'r', true )->id;
		$relationship_nodes = $rels_xml->xpath( '//*[local-name()="Relationship"]' );

		if ( empty( $relationship_id ) || ! is_array( $relationship_nodes ) ) {
			return 'xl/worksheets/sheet1.xml';
		}

		foreach ( $relationship_nodes as $relationship_node ) {
			if ( $relationship_id !== (string) $relationship_node['Id'] ) {
				continue;
			}

			$target = (string) $relationship_node['Target'];
			$target = str_replace( array( '../', '..\\' ), '', $target );

			if ( '' === $target ) {
				break;
			}

			if ( 0 === strpos( $target, '/xl/' ) ) {
				return ltrim( $target, '/' );
			}

			return 'xl/' . ltrim( $target, '/' );
		}

		return 'xl/worksheets/sheet1.xml';
	}

	/**
	 * Returns parsed worksheet rows.
	 *
	 * @param SimpleXMLElement $worksheet_xml Worksheet XML.
	 * @param array            $shared_strings Shared strings.
	 * @return array
	 */
	protected function get_worksheet_rows( $worksheet_xml, $shared_strings ) {
		$rows      = array();
		$row_nodes = $worksheet_xml->xpath( '//*[local-name()="sheetData"]/*[local-name()="row"]' );

		if ( ! is_array( $row_nodes ) ) {
			return $rows;
		}

		foreach ( $row_nodes as $row_node ) {
			$row_number = isset( $row_node['r'] ) ? absint( $row_node['r'] ) : 0;
			$values     = array();
			$max_index  = -1;
			$cell_nodes = $row_node->xpath( './*[local-name()="c"]' );

			if ( is_array( $cell_nodes ) ) {
				foreach ( $cell_nodes as $cell_node ) {
					$reference = isset( $cell_node['r'] ) ? (string) $cell_node['r'] : '';
					$index     = $this->get_column_index_from_reference( $reference );

					if ( $index < 0 ) {
						continue;
					}

					$values[ $index ] = $this->get_cell_value( $cell_node, $shared_strings );
					$max_index        = max( $max_index, $index );
				}
			}

			if ( $max_index >= 0 ) {
				for ( $index = 0; $index <= $max_index; ++$index ) {
					if ( ! isset( $values[ $index ] ) ) {
						$values[ $index ] = '';
					}
				}

				ksort( $values );
				$values = array_values( $values );
			}

			$rows[] = array(
				'row_number' => $row_number,
				'values'     => $values,
			);
		}

		return $rows;
	}

	/**
	 * Returns a cell value from worksheet XML.
	 *
	 * @param SimpleXMLElement $cell_node Cell node.
	 * @param array            $shared_strings Shared strings.
	 * @return string
	 */
	protected function get_cell_value( $cell_node, $shared_strings ) {
		$type = isset( $cell_node['t'] ) ? (string) $cell_node['t'] : '';

		if ( 'inlineStr' === $type ) {
			return $this->extract_text_from_node( $cell_node );
		}

		$value_nodes = $cell_node->xpath( './*[local-name()="v"]' );
		$value       = isset( $value_nodes[0] ) ? (string) $value_nodes[0] : '';

		if ( 's' === $type ) {
			$index = absint( $value );
			return isset( $shared_strings[ $index ] ) ? $shared_strings[ $index ] : '';
		}

		if ( 'b' === $type ) {
			return '1' === $value ? '1' : '0';
		}

		return (string) $value;
	}

	/**
	 * Extracts text from an XML node.
	 *
	 * @param SimpleXMLElement $node XML node.
	 * @return string
	 */
	protected function extract_text_from_node( $node ) {
		$text = '';
		$text_nodes = $node->xpath( './/*[local-name()="t"]' );

		if ( is_array( $text_nodes ) && ! empty( $text_nodes ) ) {
			foreach ( $text_nodes as $text_node ) {
				$text .= (string) $text_node;
			}

			return $text;
		}

		return trim( (string) $node );
	}

	/**
	 * Returns a worksheet column index from a cell reference.
	 *
	 * @param string $reference Cell reference.
	 * @return int
	 */
	protected function get_column_index_from_reference( $reference ) {
		if ( ! preg_match( '/^([A-Z]+)/', strtoupper( $reference ), $matches ) ) {
			return -1;
		}

		$column = $matches[1];
		$index  = 0;
		$length = strlen( $column );

		for ( $i = 0; $i < $length; ++$i ) {
			$index = ( $index * 26 ) + ( ord( $column[ $i ] ) - 64 );
		}

		return $index - 1;
	}

	/**
	 * Returns a parsed XML file from the ZIP archive.
	 *
	 * @param ZipArchive $zip ZIP archive.
	 * @param string     $path File path.
	 * @return SimpleXMLElement|null
	 */
	protected function get_xml( $zip, $path ) {
		$contents = $zip->getFromName( $path );

		if ( false === $contents || '' === $contents ) {
			return null;
		}

		$previous_state = libxml_use_internal_errors( true );
		$xml            = simplexml_load_string( $contents );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_state );

		return $xml instanceof SimpleXMLElement ? $xml : null;
	}
}
