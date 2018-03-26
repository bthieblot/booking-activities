<?php
/**
 * Form editor page
 * @since 1.5.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

$can_create_form	= current_user_can( 'bookacti_create_forms' );
$can_edit_form		= current_user_can( 'bookacti_edit_forms' );
$can_delete_form	= current_user_can( 'bookacti_delete_forms' );

if( empty( $_REQUEST[ 'action' ] ) && ! empty( $_REQUEST[ 'form_id' ] ) ) {
	$_REQUEST[ 'action' ] = is_numeric( $_REQUEST[ 'form_id' ] ) ? 'edit' : 'new';
}

$form_id = ! empty( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] === 'new' ? 'new' : intval( $_REQUEST[ 'form_id' ] );

if( ! $form_id ) { return; }

// Default form data
if( $form_id === 'new' ) {
	$form = new stdClass();
	$form->id		= 'new';
	$form->title	= '';
	$form->active	= 1;

// Get form data by id
} else {
	$filters	= bookacti_format_form_filters( array( 'id' => array( $form_id ) ) );
	$forms		= bookacti_get_forms( $filters );
	
	if( empty( $forms[ 0 ] ) || empty( $forms ) ) { return; }
	
	$form = $forms[ 0 ];
}

?>
<div class='wrap'>
	<h1>
	<?php 
		echo $form_id === 'new' ? esc_html__( 'Add New Booking Form', BOOKACTI_PLUGIN_NAME ) : esc_html__( 'Edit Booking Form', BOOKACTI_PLUGIN_NAME ); 
	?>
	</h1>
	<hr class='wp-header-end' />
	
	<?php
		// Display contextual notices
		if( ! empty( $_REQUEST[ 'notice' ] ) &&  $_REQUEST[ 'notice' ] === 'created' ) {
		?>
			<div class='notice notice-success is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'The booking form has been created.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
		}
	?>
	
	<div id='bookacti-form-editor-page-container' >
		<?php
			do_action( 'bookacti_form_editor_page_before', $form );
			$form_action = $form_id === 'new' ? 'new' : 'edit';
			$redirect_url = 'admin.php?page=bookacti_forms';
		?>
		<form name='post' action='<?php echo $redirect_url; ?>' method='post' id='bookacti-form-editor-page-form' >
			<?php
			/* Used to save closed meta boxes and their order */
			wp_nonce_field( 'bookacti_insert_or_update_form', 'nonce_insert_or_update_form', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			?>
			<input type='hidden' name='page' value='bookacti_forms' />
			<input type='hidden' name='action' value='<?php echo $form_id === 'new' ? 'bookactiInsertForm' : 'bookactiUpdateForm'; ?>' />
			<input type='hidden' name='form_id' value='<?php echo $form_id; ?>' id='bookacti-form-id' />
			
			<div id='poststuff'>
				<div id='post-body' class='metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>'>
					<div id='post-body-content'>
						<div id='titlediv'>
							<div id='titlewrap'>
								<?php $title_placeholder = __( 'Enter form title here', BOOKACTI_PLUGIN_NAME ); ?>
								<label class='screen-reader-text' id='title-prompt-text' for='title'><?php echo $title_placeholder; ?></label>
								<input type='text' name='form_title' size='30' value='<?php echo esc_attr( $form->title ); ?>' id='title' spellcheck='true' autocomplete='off' placeholder='<?php echo $title_placeholder; ?>' required />
							</div>
						</div>
						
						<div id='postdivrich' class='postarea' >
						<?php
							// Check if the form editor shall be displayed
							$error_message = '';
							// Check if the form is published
							if( empty( $form_id ) || ! is_numeric( $form_id ) ) {
								$error_message = esc_html__( 'Please set a title and publish your form first.', BOOKACTI_PLUGIN_NAME );
							
							// Check if the user has available calendars
							} else {
								
								$templates = bookacti_fetch_templates();
								if( ! $templates ) {
									$editor_path	= 'admin.php?page=bookacti_calendars';
									$editor_url		= admin_url( $editor_path );
									$error_message	= sprintf( esc_html__( 'Welcome! It seems you don\'t have any calendar yet. Go to %1$sCalendar Editor%2$s to create your first calendar.', BOOKACTI_PLUGIN_NAME ),
														'<a href="' . esc_url( $editor_url ) . '" >', 
														'</a>' 
													);
								}
							}
							
							// Form editor not available error message
							if( $error_message ) {
							?>
								<div id='bookacti-form-editor-not-available' ><h2><?php echo $error_message; ?></h2></div>
							<?php
							
							// FORM EDITOR
							} else {
								
								// Display a nonce for form field order
								wp_nonce_field( 'bookacti_form_field_order', 'bookacti_nonce_form_field_order', false );
								
								// Get form fields in the custom order
								$fields_data = bookacti_get_default_form_fields_data();
								$form_fields = bookacti_get_form_fields_data( $form_id );
								$is_new_form = empty( $form_fields );
								
								// Make sure that all compulsory fields will be displayed
								foreach( $fields_data as $field_name => $field_data ) {
									if( ! ( ! empty( $field_data[ 'compulsory' ] ) || ( $is_new_form && $field_data[ 'default' ] ) ) ) { continue; }
									$is_displayed = false;
									foreach( $form_fields as $j => $form_field ) {
										if( $form_field[ 'name' ] === $field_name ) { 
											$is_displayed = true; 
											break; 
										}
									}
									if( ! $is_displayed ) { 
										$form_fields[] = $field_data; 
									}
								}
								$form_fields = bookacti_sort_form_fields_array( $form_id, $form_fields );
								?>
								
								<div id='bookacti-form-editor-container' >
									<div id='bookacti-form-editor-title' >
										<h2><?php _e( 'Form editor', BOOKACTI_PLUGIN_NAME ) ?></h2>
										<span id='bookacti-add-field-to-form' class='bookacti-edit-form-field dashicons dashicons-plus' title='<?php _e( 'Add a new field to your form', BOOKACTI_PLUGIN_NAME ); ?>'></span>
									</div>
									<div id='bookacti-form-editor-description' >
										<p>
										<?php 
											/* translators: the placeholders are icons related to the action */
											echo sprintf( __( 'Click on %1$s to add, %2$s to edit, %3$s to remove and %4$s to preview your form fields.<br/>Drag and drop fields to switch their positions.', BOOKACTI_PLUGIN_NAME ),
												'<span class="dashicons dashicons-plus"></span>',
												'<span class="dashicons dashicons-admin-generic"></span>',
												'<span class="dashicons dashicons-trash"></span>',
												'<span class="dashicons dashicons-arrow-down"></span>' ); 
											do_action( 'bookacti_form_editor_description_after', $form );
										?>
										</p>
									</div>
									<div id='bookacti-form-editor' >
										<?php
										do_action( 'bookacti_form_editor_before', $form );
										
										// Display form fields 
										foreach( $form_fields as $form_field ) {
											$field_name = $form_field[ 'name' ];
										?>
										<div id='bookacti-form-editor-field-<?php echo $field_name; ?>' class='bookacti-form-editor-field' data-field-name='<?php echo $field_name; ?>' >
											<div class='bookacti-form-editor-field-header' >
												<h3 class='bookacti-form-editor-field-title' >
													<?php echo $form_field[ 'title' ]; ?>
												</h3>
												<div class='bookacti-form-editor-field-actions' >
													<div class='bookacti-form-editor-field-action bookacti-edit-form-field dashicons dashicons-admin-generic' title='<?php _e( 'Change field settings', BOOKACTI_PLUGIN_NAME ); ?>'></div>
												<?php if( ! $form_field[ 'compulsory' ] ) { ?>
													<div class='bookacti-form-editor-field-action bookacti-remove-form-field dashicons dashicons-trash' title='<?php _e( 'Remove this field', BOOKACTI_PLUGIN_NAME ); ?>'></div>
												<?php } ?>
													<div class='bookacti-field-toggle dashicons dashicons-arrow-down' title='<?php _e( 'Show / Hide', BOOKACTI_PLUGIN_NAME ); ?>'></div>
												</div>
											</div>
											<div class='bookacti-form-editor-field-body' style='display:none;' >
											<?php
												bookacti_diplay_form_field( $form_field, $form_id, 'form-editor-instance', 'edit' );
											?>
											</div>
										</div>
										<?php
										}
										
										do_action( 'bookacti_form_editor_after', $form );
										?>
									</div>
								</div>
								<?php
							}
						?>
						</div>
					</div>
					<div id='postbox-container-1' class='postbox-container'>
					<?php
						do_meta_boxes( null, 'side', $form );
					?>
					</div>
					<div id='postbox-container-2' class='postbox-container'>
					<?php
						do_meta_boxes( null, 'normal', $form );
						do_meta_boxes( null, 'advanced', $form );
					?>
					</div>
				</div>
				<br class='clear' />
			</div>
		</form>
		<?php
			do_action( 'bookacti_form_editor_page_after', $form );
		?>
	</div>
</div>
<?php
// Include dialogs
//include_once( 'view-form-editor-dialogs.php' );