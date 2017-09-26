<?php

class EDD_MailChimp_Sync extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'edd_mailchimp_sync';

	protected $full_sync = false;

	/**
	 * Task
	 *
	 * Perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param array $data An array containing job status and related job data
	 *
	 * @return mixed
	 */
	protected function task( $data ) {
		global $wpdb;
		$mailchimp = new EDD_MailChimp_API;
		$store     = EDD_MailChimp_Store::find_or_create( $data['payload']['list_id'] );

		switch( $data['status'] ) {

			case 'queued':
				// Get to work.
				$batch = $mailchimp->api->new_batch();
				$model_type = $data['payload']['sync_type'];

				// Get this individual task's model IDs to be worked upon in this iteration.
				$record_ids = self::get_record_ids( $model_type, $data['payload']['offset'], $data['payload']['batch_size'] );

				if ( ! empty( $record_ids ) ) {

					$store->is_syncing();

					foreach( $record_ids as $id ) {
						$edd_wrapper_class_name   = $data['payload']['edd_wrapper_class_name'];
						$edd_mailchimp_model_name = $data['payload']['edd_mailchimp_model_name'];

						$edd_wrapper_instance = new $edd_wrapper_class_name( $id );
						$edd_mailchimp_model  = new $edd_mailchimp_model_name( $edd_wrapper_instance );

						$store_resource = $store->get_resource();

						$batch->post("$model_type-$id", $store_resource . '/' . $model_type, $edd_mailchimp_model->get_record() );
					}

					// The result includes a batch ID.
					$result = $batch->execute();

					$data['status'] = 'working';
					$data['payload']['mailchimp_batch'] = $result;

					return $data;
				}

				// Mark job as complete if no items found.
				$data['status'] = 'complete';
				return $data;

			case 'working':
				// Check in on it.
				$batch_id = $data['payload']['mailchimp_batch']['id'];
				$batch    = $mailchimp->api->new_batch($batch_id);
				$result   = $batch->check_status();

				// Set sync status based on MailChimp batch status
				$wpdb->update(
					$wpdb->edd_mailchimp_lists,
					array( 'sync_status' => $result['status'] ),
					array( 'remote_id' => $data['payload']['list_id'] ),
					array( '%s'),
					array( '%s' )
				);

				// Set job status here to whatever based on MailChimp job results

				// @todo You can log total_operations as soon batch has reached *started* status
				// Everything else should wait until finished.
				switch( $result['status'] ) {

					case 'pending':
					case 'preprocessing':
					case 'started':
					case 'finalizing':
						sleep(1);
						return $data;

					case 'finished':
						$data['status'] = 'complete';
						return $data;
				}

				return false;
			case 'failed':
			case 'interrupted':
			case 'complete':
				// Set list synced_at in edd_mailchimp_lists table
				$wpdb->update(
					$wpdb->edd_mailchimp_lists,
					array( 'synced_at' => current_time( 'mysql' ) ),
					array( 'remote_id' => $data['payload']['list_id'] ),
					array( '%s'),
					array( '%s' )
				);

				if ( $data['payload']['is_full_sync'] === true ) {
					$this->full_sync = true;
					$this->store = $store;
				} else {
					$store->is_syncing( false );
				}

				return false;
			default:
				return false;
				break;
		}
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		// If is_full_sync, fire off orders sync job
		if ( $this->full_sync === true ) {
			$this->store->sync( 'orders' );
		}

		// Show notice to user or perform some other arbitrary task... (optional)
	}

	/**
	 * Fetch the record IDs for a given batch based on
	 * the current model type that we are working with in this task.
	 *
	 * @param  string $model_type The name of the type of model (products, customers, orders)
	 * @param  int $offset        How many records should we skip in the result set
	 * @param  int $batch_size    The limit of how many records to fetch
	 * @return array              Record IDs
	 */
	private static function get_record_ids($model_type, $offset, $batch_size) {
		global $wpdb;

		switch( $model_type ) {
			case 'products':
				$download_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID
					FROM $wpdb->posts
					WHERE post_type = 'download'
					AND post_status = 'publish'
					ORDER BY post_date DESC
					LIMIT %d,%d;",
					$offset,
					$batch_size
				) );

				return $download_ids;
			case 'orders':
				$payment_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID
					FROM $wpdb->posts
					WHERE post_type = 'edd_payment'
					ORDER BY post_date DESC
					LIMIT %d,%d;",
					$offset,
					$batch_size
				) );

				return $payment_ids;
			default:
				return false;
		}
	}

}
