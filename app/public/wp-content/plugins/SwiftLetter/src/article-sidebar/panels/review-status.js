import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ReviewStatusPanel() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );

	const reviewConfirmed = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )?._swl_review_confirmed;
	}, [] );

	const reviewConfirmedAt = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )?._swl_review_confirmed_at;
	}, [] );

	const isPostDirty = useSelect( ( select ) => {
		return select( 'core/editor' ).isEditedPostDirty();
	}, [] );

	const [ confirming, setConfirming ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleConfirm = async () => {
		setConfirming( true );
		setError( null );

		try {
			await apiFetch( {
				path: `/swiftletter/v1/articles/${ postId }/confirm-review`,
				method: 'POST',
			} );

			// Refresh the editor to pick up meta changes.
			window.location.reload();
		} catch ( err ) {
			setError( err.message || __( 'Failed to confirm review.', 'swiftletter' ) );
		} finally {
			setConfirming( false );
		}
	};

	// Keyboard shortcut: Alt+Shift+R triggers review confirmation.
	useEffect( () => {
		function onKeyDown( event ) {
			if ( ! event.altKey || ! event.shiftKey || event.ctrlKey || event.metaKey ) return;
			if ( event.key.toLowerCase() !== 'r' ) return;
			const a = document.activeElement;
			if ( a && ( [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( a.tagName ) || a.isContentEditable ) ) return;
			event.preventDefault();
			if ( ! confirming && ! isPostDirty && ! reviewConfirmed ) handleConfirm();
		}
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ confirming, isPostDirty, reviewConfirmed, handleConfirm ] );

	return (
		<div>
			{ reviewConfirmed ? (
				<div>
					<p style={ { color: '#155724', fontWeight: 600 } }>
						{ __( '✓ Reviewed for Accuracy', 'swiftletter' ) }
					</p>
					{ reviewConfirmedAt && (
						<p style={ { fontSize: '13px', color: '#666' } }>
							{ __( 'Confirmed:', 'swiftletter' ) }{ ' ' }
							{ new Date( reviewConfirmedAt ).toLocaleString() }
						</p>
					) }

					{ isPostDirty && (
						<p style={ { color: '#856404', fontWeight: 600 } } role="alert">
							{ __( '⚠ Content has been modified. Review confirmation will be reset on save.', 'swiftletter' ) }
						</p>
					) }
				</div>
			) : (
				<div>
					<p>
						{ __( 'This article has not been reviewed for accuracy yet.', 'swiftletter' ) }
					</p>

					<Button
						variant="primary"
						onClick={ handleConfirm }
						isBusy={ confirming }
						disabled={ confirming || isPostDirty }
						aria-keyshortcuts="Alt+Shift+R"
					>
						{ confirming
							? __( 'Confirming…', 'swiftletter' )
							: __( 'Mark as Reviewed for Accuracy', 'swiftletter' )
						}
					</Button>

					{ isPostDirty && (
						<p style={ { fontSize: '13px', color: '#666', marginTop: '0.5rem' } }>
							{ __( 'Save the post first before confirming review.', 'swiftletter' ) }
						</p>
					) }
				</div>
			) }

			{ error && (
				<p style={ { color: '#721c24', marginTop: '0.5rem' } } role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}
