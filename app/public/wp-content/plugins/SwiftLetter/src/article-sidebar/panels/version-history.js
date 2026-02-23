import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function VersionHistoryPanel() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const { editPost } = useDispatch( 'core/editor' );

	const [ versions, setVersions ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ restoring, setRestoring ] = useState( null );

	useEffect( () => {
		loadVersions();
	}, [ postId ] );

	const loadVersions = async () => {
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: `/swiftletter/v1/articles/${ postId }/versions`,
			} );
			setVersions( data );
		} catch {
			setVersions( [] );
		} finally {
			setLoading( false );
		}
	};

	const handleRestore = async ( versionId ) => {
		if ( ! window.confirm(
			__( 'Restore this version? A snapshot of the current content will be saved first.', 'swiftletter' )
		) ) {
			return;
		}

		setRestoring( versionId );
		try {
			const result = await apiFetch( {
				path: `/swiftletter/v1/articles/${ postId }/restore/${ versionId }`,
				method: 'POST',
			} );

			// Update editor content.
			if ( result.content ) {
				editPost( { content: result.content } );
			}

			loadVersions();
		} catch ( err ) {
			// Show error inline.
			window.alert( err.message || __( 'Failed to restore version.', 'swiftletter' ) );
		} finally {
			setRestoring( null );
		}
	};

	if ( loading ) {
		return (
			<div style={ { display: 'flex', alignItems: 'center', gap: '0.5rem' } }>
				<Spinner />
				{ __( 'Loading versions…', 'swiftletter' ) }
			</div>
		);
	}

	if ( versions.length === 0 ) {
		return (
			<p style={ { color: '#666' } }>
				{ __( 'No version snapshots yet. Versions are created when AI processing occurs.', 'swiftletter' ) }
			</p>
		);
	}

	const typeLabels = {
		pre_ai: __( 'Before AI', 'swiftletter' ),
		post_ai: __( 'After AI', 'swiftletter' ),
		manual: __( 'Manual', 'swiftletter' ),
		pre_restore: __( 'Before Restore', 'swiftletter' ),
	};

	return (
		<div>
			<ul style={ { listStyle: 'none', padding: 0, margin: 0 } }>
				{ versions.map( ( version ) => (
					<li
						key={ version.id }
						style={ {
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							padding: '0.5rem 0',
							borderBottom: '1px solid #eee',
						} }
					>
						<div>
							<strong>{ typeLabels[ version.version_type ] || version.version_type }</strong>
							<br />
							<span style={ { fontSize: '12px', color: '#666' } }>
								{ new Date( version.created_at ).toLocaleString() }
							</span>
						</div>

						<Button
							variant="tertiary"
							size="small"
							onClick={ () => handleRestore( version.id ) }
							isBusy={ restoring === version.id }
							disabled={ restoring !== null }
						>
							{ __( 'Restore', 'swiftletter' ) }
						</Button>
					</li>
				) ) }
			</ul>

			<Button
				variant="link"
				onClick={ loadVersions }
				style={ { marginTop: '0.5rem' } }
			>
				{ __( 'Refresh', 'swiftletter' ) }
			</Button>
		</div>
	);
}
