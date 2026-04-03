<?php
/**
 * Certificates list table.
 *
 * @package CertificateValidationPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once CVP_PLUGIN_DIR . 'includes/class-cvp-certificate-repository.php';

/**
 * Admin certificates list table.
 */
class CVP_Certificates_List_Table extends WP_List_Table {

	/**
	 * Certificate repository.
	 *
	 * @var CVP_Certificate_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'certificate',
				'plural'   => 'certificates',
				'ajax'     => false,
			)
		);

		$this->repository = new CVP_Certificate_Repository();
	}

	/**
	 * Returns table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'code'        => esc_html__( 'Code', 'certificate-validation-plugin' ),
			'name'        => esc_html__( 'Name', 'certificate-validation-plugin' ),
			'surname'     => esc_html__( 'Surname', 'certificate-validation-plugin' ),
			'course'      => esc_html__( 'Course', 'certificate-validation-plugin' ),
			'issued_date' => esc_html__( 'Issued Date', 'certificate-validation-plugin' ),
			'created_at'  => esc_html__( 'Created At', 'certificate-validation-plugin' ),
			'updated_at'  => esc_html__( 'Updated At', 'certificate-validation-plugin' ),
		);
	}

	/**
	 * Returns sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array();
	}

	/**
	 * Returns available bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => esc_html__( 'Delete', 'certificate-validation-plugin' ),
		);
	}

	/**
	 * Renders the checkbox column.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="certificate_ids[]" value="%d" />',
			absint( $item['id'] )
		);
	}

	/**
	 * Renders the code column with row actions.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_code( $item ) {
		$return_args = array();

		if ( isset( $_REQUEST['paged'] ) ) {
			$return_args['paged'] = absint( wp_unslash( $_REQUEST['paged'] ) );
		}

		if ( isset( $_REQUEST['s'] ) ) {
			$return_args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		$edit_url = add_query_arg(
			array_merge(
				array(
				'page'           => 'cvp-add-certificate',
				'certificate_id' => absint( $item['id'] ),
				),
				$return_args
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array_merge(
					array(
					'action'         => 'cvp_delete_certificate',
					'certificate_id' => absint( $item['id'] ),
					),
					$return_args
				),
				admin_url( 'admin-post.php' )
			),
			'cvp_delete_certificate_' . absint( $item['id'] )
		);

		$actions = array(
			'edit' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'certificate-validation-plugin' )
			),
			'delete' => sprintf(
				'<a href="%1$s" onclick="return window.confirm(\'%2$s\');">%3$s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure?', 'certificate-validation-plugin' ) ),
				esc_html__( 'Delete', 'certificate-validation-plugin' )
			),
		);

		return sprintf(
			'%1$s %2$s',
			esc_html( $item['code'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Item data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( ! isset( $item[ $column_name ] ) ) {
			return '';
		}

		return esc_html( (string) $item[ $column_name ] );
	}

	/**
	 * Prepares items for display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$search   = '';
		$per_page = 20;
		$page     = $this->get_pagenum();

		if ( isset( $_REQUEST['s'] ) ) {
			$search = wp_unslash( $_REQUEST['s'] );
		}

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$this->items = $this->repository->get_certificates(
			array(
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);

		$total_items = $this->repository->count_certificates( $search );
		$total_pages = (int) ceil( $total_items / $per_page );

		if ( $total_pages < 1 ) {
			$total_pages = 1;
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => $total_pages,
			)
		);
	}

	/**
	 * Returns the message shown when no items exist.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No certificates found.', 'certificate-validation-plugin' );
	}
}
