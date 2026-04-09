<?php
/**
 * Certificate export helper.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles certificate export file generation.
 */
class CVP_Certificate_Exporter {

	/**
	 * Export column order.
	 *
	 * @var array
	 */
	const EXPORT_COLUMNS = array(
		'code',
		'full_name',
		'course',
		'hours',
		'ects_hours',
		'issued_date',
		'link',
	);

	/**
	 * Streams a CSV export.
	 *
	 * @param array  $certificates Export rows.
	 * @param string $file_name Download file name.
	 * @return true|WP_Error
	 */
	public function download_csv( $certificates, $file_name ) {
		$this->clear_output_buffers();
		$handle = fopen( 'php://output', 'wb' );

		if ( false === $handle ) {
			return new WP_Error( 'cvp_export_csv_open_failed', __( 'The CSV export could not be generated.', 'certificate-validation-plugin' ) );
		}

		$this->send_download_headers( $file_name, 'text/csv; charset=utf-8' );

		fwrite( $handle, "\xEF\xBB\xBF" );
		fputcsv( $handle, self::EXPORT_COLUMNS, ',' );

		foreach ( $this->normalize_rows( $certificates ) as $row ) {
			fputcsv( $handle, $row, ',' );
		}

		fclose( $handle );

		return true;
	}

	/**
	 * Streams an XLSX export.
	 *
	 * @param array  $certificates Export rows.
	 * @param string $file_name Download file name.
	 * @return true|WP_Error
	 */
	public function download_xlsx( $certificates, $file_name ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'cvp_export_xlsx_ziparchive_unavailable',
				__( 'The server does not support .xlsx exports because ZipArchive is unavailable.', 'certificate-validation-plugin' )
			);
		}

		$temp_file = wp_tempnam( $file_name );

		if ( ! $temp_file ) {
			return new WP_Error( 'cvp_export_xlsx_temp_file_failed', __( 'The XLSX export could not be generated.', 'certificate-validation-plugin' ) );
		}

		$build_result = $this->build_xlsx_file( $this->normalize_rows( $certificates ), $temp_file );

		if ( is_wp_error( $build_result ) ) {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}

			return $build_result;
		}

		$this->clear_output_buffers();
		$this->send_download_headers(
			$file_name,
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			filesize( $temp_file )
		);

		$streamed = readfile( $temp_file );
		wp_delete_file( $temp_file );

		if ( false === $streamed ) {
			return new WP_Error( 'cvp_export_xlsx_stream_failed', __( 'The XLSX export could not be downloaded.', 'certificate-validation-plugin' ) );
		}

		return true;
	}

	/**
	 * Sends download headers.
	 *
	 * @param string   $file_name File name.
	 * @param string   $content_type Response content type.
	 * @param int|null $content_length Optional content length.
	 * @return void
	 */
	protected function send_download_headers( $file_name, $content_type, $content_length = null ) {
		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file_name ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );

		if ( null !== $content_length && $content_length > 0 ) {
			header( 'Content-Length: ' . absint( $content_length ) );
		}
	}

	/**
	 * Clears output buffers before streaming a download.
	 *
	 * @return void
	 */
	protected function clear_output_buffers() {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	/**
	 * Normalizes certificate rows to the export column order.
	 *
	 * @param array $certificates Raw certificate rows.
	 * @return array
	 */
	protected function normalize_rows( $certificates ) {
		$rows = array();

		foreach ( (array) $certificates as $certificate ) {
			$row = array();

			foreach ( self::EXPORT_COLUMNS as $column ) {
				$row[] = isset( $certificate[ $column ] ) ? (string) $certificate[ $column ] : '';
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Builds an XLSX file on disk.
	 *
	 * @param array  $rows Export rows.
	 * @param string $file_path Target file path.
	 * @return true|WP_Error
	 */
	protected function build_xlsx_file( $rows, $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'cvp_export_xlsx_ziparchive_unavailable',
				__( 'The server does not support .xlsx exports because ZipArchive is unavailable.', 'certificate-validation-plugin' )
			);
		}

		$zip    = new ZipArchive();
		$opened = $zip->open( $file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		if ( true !== $opened ) {
			return new WP_Error( 'cvp_export_xlsx_open_failed', __( 'The XLSX export could not be generated.', 'certificate-validation-plugin' ) );
		}

		$added = $zip->addFromString( '[Content_Types].xml', $this->get_content_types_xml() );
		$added = $added && $zip->addFromString( '_rels/.rels', $this->get_root_relationships_xml() );
		$added = $added && $zip->addFromString( 'xl/workbook.xml', $this->get_workbook_xml() );
		$added = $added && $zip->addFromString( 'xl/_rels/workbook.xml.rels', $this->get_workbook_relationships_xml() );
		$added = $added && $zip->addFromString( 'xl/worksheets/sheet1.xml', $this->get_worksheet_xml( $rows ) );

		$zip->close();

		if ( ! $added ) {
			return new WP_Error( 'cvp_export_xlsx_write_failed', __( 'The XLSX export could not be generated.', 'certificate-validation-plugin' ) );
		}

		return true;
	}

	/**
	 * Returns the content types XML.
	 *
	 * @return string
	 */
	protected function get_content_types_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '</Types>';
	}

	/**
	 * Returns the root relationships XML.
	 *
	 * @return string
	 */
	protected function get_root_relationships_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	/**
	 * Returns the workbook XML.
	 *
	 * @return string
	 */
	protected function get_workbook_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
			. 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Certificates" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	/**
	 * Returns the workbook relationships XML.
	 *
	 * @return string
	 */
	protected function get_workbook_relationships_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '</Relationships>';
	}

	/**
	 * Returns the worksheet XML.
	 *
	 * @param array $rows Export rows without headers.
	 * @return string
	 */
	protected function get_worksheet_xml( $rows ) {
		$sheet_rows = array_merge(
			array( self::EXPORT_COLUMNS ),
			$rows
		);
		$xml_rows   = '';

		foreach ( $sheet_rows as $row_index => $row ) {
			$row_number = $row_index + 1;
			$xml_rows  .= '<row r="' . $row_number . '">';

			foreach ( self::EXPORT_COLUMNS as $column_index => $column ) {
				$cell_reference = $this->get_column_name( $column_index ) . $row_number;
				$cell_value     = isset( $row[ $column_index ] ) ? $row[ $column_index ] : '';
				$xml_rows      .= '<c r="' . $cell_reference . '" t="inlineStr"><is><t>'
					. $this->escape_xml_string( $cell_value )
					. '</t></is></c>';
			}

			$xml_rows .= '</row>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData>' . $xml_rows . '</sheetData>'
			. '</worksheet>';
	}

	/**
	 * Returns an Excel column name for a zero-based index.
	 *
	 * @param int $index Column index.
	 * @return string
	 */
	protected function get_column_name( $index ) {
		$index  = (int) $index;
		$column = '';

		do {
			$remainder = $index % 26;
			$column    = chr( 65 + $remainder ) . $column;
			$index     = (int) floor( $index / 26 ) - 1;
		} while ( $index >= 0 );

		return $column;
	}

	/**
	 * Escapes a string for XML text output.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function escape_xml_string( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value );

		return htmlspecialchars( $value, ENT_XML1, 'UTF-8' );
	}
}
