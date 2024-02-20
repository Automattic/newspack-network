jQuery( document ).ready( () => {
	toggleMetadataSettings = function () {
		const metadataSettings = jQuery( '#np_network_esp_metadata_settings' ).val();
		if ( 'default' === metadataSettings ) {
			jQuery( '#newspack-network-select-fields-row' ).hide();
		} else {
			jQuery( '#newspack-network-select-fields-row' ).show();
		}
	};

	toggleMetadataSettings();

	jQuery( '#np_network_esp_metadata_settings' ).change( toggleMetadataSettings );
} );
